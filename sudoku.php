<?php
require_once('log.php');

class Element {
	private $x, $y;
	private $max;
	private $value;
	private $modified;

	function
	__construct($x, $y, $max)
	{
		$this->x = $x;
		$this->y = $y;
		$this->max = $max;
		$this->value = array();
		for ($i = 0; $i < $this->max; $i++)
			$this->value[$i] = $i;
		$this->modified = false;
	}

	public function
	x()
	{
		return $this->x;
	}

	public function
	y()
	{
		return $this->y;
	}

	public function
	is_modified()
	{
		return $this->modified;
	}

	public function
	modified()
	{
		$this->modified = true;
	}

	public function
	unmodified()
	{
		$this->modified = false;
	}

	public function
	is_set()
	{
		return ! is_array($this->value);
	}

	public function
	set($v)
	{
		if ($v >= $this->max)
			throw new RangeException();
		if ($this->is_set())
			throw new UnexpectedValueException();
		if ($this->value[$v] !== $v)
			throw new UnexpectedValueException('Trying to set ' .
			    $v . ' from candidates ' . $this->to_s());
		$this->value = $v;
		$this->modified();
		// XXX: should return left candidates???
	}

	public function
	get()
	{
		$r = array();
		if (is_int($this->value))
			$r[] = $this->value;
		else
			foreach ($this->value as $v)
				if ($v !== NULL)
					$r[] = $v;
		return $r;
	}

	public function
	remove($v)
	{
		if ($this->is_set())
			return;
		if (! array_key_exists($v, $this->value))
			return;
		$this->value[$v] = NULL;	// XXX
		$this->modified();

		$left = NULL;
		for ($i = 0; $i < $this->max; $i++)
			if ($this->value[$i] !== NULL) {
				if ($left !== NULL)
					return;
				$left = $i;
			}
		if ($left === NULL)
			throw new BadMethodCallException();
		$this->set($left);
	}

	public function
	to_s()
	{
		if (is_int($this->value))
			return sprintf('%x', $this->value);
		$v = $this->value;
		$a = array();
		foreach ($v as $n)
			if ($n !== NULL)
				$a[] = sprintf('%x', $n);
		return '(' . implode($a) . ')';
	}
};

class Matrix {
	private $matrix;
	private $base;
	private $max, $width, $height;
	private $elementlist;
	private $log;

	function
	__construct($a)
	{

		if (! is_array($a))
			throw new InvalidArgumentException(
				    'should be an integer or array');
		$max = count($a);
		$base = (int)sqrt($max);
		if (pow($base, 2) !== $max)
			throw new InvalidArgumentException(
			    'should be power of integer');

		$this->base = $base;
		$this->max = $this->width = $this->height = $max;
		$this->matrix = array();
	
		$this->elementlist = array();
		for ($i = 0; $i < $this->height; $i++) {
			$this->matrix[$i] = array();
			for ($j = 0; $j < $this->width; $j++) {
				$e = new Element($i, $j, $this->max);
				$this->matrix[$i][$j] = $e;
				$this->elementlist[] = $e;
			}
		}

		$this->log = new Log(Log::DEBUG);

		$this->import($a);

		$this->solve();
	}

	private function
	compare_elements($a, $b)
	{

		$av = $a->get();
		$bv = $b->get();
		$d = count($av) - count($bv);
		if ($d !== 0)
			return $d;
		for ($i = 0; $i < count($av); $i++)
			if ($av[$i] !== $bv[$i])
				return $av[$i] - $bv[$i];
		return 0;
	}

	private function
	sort_elements()
	{
		usort($this->elementlist, array($this, 'compare_elements'));
	}

	private function
	range_check($x, $y)
	{
		if ($x > $this->height || $y > $this->width)
			throw new RangeException();
	}

	public function
	set($x, $y, $v)
	{
		$e = $this->get($x, $y);
		$e->set($v);
		$this->log->debug("Set $v on ($x,$y)\n");
	}

	private function
	remove($x, $y, $v)
	{
		$e = $this->get($x, $y);
		$e->remove($v);
		$this->log->debug("Remove $v on ($x,$y)\n");
		// XXX: should look into another element when its value is set?
	}

	private function
	import($a)
	{
		if (! is_array($a))
			throw new UnexpectedValueException();
		for ($i = 0; $i < $this->height; $i++)
			for ($j = 0; $j < $this->width; $j++) {
				$v = $a[$i][$j];
				if ($v === NULL)
					continue;
				$this->log->debug("Import $v on ($i,$j)\n");
				$this->set($i, $j, $v);
			}
	}

