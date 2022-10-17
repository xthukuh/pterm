<?php

//global options
Process::$VERBOSE = 1;
$cwd = getcwd();
$options = [
	'title' => 'NCMS Admin',
	'mypid' => getmypid(),
	'stdout' => ($tmp = '__install.log'),
	'log_file' => $cwd . '/' . $tmp,
	'pid_file' => $cwd . '/__install.pid',
	'tmp_file' => $cwd . '/__install.tmp',
	'err_file' => $cwd . '/__install_error.log',
	'target' => $GLOBALS['__TARGET__'],
	//'target' => '__ins.php',
	'method' => ($method = isset($_SERVER) && isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : null),
	'request' => isset($_REQUEST) ? $_REQUEST : null,
	'cwd' => $cwd,
	'cmd' => null,
	'worker' => null,
	'resume' => null,
	'page' => $method === 'get' ? 1 : 0,
	'args' => isset($argv) ? $argv : null,
	'__args__' => $GLOBALS['__args__'],
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
		case 'requirements':
		    //_run_cached('/usr/local/bin/php __ts.php test-requirements');
		    _run_cached('php self test-requirements');
			//_run_cached(sprintf('/usr/local/bin/php __ts.php %s', escapeshellcmd('test-requirements')));
			/*
			$cmd = _command(sprintf('/usr/local/bin/php %s %s', escapeshellcmd(getcwd() . '/__inc2.php'), escapeshellcmd('test-requirements')), 1) . ' & echo $!';
			_echo("shell_exec> $cmd");
			$out = shell_exec($cmd);
			_echo("output: $output");
			/*
			_echo("[exec]> $cmd");
			if (exec($cmd, $output, $exit) === false) _failure("exec() failed!");
			_echo(['output' => $output, 'exit' => $exit], 1, 0);
			//*/
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
			exit(0);
			break;
		case 'clear':
			_echo('>> Cleanup...');
			if (!_option('method')) sleep(1);
			_cache_get(1);
			_delete(Process::$LOG_FILE, 0);
			_delete('__ts.log', 0);
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