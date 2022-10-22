<?php

//check requirements
function _check_requirements(){
	
	//init vars
	$errors = [];
	$fails = 0;
	$sp = ($is_console = _option('is_console')) ? '  ' : ' ';
	$pass = '✔️' . $sp;
	$fail = '❌' . $sp;

	//print helper
	$_msg = function($str, $p=null) use (&$pass, &$fail, &$fails, &$errors){
		if (!is_null($p) && !$p){
			$fails += 1;
			$errors[] = $str;
		}
		_echo(sprintf('%s%s', is_null($p) ? '' : ($p ? $pass : $fail), $str));
	};

	//test
	_echo('Checking requirements...');
	_echo('');

	//Test PHP Version >= 7.3
	$c = '7.3';
	$v = PHP_VERSION;
	if (!version_compare($v, $c, '>=')) $_msg("Error PHP Version ($v < $c).", 0);
	else $_msg("PHP Version ($v >= $c)", 1);
	
	//Test Extensions
	$required_exts = [
		'bcmath' => 'BCMath PHP Extension',
		'ctype' => 'Ctype PHP Extension',
		'fileinfo' => 'Fileinfo PHP Extension',
		'json' => 'JSON PHP Extension',
		'mbstring' => 'Mbstring PHP Extension',
		'openssl' => 'OpenSSL PHP Extension',
		'PDO' => 'PDO PHP Extension',
		'tokenizer' => 'Tokenizer PHP Extension',
		'xml' => 'XML PHP Extension',
		'gd' => 'GD PHP Extension',
	];

	//check loaded extension
	$loaded_exts = get_loaded_extensions();
	foreach ($required_exts as $ext => $title){
		if (!in_array($ext, $loaded_exts)) $_msg("Error $title ($ext) Not Loaded.", 0);
		else $_msg("Loaded $title ($ext)", 1);
	}

	//result
	_echo("\nTest " . (!$fails ? 'PASSED' : "FAILED ($fails)"));
	return !$fails;
}