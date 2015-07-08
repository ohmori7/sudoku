<?php

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
		$this->log->info($this->a_to_s() . ': set ' . $this->to_s() .
		    "\n");
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
		$this->log->info($this->a_to_s() . ': remove ' .
		    sudoku_ntoa($v) . "\n");

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

?>
