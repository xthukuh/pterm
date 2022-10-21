<?php

//global options
$cwd = getcwd();
$target = $GLOBALS['__TARGET__'];
$target_arg = strpos($target, ' ') !== false ? '"' . $target . "'" : $target;
$options = [
	'title' => 'P-TERM',
	'mypid' => getmypid(),
	'stdout' => ($tmp = '__install.log'),
	'log_file' => $cwd . '/' . $tmp,
	'pid_file' => $cwd . '/__install.pid',
	'tmp_file' => $cwd . '/__install.tmp',
	'composer_file' => $cwd . '/composer',
	'target' => $target,
	'target_arg' => $target_arg,
	'method' => ($method = isset($_SERVER) && isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : null),
	'request' => isset($_REQUEST) ? $_REQUEST : null,
	'cwd' => $cwd,
	'cmd' => null,
	'worker' => null,
	'resume' => null,
	'page' => $method === 'get' ? 1 : 0,
	'is_console' => Process::is_console(),
];
if (isset($argv) && is_array($argv) && ($len = count($argv))){
	if (isset($argv[1]) && ($val = trim($argv[1]))) $options['cmd'] = $val;
}
else {
	if (!empty($_REQUEST)){
	    if ($options['method'] === 'get') $options['page'] = -1;
		if (
		    in_array($options['method'], ['get', 'post'])
			&& array_key_exists('cmd', $_REQUEST)
			&& ($val = trim($_REQUEST['cmd']))
		){
		    $options['cmd'] = $val;
		    $options['page'] = 0;
		}
	}
	if ($options['page'] < 0){
	    _redirect();
	    exit;
	}
}
$GLOBALS['__options__'] = $options;
if (!is_file(_option('target'))) _failure('Installer target file is invalid!');

//handle command
if ($cmd = _option('cmd')){
    switch (strtolower($cmd)){
		case 'php':
			_echo('PHP: ' . Process::find_php(), 1, 0);
			break;
		case 'requirements':
		    _run_cached('test-requirements', 1);
			break;
		case 'test-requirements':
			_test_requirements();
			break;
		case 'test':
			_run_cached('test-lines', 1);
			break;
		case 'test-lines':
			_test_lines();
			break;
		case 'install-composer':
			_install_composer();
			break;
		case 'resume':
			_buffer_running();
			break;
		case 'cancel':
			_echo('> Cancel...');
			_cache_get(1, 1);
			exit(0);
			break;
		case 'clear':
			_echo('> Clear...');
			if (!_option('method')) sleep(1);
			_cache_get(1);
			exit(0);
			break;
		default:
			_run_cached($cmd);
			break;
	}
}

//show page
if (!$options['page']){
    //_echo(['options' => _option()]);
    exit(2);
}
_option('resume', _cache_get(1));