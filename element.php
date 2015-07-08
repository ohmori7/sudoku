<?php
/*
 * Copyright (c) 2015 Motoyuki OHMORI <ohmori@tottori-u.ac.jp>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE REGENTS AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE REGENTS OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */

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
		if (is_null($this->value[$v]))
			return false;
		$this->value[$v] = NULL;	// XXX
		$this->modified();
		$this->log->info($this->a_to_s() . ': remove ' .
		    sudoku_ntoa($v) . "\n");

		$left = $this->get_array_without_null();
		if (empty($left))
			throw new BadMethodCallException();
		if (count($left) === 1)
			$this->set($left[0]);
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
