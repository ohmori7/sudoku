<?php
require_once('log.php');

function
sudoku_is_not_null($v)
{
	return ! is_null($v);
}

// similar to array_filter() but changes each key as well.
function
sudoku_array_filter($a)
{
	return array_values(array_filter($a, 'sudoku_is_not_null'));
}

function
sudoku_ntoa($v)
{
	static $x = '0123456789abcdefghijklmnopqrstuvwxyz';
	if ($v >= strlen($x))
		throw new RangeException();
	return $x[$v];
}

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
		$this->log->info("Set $v on ({$this->x},{$this->y})\n");
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
			$a[] = NULL;
		$a[$v] = $v;
		return $a;
	}

	public function
	get_array_without_null()
	{
		$v = $this->get();
		if (! is_array($v))
			return array($v);
		return sudoku_array_filter($v);
	}

	public function
	count()
	{
		return count($this->get_array_without_null());
	}

	public function
	remove($v)
	{
		if ($this->is_set())
			return false;
		if ($this->value[$v] === NULL)
			return false;
		$this->value[$v] = NULL;	// XXX
		$this->modified();
		$this->log->info("Remove $v on ({$this->x},{$this->y})\n");

		$left = NULL;
		for ($i = 0; $i < $this->max; $i++)
			if ($this->value[$i] !== NULL) {
				if ($left !== NULL)
					goto out;
				$left = $i;
			}
		if ($left === NULL)
			throw new BadMethodCallException();
		$this->set($left);
  out:
		return true;
	}

	public function
	to_s()
	{
		$v = $this->value;
		if (is_int($v))
			return sudoku_ntoa($v);
		$a = array_map('sudoku_ntoa', sudoku_array_filter($v));
		return '(' . implode($a) . ')';
	}

	public function
	a_to_s()
	{
		return "({$this->x},{$this->y})";
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
	boxaddrbase($a)
	{
		return ((int)($a / $this->base)) * $this->base;
	}

	private function
	range_check($x, $y)
	{
		if ($x > $this->height || $y > $this->width)
			throw new RangeException();
	}

	private function
	set($e, $v)
	{
		$e->set($v);
		if ($this->log->is_logging(Log::DEBUG)) {
			$this->dump();
			$this->sanity_check($e);
		}
	}

	private function
	remove($e, $vs)
	{
		if (! is_array($vs))
			$vs = array($vs);
		foreach ($vs as $v) {
			$isremoved = $e->remove($v);
			if ($isremoved && $this->log->is_logging(Log::DEBUG)) {
				$this->dump();
				$this->sanity_check($e);
			}
			// XXX: should look into another element when
			//	its value is set?
		}
	}

	private function
	import($a)
	{
		$this->log->info("== Staring to import\n");
		if (! is_array($a))
			throw new UnexpectedValueException();
		for ($i = 0; $i < $this->height; $i++)
			for ($j = 0; $j < $this->width; $j++) {
				$v = $a[$i][$j];
				if ($v === NULL)
					continue;
				$e = $this->get($i, $j);
				$this->set($e, $v);
			}
		$this->log->info("== Finish importing\n");
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
	foreach_row_or_column($x_or_y, $cb, &$arg, $exclude, $isrow)
	{
		if (! is_array($exclude))
			$exclude = array($exclude);

		for ($i = 0; $i < $this->max; $i++) {
			if ($isrow)
				$e = $this->get($x_or_y, $i);
			else
				$e = $this->get($i, $x_or_y);
			if ($e->is_included($exclude))
				continue;
			$this->$cb($e, $arg);
		}
	}

	private function
	foreach_row($x, $cb, &$arg, $exclude = array())
	{
		$this->foreach_row_or_column($x, $cb, $arg, $exclude, true);
	}

	private function
	foreach_column($y, $cb, &$arg, $exclude = array())
	{
		$this->foreach_row_or_column($y, $cb, $arg, $exclude, false);
	}

	private function
	foreach_box($coord, $cb, &$arg, $exclude = array())
	{
		if (! is_array($exclude))
			$exclude = array($exclude);

		$xbase = $this->boxaddrbase($coord[0]);
		$ybase = $this->boxaddrbase($coord[1]);
		for ($i = 0; $i < $this->base; $i++)
			for ($j = 0; $j < $this->base; $j++) {
				$e = $this->get($xbase + $i, $ybase + $j);
				if ($e->is_included($exclude))
					continue;
				$this->$cb($e, $arg);
			}
	}

	private function
	conflict_check($e, $ee)
	{
		if (! $e->is_set())
			return;
		if ($e->get() === $ee->get())
			throw new UnexpectedValueException(
			    $e->to_s() . ' on ' . $ee->a_to_s() .
			    ' conflicts with ' . $e->a_to_s());
	}

	private function
	sanity_check($e)
	{
		if (! $e->is_set())
			return;
		$this->foreach_row($e->x(), 'conflict_check', $e, $e);
		$this->foreach_column($e->y(), 'conflict_check', $e, $e);
		$this->foreach_box(array($e->x(), $e->y()), 'conflict_check',
		    $e, $e);
	}

	private function
	prune_cb($e, $v)
	{
		$this->remove($e, $v);
	}

	private function
	prune_row_or_column($samelist, $unit)
	{
		$e = $samelist[0];
		$v = $e->get_array_without_null();
		if ($unit === 'row')
			$xory = $e->x();
		else
			$xory = $e->y();
		$this->log->info('Pruning ' . $e->to_s() . " in $unit for " .
		    $e->a_to_s() . "\n");
		$cb = "foreach_$unit";
		$this->$cb($xory, 'prune_cb', $v, $samelist);
	}

	private function
	prune_row($samelist)
	{
		$this->prune_row_or_column($samelist, 'row');
	}

	private function
	prune_column($samelist)
	{
		$this->prune_row_or_column($samelist, 'column');
	}

	private function
	prune_box($samelist)
	{
		$e = $samelist[0];
		$coord = array($e->x(), $e->y());
		$v = $e->get_array_without_null();

		$this->log->info('Pruning ' . $e->to_s() . ' in box for ' .
		    $e->a_to_s() . "\n");

		$this->foreach_box($coord, 'prune_cb', $v, $samelist);
	}

	private function
	prune_by_sets($e)
	{
		if (! $e->is_set())
			return;
		$this->log->info('Pruning ' . $e->to_s() . ' for ' .
		    $e->a_to_s() . "\n");
		$this->prune_row(array($e));
		$this->prune_column(array($e));
		$this->prune_box(array($e));
	}

	private function
	candidate_check($e, &$arg)
	{
		foreach ($e->get_array_without_null() as $o)
			$arg[$o] = NULL;
	}

	private function
	prune_by_candidates($e)
	{
		if ($e->is_set())
			return;
		foreach (array('row', 'column', 'box') as $unit) {
			$v = $e->get();
			if ($unit === 'row')
				$addr = $e->x();
			else if ($unit === 'column')
				$addr = $e->y();
			else
				$addr = array($e->x(), $e->y());
			$cb = "foreach_$unit";
			$this->$cb($addr, 'candidate_check', $v, $e);
			$v = sudoku_array_filter($v);
			if (count($v) === 1) {
				$this->log->debug($e->a_to_s() .
				    ": pruned by $unit\n");
				$this->set($e, $v[0]);
				return;
			}
		}
	}

	private function
	naked_check($e, &$samelist)
	{
		$o = $samelist[0];
		if (strcmp($e->to_s(), $o->to_s()) !== 0)
			return;
		$samelist[] = $e;
		$d = $o->count() - count($samelist);
		$this->log->debug($o->a_to_s() . ': matches ' . $e->a_to_s() .
		    ' on ' . $e->to_s() . " left $d element(s)\n");
		if ($d > 0)
			return;
		if ($d < 0)
			throw new UnexpectedValueException();
		if ($e->is_same_row($o))
			$this->prune_row($samelist);
		if ($e->is_same_column($o))
			$this->prune_column($samelist);
		foreach ($samelist as $o)
			if (! $e->is_same_box($o))
				return;
		$this->prune_box($samelist);
	}

	// naked pair/triple
	private function
	prune_by_naked($e)
	{
		if ($e->is_set())
			return;

		$a = array($e);
		$this->foreach_row($e->x(), 'naked_check', $a, $e);
		$a = array($e);
		$this->foreach_column($e->y(), 'naked_check', $a, $e);
		$a = array($e);
		$this->foreach_box(array($e->x(), $e->y()),
		    'naked_check', $a, $e);
	}

	private function
	solve()
	{
		while ($modified = $this->get_modified()) {
			$this->log->info("== Start pruning\n");
			foreach ($modified as $e) {
				$this->log->debug($e->a_to_s() . ': ' .
				    $e->to_s() . ": examine\n");
				$this->prune_by_sets($e);
				$this->prune_by_candidates($e);
				$this->prune_by_naked($e);
			}
			$this->log->info("== End pruning\n");
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
		$this->log->info("All elements: $n, Set: {$nsets}, " .
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
							print(sudoku_ntoa($a));
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