	private function
	get($x, $y)
	{
		$this->range_check($x, $y);
		return $this->matrix[$x][$y];
	}

	private function
	gets($x, $y)
	{
		$e = $this->get($x, $y);
		return $e->to_s();
	}

	private function
	get_modified()
	{
		$r = array();
		for ($i = 0; $i < $this->height; $i++)
			foreach ($this->matrix[$i] as $e)
				if ($e->is_modified())
					$r[] = $e;
		return $r;
	}

	private function
	boxaddrbase($a)
	{
		return ((int)($a / $this->base)) * $this->base;
	}

	private function
	prune($e)
	{
		if (! $e->is_set())
			return;

		$x = $e->x();
		$y = $e->y();
		$vs = $e->get();
		$v = $vs[0];

		for ($i = 0; $i < $this->height; $i++)
			if ($i !== $x)
				$this->remove($i, $y, $v);
		for ($j = 0; $j < $this->width; $j++)
			if ($i !== $y)
				$this->remove($x, $j, $v);

		$xbase = $this->boxaddrbase($x);
		$ybase = $this->boxaddrbase($y);
		for ($i = 0; $i < $this->base; $i++)
			for ($j = 0; $j < $this->base; $j++) {
				if ($i === $x && $j === $y)
					continue;
				$this->remove($xbase + $i, $ybase + $j, $v);
			}
	}

	private function
	solve()
	{

		$r = $this->get_modified();
		foreach ($r as $e) {
			$e->unmodified();
			$this->prune($e);
		}

		// naked pair/triple...
		// XXX: it is too easy... and under construction...
		$this->sort_elements();
		$pe = NULL;
		foreach ($this->elementlist as $e) {
			if ($e->is_set())
				continue;
			if ($pe === NULL || $this->compare_elements($e, $pe)) {
				$same = array();
				$pe = $e;
				continue;
			}
			$this->log->debug('same element found: ' .
			    $e->to_s() . "\n");
		}
	}

	public function
	dump()
	{
		$bar = ' ';
		for ($i = 0; $i < $this->width; $i++) {
			if ($i % $this->base === 0)
				$bar .= '+';
			$bar .= '---';
		}
		$bar .= "+\n";

		for ($i = 0; $i < $this->width; $i++) {
			if ($i % $this->base === 0)
				print($bar);
			print(' ');
			for ($j = 0; $j < $this->width; $j++) {
				if ($j % $this->base === 0)
					print('|');
				print(' ' . $this->gets($i, $j) . ' ');
			}
			print('|');
			print("\n");
		}
		print($bar);
	}
};

/******************************************************************************/
define('N',		NULL);
define('A',		0xa);
define('B',		0xb);
define('C',		0xc);
define('D',		0xd);
define('E',		0xe);
define('F',		0xf);

$init = array(
	array(N, 4, N, E,	N, 3, N, 0,	6, N, N, F,	N, N, C, N),
	array(B, N, N, N,	N, N, 5, D,	N, A, N, N,	4, E, N, 7),
	array(N, C, N, D,	9, N, 4, N,	3, 7, N, N,	N, N, 0, N),
	array(8, N, 0, N,	N, N, 2, N,	D, N, 9, N,	3, N, N, 1),

	array(N, 5, N, N,	N, N, D, N,	B, F, N, 4,	N, 9, 1, 6),
	array(6, N, 3, B,	5, 7, N, F,	N, N, N, A,	N, N, N, N),
	array(N, N, F, 7,	N, 4, N, N,	N, N, 5, 8,	C, N, 3, N),
	array(N, A, N, N,	3, N, 0, N,	N, N, 7, N,	E, N, F, N),

	array(1, N, N, N,	2, N, A, 6,	N, E, N, B,	F, N, N, N),
	array(N, N, 8, C,	N, F, N, N,	0, 6, N, N,	N, B, N, 9),
	array(4, N, E, N,	7, N, N, N,	5, N, N, N,	N, 0, N, 3),
	array(7, B, N, N,	0, N, 1, N,	N, 9, C, N,	6, N, 4, N),

	array(3, 6, N, 9,	N, N, N, B,	N, N, D, E,	N, 4, 5, N),
	array(N, N, B, N,	D, 6, N, N,	7, N, N, C,	9, N, N, 2),
	array(N, 8, N, 0,	N, 9, N, N,	A, N, 6, N,	B, N, N, F),
	array(N, N, C, N,	N, 2, F, 3,	N, N, N, 9,	N, 7, N, N),
);

$m = new Matrix($init);
$m->dump();
?>
