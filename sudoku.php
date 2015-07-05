<?php
require_once('log.php');

class Element {
	private $x, $y;
	private $max;
	private $value;
	private $modified;
	private $log;

	function
	__construct($x, $y, $base, $log)
	{
		$this->x = $x;
		$this->y = $y;
		$this->base = $base;
		$this->max = pow($base, 2);
		$this->value = array();
		for ($i = 0; $i < $this->max; $i++)
			$this->value[$i] = $i;
		$this->unmodified();
		$this->log = $log;
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
	is_same_row($other)
	{

		return $this->x === $other->x();
	}

	public function
	is_same_column($other)
	{

		return $this->y === $other->y();
	}

	private function
	boxindex($xory)
	{
		return (int)($xory / $this->base);
	}

	private function
	boxid()
	{
		return $this->boxindex($this->x) * $this->base +
		    $this->boxindex($this->y);
	}

	public function
	is_same_box($other)
	{
		return $this->boxid() === $other->boxid();
	}

	public function
	is_modified()
	{
		return $this->modified;
	}

	private function
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
	is_included($elementlist)
	{
		foreach ($elementlist as $e)
			if ($this->x === $e->x() && $this->y === $e->y())
				return true;
		return false;
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
		$this->log->debug("Set $v on ({$this->x},{$this->y})\n");
		// XXX: should return left candidates???
	}

	public function
	get()
	{
		return $this->value;
	}

	public function
	get_array()
	{
		$v = $this->get();
		if (is_array($v))
			return $v;
		$a = array();
		for ($i = 0; $i < $this->max; $i++)
			if ($i === $v)
				$a[] = $v;
			else
				$a[] = NULL;
		return $a;
	}

	public function
	get_array_without_null()
	{
		$v = $this->get();
		if (! is_array($v))
			return array($v);
		$a = array();
		foreach ($v as $n)
			if ($n !== NULL)
				$a[] = $n;
		return $a;
	}

	public function
	remove($v)
	{
		if ($this->is_set())
			return;
		if ($this->value[$v] === NULL)
			return;
		$this->value[$v] = NULL;	// XXX
		$this->modified();
		$this->log->debug("Remove $v on ({$this->x},{$this->y})\n");

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
	
		$this->log = new Log(Log::DEBUG);
		for ($i = 0; $i < $this->height; $i++)
			for ($j = 0; $j < $this->width; $j++)
				$this->matrix[] =
				    new Element($i, $j, $base, $this->log);

		$this->import($a);

		$this->solve();
	}

	private function
	addr2index($x, $y)
	{
		return $x * $this->width + $y;
	}

	private function
	range_check($x, $y)
	{
		if ($x > $this->height || $y > $this->width)
			throw new RangeException();
	}

	private function
	set($x, $y, $v)
	{
		$e = $this->get($x, $y);
		$e->set($v);
	}

	private function
	remove($e, $vs)
	{
		if (! is_array($vs))
			$vs = array($vs);
		foreach ($vs as $v) {
			$e->remove($v);
			// XXX: should look into another element when
			//	its value is set?
		}
	}

	private function
	import($a)
	{
		$this->log->debug("== Staring to import\n");
		if (! is_array($a))
			throw new UnexpectedValueException();
		for ($i = 0; $i < $this->height; $i++)
			for ($j = 0; $j < $this->width; $j++) {
				$v = $a[$i][$j];
				if ($v === NULL)
					continue;
				$this->set($i, $j, $v);
			}
		$this->log->debug("== Finish importing\n");
		$this->stat();
	}

	private function
	get($x, $y)
	{
		$this->range_check($x, $y);
		return $this->matrix[$this->addr2index($x, $y)];
	}

	private function
	get_modified()
	{
		$r = array();
		foreach ($this->matrix as $e)
			if ($e->is_modified()) {
				$e->unmodified();
				$r[] = $e;
			}
		return $r;
	}

	private function
	prune_row_or_column($samelist, $is_row = true)
	{
		if (! is_array($samelist))
			$samelist = array($samelist);
		$e = $samelist[0];
		$xory = $is_row ? $e->x() : $e->y();
		$v = $e->get_array_without_null();
		$this->log->debug('Pruning ' . ($is_row ? 'row' : 'column') .
		    ' ' . $e->to_s() .
		    ' on (' . $e->x() . ',' . $e->y() . ")\n");
		for ($i = 0; $i < $this->width; $i++) {
			if ($is_row)
				$other = $this->get($xory, $i);
			else
				$other = $this->get($i, $xory);
			if (! $other->is_included($samelist))
				$this->remove($other, $v);
		}
	}

	private function
	prune_row($samelist)
	{
		$this->prune_row_or_column($samelist, true);
	}

	private function
	prune_column($samelist)
	{
		$this->prune_row_or_column($samelist, false);
	}

	private function
	boxaddrbase($a)
	{
		return ((int)($a / $this->base)) * $this->base;
	}

	private function
	prune_box($samelist)
	{
		if (! is_array($samelist))
			$samelist = array($samelist);

		$e = $samelist[0];
		$v = $e->get_array_without_null();
		$xbase = $this->boxaddrbase($e->x());
		$ybase = $this->boxaddrbase($e->y());

		$this->log->debug('Pruning box '. $e->to_s() .
		    ' on (' . $e->x() . ',' . $e->y() . ")\n");

		for ($i = 0; $i < $this->base; $i++)
			for ($j = 0; $j < $this->base; $j++) {
				$other = $this->get($xbase + $i, $ybase + $j);
				if (! $other->is_included($samelist))
					$this->remove($other, $v);
			}
	}

	private function
	prune_by_set($r)
	{
		$this->log->debug("== Start to prune by set elements\n");
		foreach ($r as $e) {
			if (! $e->is_set())
				continue;
			$this->log->debug('Pruning ' . $e->get() .
			    ' on (' . $e->x() . ',' . $e->y() .")\n");
			$this->prune_row($e);
			$this->prune_column($e);
			$this->prune_box($e);
		}
		$this->log->debug("== Finish pruning by set elements\n");
		$this->stat();
	}

	private function
	compare_elements($a, $b)
	{

		$av = $a->get_array_without_null();
		$bv = $b->get_array_without_null();
		$d = count($av) - count($bv);
		if ($d !== 0)
			return $d;
		for ($i = 0; $i < count($av); $i++)
			if ($av[$i] !== $bv[$i])
				return $av[$i] - $bv[$i];
		return 0;
	}

	private function
	naked_prune($samelist)
	{
		if (count($samelist) < 2)
			return;
		$e = $samelist[0];
		$n = count($e->get_array_without_null());
		if (count($samelist) < $n)
			return;

		while (($e = array_shift($samelist)) != NULL) {
			$same_row = array($e);
			$same_col = array($e);
			$same_box = array($e);
			foreach ($samelist as $other) {
				if ($e === $other)
					continue;
				if ($e->is_same_row($other))
					$same_row[] = $other;
				if ($e->is_same_column($other))
					$same_col[] = $other;
				if ($e->is_same_box($other))
					$same_box[] = $other;
			}
			if (count($same_row) === $n)
				$this->prune_row($same_row);
			if (count($same_col) === $n)
				$this->prune_column($same_col);
			if (count($same_box) === $n)
				$this->prune_box($same_box);
		}
	}

	// naked pair/triple
	// XXX: it is too easy... and under construction...
	private function
	naked()
	{
		$a = array();
		foreach ($this->matrix as $e)
			if (! $e->is_set())
				$a[] = $e;

		usort($a, array($this, 'compare_elements'));

		$pe = NULL;
		$samelist = array();
		foreach ($a as $e) {
			if ($pe === NULL || $this->compare_elements($e, $pe)) {
				$pe = $e;
				$this->naked_prune($samelist);
				$samelist = array($e);
				continue;
			}
			$samelist[] = $e;
		}
		$this->naked_prune($samelist);
	}

	private function
	solve()
	{
		for (;;) {
			$r = $this->get_modified();
			if (empty($r))
				break;
			$this->prune_by_set($r);
			$this->log->debug("== Start naked pruning\n");
			$this->naked();
			$this->log->debug("== Finished naked pruning\n");
			$this->stat();
		}
	}

	private function
	stat()
	{
		$n = count($this->matrix);
		$ncands = $n * $this->max;
		$nsets = 0;
		$nleftcands = 0;
		foreach ($this->matrix as $e) {
			if ($e->is_set())
				++$nsets;
			else  {
				$v = $e->get_array_without_null();
				$nleftcands += count($v) - 1;
			}
		}
		$this->log->debug("All elements: $n, Set: {$nsets}, " .
		    "All candidates: $ncands, Left candidates: $nleftcands\n");
	}

	public function
	dump()
	{
		$bar = '';
		for ($i = 0; $i < $this->width; $i++) {
			$bar .= '+';
			if ($i % $this->base === 0)
				$bar .= '+';
			for ($j = 0; $j < $this->base; $j++)
				$bar .= '-';
		}
		$bar .= "++\n";
		$twobar = str_replace('-', '=', $bar);

		for ($i = 0; $i < $this->height; $i++) {
			if ($i % $this->base === 0)
				print($twobar);
			else
				print($bar);
			for ($n = 0; $n < $this->base; $n++) {
				for ($j = 0; $j < $this->width; $j++) {
					if ($j % $this->base === 0)
						print('||');
					else
						print('|');
					$e = $this->get($i, $j);
					$v = $e->get_array();
					for ($l = 0; $l < $this->base; $l++) {
						$a = $v[$n * $this->base + $l];
						if ($a !== NULL)
							printf('%x', $a);
						else if ($e->is_set())
							print('*');
						else
							print(' ');
					}
				}
				print('||');
				print("\n");
			}
		}
		print($twobar);
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
