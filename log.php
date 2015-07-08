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

class Log {
	const EMERG	= 0;
	const ALERT	= 1;
	const CRIT	= 2;
	const ERR	= 3;
	const WARN	= 4;
	const NOTICE	= 5;
	const INFO	= 6;
	const DEBUG	= 7;

	private $level;

	function
	__construct($level = self::NOTICE)
	{
		$this->level = $level;
	}

	public function
	increase_level()
	{
		if ($this->level >= self::DEBUG)
			return;
		++$this->level;
	}

	public function
	is_logging($level)
	{
		return $this->level >= $level;
	}

	private function
	log($level, $msg)
	{
		if ($this->is_logging($level))
			fprintf(STDOUT, $msg);
	}

	public function
	emerg($msg)
	{
		$this->log(self::EMERG, $msg);
	}

	public function
	alert($msg)
	{
		$this->log(self::ALERT, $msg);
	}

	public function
	crit($msg)
	{
		$this->log(self::CRIT, $msg);
	}

	public function
	err($msg)
	{
		$this->log(self::ERR, $msg);
	}

	public function
	warn($msg)
	{
		$this->log(self::WARN, $msg);
	}

	public function
	notice($msg)
	{
		$this->log(self::NOTICE, $msg);
	}

	public function
	info($msg)
	{
		$this->log(self::INFO, $msg);
	}

	public function
	debug($msg)
	{
		$this->log(self::DEBUG, $msg);
	}
};
?>
