<?php

//target file
$target = defined('P_TERM') ? P_TERM : null;
if (!($target && is_file($target))) _failure($target ? "Invalid target file (\P_TERM=$target)" : 'Undefined target file (\P_TERM)');
$target_arg = strpos($target, ' ') !== false ? '"' . $target . "'" : $target;

//set options
$version = '1.0.0';
$cwd = getcwd();
$options = [
	'title' => 'P-TERM v' . $version,
	'version' => $version,
	'mypid' => getmypid(),
	'stdout' => ($tmp = '__pterm.xx.log'),
	'log_file' => $cwd . '/' . $tmp,
	'cache_file' => $cwd . '/__pterm.xx.cache',
	'composer_file' => $cwd . '/composer',
	'target' => $target,
	'target_arg' => $target_arg,
	'cwd' => $cwd,
	'cmd' => null,
	'resume' => null,
	'page' => 0,
	'is_console' => Process::is_console(),
];
if (isset($argv) && is_array($argv) && ($len = count($argv))){
	if ($len > 1){
		$val = array_slice($argv, 1);
		if ($val = trim(implode(' ', $val))) $options['cmd'] = $val;
	}
}
else {
	$options['method'] = $method = isset($_SERVER) && isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : null;
	if ($method === 'get') $options['page'] = 1;
	if (isset($_REQUEST) && is_array($_REQUEST) && !empty($_REQUEST)){
		if ($method === 'get') $options['page'] = -1;
		if (
			in_array($method, ['get', 'post'])
			&& array_key_exists('cmd', $_REQUEST)
			&& ($val = trim($_REQUEST['cmd']))
		){
			$options['cmd'] = $val;
			$options['page'] = 0;
		}
	}
}
$GLOBALS['__options__'] = $options;

//handle command
if ($cmd = _option('cmd')){
	switch (strtolower($cmd)){
		
		//options
		case 'options':
			_echo(_option());
			break;
		
		//exit
		case 'exit':
			break;

		//php
		case 'php':
			_echo('- PHP_EXE: ' . Process::find_php(), 1, 0);
			break;
		
		//test requirements
		case 'requirements':
			_run_bg('requirements-worker', 1);
			break;
		case 'requirements-worker':
			_check_requirements();
			break;
		
		//test lines
		case 'test':
			_run_bg('test-worker', 1);
			break;
		case 'test-worker':
			_test_lines();
			break;
		
		//install/update composer
		case 'install-composer':
			_run_bg('install-composer-worker', 1);
			break;
		case 'install-composer-worker':
			_install_composer();
			break;
		
		//resume running - buffer
		case 'resume':
			_output();
			break;
		
		//cancel running
		case 'cancel':
			_cache_get(-1, 1);
			break;
		
		//clear cache
		case 'clear':
			if (_option('is_console') === 1) sleep(1);
			_cache_get(1);
			break;
		
		//custom command
		default:
			_run_bg($cmd);
			break;
	}

	//exit - done
	exit;
}

//redirect - html page
if (($page = _option('page')) < 0){
	_redirect();
	exit;
}

//exit - no html/unsupported
if (!$page) exit(2);

//html page
_option('resume', _cache_get(-1));