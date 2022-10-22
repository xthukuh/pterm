<?php

//test lines
function _test_lines(){
	$max = 50;
	$ms = 200;
	_echo("Test $max lines (sleep $ms ms)...");
	for ($i = 1; $i <= $max; $i ++){
		_echo("[$i/$max] - test line.");
		usleep($ms * 1000);
	}
	_echo('Test lines done.');
}