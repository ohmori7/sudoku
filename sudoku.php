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

require_once('log.php');
require_once('lib.php');
require_once('element.php');

class Matrix {
	private $matrix;
	private $base;
	private $max, $width, $height;
	private $log;

	function
	__construct($a, $log = NULL)
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
	
		if (is_null($log))
			$log = new Log(Log::NOTICE);
		$this->log = $log;
		for ($i = 0; $i < $this->height; $i++)
			for ($j = 0; $j < $this->width; $j++)
				$this->matrix[] =
				    new Element($i, $j, $base, $this->log);

		$this->import($a);
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
			$this->dump_all();
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
				$this->dump_all();
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
				if (is_null($v))
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
	foreach_row_or_column($p, $cb, &$arg, $exclude, $isrow)
	{
		if (! is_array($exclude))
			$exclude = array($exclude);

		for ($i = 0; $i < $this->max; $i++) {
			if ($isrow)
				$e = $this->get($p->x(), $i);
			else
				$e = $this->get($i, $p->y());
			if ($e->is_included($exclude))
				continue;
			$this->$cb($e, $arg);
		}
	}

	private function
	foreach_row($e, $cb, &$arg, $exclude = array())
	{
		$this->foreach_row_or_column($e, $cb, $arg, $exclude, true);
	}

	private function
	foreach_column($e, $cb, &$arg, $exclude = array())
	{
		$this->foreach_row_or_column($e, $cb, $arg, $exclude, false);
	}

	private function
	foreach_box($e, $cb, &$arg, $exclude = array())
	{
		if (! is_array($exclude))
			$exclude = array($exclude);

		$xbase = $this->boxaddrbase($e->x());
		$ybase = $this->boxaddrbase($e->y());
		for ($i = 0; $i < $this->base; $i++)
			for ($j = 0; $j < $this->base; $j++) {
				$e = $this->get($xbase + $i, $ybase + $j);
				if ($e->is_included($exclude))
					continue;
				$this->$cb($e, $arg);
			}
	}

	private function
	conflict_check($e, $o)
	{
		if (! $e->is_set())
			return;
		if ($e->get() === $o->get())
			throw new UnexpectedValueException(
			    $e->to_s() . ' on ' . $o->a_to_s() .
			    ' conflicts with ' . $e->a_to_s());
	}

	private function
	sanity_check($e)
	{
		if (! $e->is_set())
			return;
		$this->foreach_row($e, 'conflict_check', $e, $e);
		$this->foreach_column($e, 'conflict_check', $e, $e);
		$this->foreach_box($e, 'conflict_check', $e, $e);
	}

	private function
	prune_cb($e, $v)
	{
		$this->remove($e, $v);
	}

	private function
	prune_row_or_column($exclude, $unit)
	{
		$e = $exclude[0];
		$v = $e->get_array_without_null();
		$this->log->info($e->a_to_s() . ': pruning ' . $e->to_s() .
		    " in $unit\n");
		$cb = "foreach_$unit";
		$this->$cb($e, 'prune_cb', $v, $exclude);
	}

	private function
	prune_row($exclude)
	{
		$this->prune_row_or_column($exclude, 'row');
	}

	private function
	prune_column($exclude)
	{
		$this->prune_row_or_column($exclude, 'column');
	}

	private function
	prune_box($exclude)
	{
		$e = $exclude[0];
		$v = $e->get_array_without_null();

		$this->log->info($e->a_to_s() . ': pruning ' . $e->to_s() .
		    " in box\n");

		$this->foreach_box($e, 'prune_cb', $v, $exclude);
	}

	private function
	prune_by_sets($e)
	{
		if (! $e->is_set())
			return;
		$this->log->info($e->a_to_s() . ': pruning ' . $e->to_s() .
		    "\n");
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
			$cb = "foreach_$unit";
			$this->$cb($e, 'candidate_check', $v, $e);
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
		$this->foreach_row($e, 'naked_check', $a, $e);
		$a = array($e);
		$this->foreach_column($e, 'naked_check', $a, $e);
		$a = array($e);
		$this->foreach_box($e, 'naked_check', $a, $e);
	}

	public function
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
		if (! $this->log->is_logging(Log::INFO))
			return;

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
		for ($j = 0; $j < $this->width; $j++) {
			if ($j % $this->base === 0)
				$bar .= '+';
			$bar .= '-';
		}
		$bar .= "+\n";

		for ($i = 0; $i < $this->height; $i++) {
			if ($i % $this->base === 0)
				print($bar);
			for ($j = 0; $j < $this->width; $j++) {
				if ($j % $this->base === 0)
					print('|');
				$e = $this->get($i, $j);
				$v = $e->get();
				if (is_array($v))
					print('*');
				else
					print(sudoku_ntoa($v));
			}
			print("|\n");
		}
		print($bar);
	}

	public function
	dump_all()
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
						if (! is_null($a))
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

?>
