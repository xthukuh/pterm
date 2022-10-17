<?php

//global options
Process::$VERBOSE = 2;
$cwd = getcwd();
$options = [
	'title' => 'NCMS Installer',
	'mypid' => getmypid(),
	'stdout' => ($tmp = '__install.log'),
	'log_file' => $cwd . '/' . $tmp,
	'pid_file' => $cwd . '/__install.pid',
	'tmp_file' => $cwd . '/__install.tmp',
	'err_file' => $cwd . '/__install_error.log',
	'target' => $GLOBALS['__TARGET__'],
	'method' => null,
	'cwd' => $cwd,
	'cmd' => null,
	'worker' => null,
	'resume' => null,
];
if (isset($argv) && is_array($argv) && ($len = count($argv))){
	if (isset($argv[1]) && ($val = trim($argv[1]))) $options['cmd'] = $val;
}
else {
	if (isset($_REQUEST) && !empty($_REQUEST)){
		if (!in_array($method = strtolower($_SERVER['REQUEST_METHOD']), ['get', 'post'])) _failure("Unsupported request method ($method).");
		$options['method'] = $method;
		if (
			in_array($method, ['get', 'post'])
			&& array_key_exists('cmd', $_REQUEST)
			&& ($val = trim($_REQUEST['cmd']))
		) $options['cmd'] = $val;
	}
}
$GLOBALS['__options__'] = $options;
if (!is_file(_option('target'))) _failure('Installer target file is invalid!');

//DEBUG:
//_echo(['options' => _option()], 1, 0);

//handle command
if ($cmd = _option('cmd')){
	switch (strtolower($cmd)){
		case 'requirements':
			_run_cached('php self test-requirements');
			break;
		case 'test-requirements':
			_test_requirements();
			break;
		case 'test':
			_run_cached('php self test-lines');
			break;
		case 'test-lines':
			_test_lines();
			break;
		case 'install-composer':
			_worker_install_composer();
			break;
		case 'resume':
			_run_resume();
			break;
		case 'cancel':
			_echo('>> Cancel...');
			_cache_get(1, 1);
			break;
		case 'clear':
			_echo('>> Cleanup...');
			if (!_option('method')) sleep(1);
			_cache_get(1);
			break;
		default:
			_run_cached($cmd);
			break;
	}
	exit;
}

//defaults - redirect/check running
if (_option('method') === 'get') _redirect();
elseif (Process::is_console()) exit(2);
else _option('resume', _cache_get(1));
