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

require_once('sudoku.php');

define('N',		NULL);
define('A',		0xa);
define('B',		0xb);
define('C',		0xc);
define('D',		0xd);
define('E',		0xe);
define('F',		0xf);

/*
 * below quiz is provided by Technology-Gym as follows:
 *	http://tech-gym.com/2015/06/%E3%81%9D%E3%81%AE%E4%BB%96/1641.html
 */
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

$log = new Log();
while ($arg = array_shift($argv))
	if ($arg === '-v')
		$log->increase_level();

$m = new Matrix($init, $log);
$m->solve();
$m->dump();

?>
