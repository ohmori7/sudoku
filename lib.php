<?php

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

?>
