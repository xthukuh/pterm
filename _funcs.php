<?php

//get option
function _option($key=null, $value='!undefined'){
	if (!(isset($GLOBALS['__options__']) && is_array($GLOBALS['__options__']))) return _failure('Undefined $GLOBALS[__options__] array.');
	if (empty($key)) return $value === '!undefined' ? $GLOBALS['__options__'] : _failure('Undefined $GLOBALS[__options__] key.');
	if ($value !== '!undefined') $GLOBALS['__options__'][$key] = $value;
	return array_key_exists($key, $GLOBALS['__options__']) ? $GLOBALS['__options__'][$key] : null;
}

//header
function _header($value){
	static $header_set;
	if ($header_set) return;
	$header_set = 1;
	header($value);
}

//request url
function _request_url(){
	if (!(
		isset($_SERVER)
		&& is_array($_SERVER)
		&& array_key_exists($key = 'HTTP_HOST', $_SERVER)
		&& ($host = trim($_SERVER[$key]))
		&& array_key_exists($key = 'REQUEST_URI', $_SERVER)
		&& is_string($request_uri = $_SERVER[$key])
	)) return false;
	if ($query_string = array_key_exists($key = 'QUERY_STRING', $_SERVER) ? trim($_SERVER[$key]) : null){
		$request_uri = trim(str_replace($query_string, '', $request_uri), '? ');
	}
	$protocol = 'http' . (array_key_exists($key = 'HTTPS', $_SERVER) && trim($_SERVER[$key]) ? 's' : '') . '://';
	return $protocol . $host . '/' . trim($request_uri, '/');
}

//redirect
function _redirect(){
	if (!($url = _request_url())) return _failure('Unable to get the request/redirect url.');
	_header("Location: $url");
}

//echo/print
function _echo($value, $br=1, $exit_status=null){
	static $print_start;
	
	//print start
	if (!$print_start){
		$print_start = 1;
		_header('Content-type: text/plain');
		Process::print_start();
	}

	//print out
	Process::print_out($value, $br, $str);
	if (_option('worker')) _write(_option('log_file'), trim($str) . "\n", 1); //FIX: DEBUG:
	if (is_integer($exit_status)) exit($exit_status);
}

//failure
function _failure($error){
	if (_option('worker')) throw new Exception($error);
	_write(_option('err_file'), trim($error) . "\n", 1);
	_echo("FAILURE: $error", 1, 1);
}

//read
function _read($path, $assoc=0){
	if (($data = file_get_contents($path)) === false) return _failure("unable to read ($path)");
	if ($assoc && !is_array($data = json_decode($data, 1))) return _failure("invalid assoc data ($path)");
	return $data;
}

//write
function _write($path, $data, $append=0, &$bytes=null){
	$bytes = null;
	$data = is_array($data) || is_object($data) ? json_encode($data) : (string) $data;
	if (($res = @file_put_contents($path, $data, $append ? FILE_APPEND : 0)) === false) return _failure("unable write ($path)");
	$bytes = $res;
	return true;
}

//delete
function _delete($path, $fails=1){
	if (!is_file($path)) return -1;
	if (!@unlink($path)) return $fails ? _failure("unable delete ($path)") : false;
	return true;
}

//cleanup
function _cleanup($fails=0){
	_delete(_option('tmp_file'), $fails);
	_delete(_option('log_file'), $fails === 1);
	_delete(_option('pid_file'), $fails);
}

//cache data
function _cache_data($data){
	if (!(
		is_array($data)
		&& isset($data[$key = 'cmd'])
		&& ($cmd = trim($data[$key]))
		&& isset($data[$key = 'pid'])
		&& ($pid = Process::pid($data[$key]))
	)) return false;
	$type = isset($data[$key = 'type']) && in_array($val = trim(strtolower($data[$key])), ['process', 'worker']) ? $val : 'process';
	$expires = isset($data[$key = 'expires']) && is_integer($val = $data[$key]) && $val >= 0 ? $val : 0;
	$child_cmd = isset($data[$key = 'child_cmd']) && ($val = trim($data[$key])) ? $val : null;
	$child_pid = isset($data[$key = 'child_pid']) && ($val = Process::pid($data[$key])) ? $val : null;
	return [
		'cmd' => $cmd,
		'pid' => $pid,
		'type' => $type,
		'expires' => $expires,
		'child_cmd' => $child_cmd,
		'child_pid' => $child_pid,
	];
}

//cache set
function _cache_set($data){
	if (!($data = _cache_data($data))) return _failure('Invalid cache data.');
	_write(_option('tmp_file'), $data);
	return $data;
}

//cache get
function _cache_get($running=0, $kill=0){
	if (!is_file($path = _option('tmp_file'))) return;
	if (!($data = _cache_data(_read($path, 1)))) return _failure("Invalid cache file data contents. ($path)");
	$is_worker = $data['type'] === 'worker';
	$is_running = 0;
	if ($is_worker){
		if (time() < $data['expires']){
			if ($pid = $data['child_pid']){
				if ($res = Process::exists($pid, $err)) $is_running = $res;
				if ($res === false) return _failure($err);
			}
			else $is_running = -1;
		}
	}
	else {
		if ($res = Process::exists($data['pid'], $err)) $is_running = $res;
		if ($res === false) return _failure($err);
	}
	if ($is_running){
		if ($kill){
			if ($is_running >= 1) _kill_pid($is_running);
			else _kill_pid($data['pid']);
			usleep(200 * 1000); //delay 200ms
		}
		else return $data;
	}
	_cleanup(2);
	if (!$running) return $data;
}

//kill pid
function _kill_pid($pid){
	if (!(is_integer($pid) && $pid >= 1)) return;
	if (($res = Process::kill($pid, $error)) === false) return _failure($error);
	return $res;
}

//buffer running
//FIX: refactor
function _buffer_running($data=null){
	$is_cache = !!($data = _cache_data($data));
	if (!$is_cache && !($data = _cache_get())) return;

	//opened/resumed process
	$cmd = $data['cmd'];
	$pid = $data['pid'];
	if ($data['type'] === 'worker'){
		$cmd = sprintf('[%s] %s - (%s)', $pid, $cmd, $data['child_cmd']);
		$pid = $data['child_pid'];
	}
	if (!$is_cache) _echo("[resume: $pid]> $cmd");

	//output buffer
	$_restore = Process::no_limit();
	try {
		$abort = 0;
		if (is_file($path = _option('log_file'))){
			$res = Process::read_file($path, $pid, $_seek=0, $_print=true, $_callback=null, $error, $abort);
			if ($abort) _echo("Buffer abort: $abort");
			if ($res === false) throw new Exception("Buffer Error: $error");
			else _cache_get(1);
		}
		else {
			_echo("[$pid]> process output log file not found - polling...");
			$res = Process::poll_exists($pid, false, function() use (&$pid, &$abort) {
				if (connection_aborted()){
					$abort = 2;
					return true;
				}
				_echo("- [$pid] is running.");
			}, $_sleep_ms=1000, $timeout=null, $error);
			if ($abort) _echo("Buffer abort: $abort");
			if (!$res) throw new Exception("Polling Error: $error");
			if (!$abort) _echo("[$pid] done.", 1, 0);
		}
		$_restore();
		if ($abort) return false;
	}
	catch (Exception $e){
		$_restore();
		return _failure($e -> getMessage());
	}
}

//command line
//FIX: refactor
function _command($cmd, $stdout=0){
	static $php;
	
	//check cmd
	if (!(is_string($cmd) && ($cmd = trim($cmd)))) return _failure('Empty command line.');
	
	//php executable
	if (!$php){
		$php = Process::find_php();
		if (strpos($php, ' ') !== false) $php = '"' . $php . "'";
	}

	//show php executable
	if (strtolower($cmd) === 'php') return _echo("$cmd -> $php\n", 1, 0);
	
	//parse cmd - check stdout, stderr
	if (strpos($cmd, '>') !== false){
		$parse = Process::parse($cmd);
		if (isset($parse['stdout']) && trim($parse['stdout'])) return _failure("command stdout pipe not supported. ($cmd)");
		if (isset($parse['stderr']) && trim($parse['stderr'])) return _failure("command stderr pipe not supported. ($cmd)");
	}

	//normalize
	if (stripos($cmd, 'php self') === 0){
		$file = _option('target');
		if (strpos($file, ' ') !== false) $file = '"' . $file . '"';
		$cmd = preg_replace('/^php self\s+/i', "$php $file ", $cmd);
	}
	elseif (stripos($cmd, 'php') === 0) $cmd = preg_replace('/^php\s+/i', "$php ", $cmd);
	elseif (stripos($cmd, 'composer') === 0){
	    if (strtolower($cmd) === 'composer') $cmd = "$php composer";
		else $cmd = preg_replace('/^composer\s+/i', "$php composer ", $cmd);
	}

	//stdout log
	if ($stdout) $cmd .= ($stdout === 2 ? ' >> ' : ' > ') . _option('stdout');
	$cmd .= ' 2>&1';
	
	//result
	return $cmd;
}

//run background process
//FIX: refactor
function _run($cmd, $cached=0){
	$pid_file = $cached ? _option('pid_file') : null;
	/*
	$proc = new Process($cmd, ['cwd' => _option('cwd')], 1);
	if (!$proc -> open(function($p){
	    $p -> close_pipe(0);
	})) return _failure("Run Error: " . $proc -> error);
	if (!($pid = Process::pid($proc -> pid, $err))){
	    $proc -> close(1);
	    return _failure("Run PID Error: $err");
	}
	$GLOBALS['__proc__'] = $proc;
	if ($pid_file) _write($pid_file, "$pid");
	sleep(1);
	//*/
	/*
	$pid = Process::run_bg($cmd, $error, ['cwd' => _option('cwd')], $cb=null, $pid_file);
	if (!$pid) return _failure("Run Error: $error");
	//*/
	putenv('COMPOSER_HOME=' . _option('cwd') . '/vendor/bin/composer');
	$cmd .= ' & echo $!';
	$out = shell_exec($cmd);
	if (!($pid = Process::pid((int) trim($out), $err))) return _failure("Shell exec failed: $err ($cmd)");
	$res = Process::exists($pid);
	//DEBUG: _echo("[$pid] started...");
	return [
		'cmd' => $cmd,
		'pid' => $pid
	];
}

//run resume
function _run_resume(){
	$res = _buffer_running();
	if ($data = _cache_get(1)) return _failure(sprintf('Previous %s is still running. [%s] (%s)', $data['type'], $data['pid'], $data['cmd']));
}

//run cached process
//FIX: refactor
function _run_cached($cmd){
	_run_resume();
	$cmd = _command($cmd, 1);
	//DEBUG: _echo(">> $cmd");
	$data = _run($cmd, 1);
	$data = _cache_set($data);
	$res = _buffer_running($data);
	if ($res !== false) _run(_command('php self clear')); //auto cleanup
	return $res;
}

//worker cache
function _worker_set($data){
	$data = is_array($data) ? $data : [];

	//new cache data
	if (!($worker = _cache_data(_option('worker')))){
		if (!(array_key_exists($key = 'cmd', $data) && ($cmd = trim($data[$key])))){
			return _failure('Undefined worker data cmd.');
		}
		$worker = [
			'cmd' => $cmd,
			'pid' => _option('mypid'),
			'type' => 'worker',
			'expires' => time() + 5, //5 sec
			'child_cmd' => null,
			'child_pid' => null,
		];
	}

	//update cache data
	if (array_key_exists($key = 'expires', $data)) $worker[$key] = $data[$key];
	if (array_key_exists($key = 'child_cmd', $data)) $worker[$key] = $data[$key];
	if (array_key_exists($key = 'child_pid', $data)) $worker[$key] = $data[$key];
	
	//save
	return _option('worker', _cache_set($worker));
}

//worker
//FIX: refactor
function _worker($cmd, $handler){
	if (!is_callable($handler)) return _failure('Worker handler is not callable.');
	_run_resume();
	_echo(">>> $cmd...");
	_worker_set(['cmd' => $cmd]);
	$_restore = Process::no_limit();
	$_done = function() use (&$_restore, &$cmd){
		$_restore();
		//_cleanup(2);
		_option('worker', null);
		_echo(">>> $cmd done.");
	};
	try {
		$handler();
		$_done();
	}
	catch (Exception $e){
		$_done();
		return _failure($e -> getMessage());
	}
}

//worker install composer
//FIX: refactor
function _worker_install_composer(){
	_worker('install-composer', function(){
		$ts = microtime(1);
		
		//copy helper
		$_copy = function ($source, $dest){
			$tmp = basename($dest);
			_echo("\nCopy: $source -> $tmp");
			if (copy($source, $dest)) return true;
			return _failure("Unable to copy ($source -> $dest)");
		};
		
		//composer files
		$cwd = _option('cwd');
		$sig_file = $cwd . '/composer.sig';
		$setup_file = $cwd . '/composer-setup.php';
		$phar_file = $cwd . '/composer.phar';
		$composer_file = $cwd . '/composer';
		
		//get signature
		$_copy('https://composer.github.io/installer.sig', $sig_file);
		$sig = trim(_read($sig_file));
		_delete($sig_file);
		_echo("Signature: $sig");
	
		//get setup
		$setup_file = _option('cwd') . '/composer-setup.php';
		$_copy('https://getcomposer.org/installer', $setup_file);
		if (hash_file('sha384', $setup_file) !== $sig){
			_delete($setup_file);
			$eta = time() - $ts;
			return _failure("Installer corrupt - deleted. ($eta sec)\n");
		}
		$eta = microtime(1) - $ts;
		_echo("Installer verified. ($eta sec)");
		
		//run setup
		$ts = microtime(1);
		_echo("\nSetup composer...");
		_delete($phar_file);
		$cmd = _command('php composer-setup.php', 2);
		/*
		_echo("exec>> $cmd");
		$res = exec($cmd . ' & echo $!', $output, $exit);
		_echo(['output' => $output, 'exit' => $exit]);
		if ($res === false) return _failure('Exec failed.');
		*/
		///*
		$data = _run($cmd, 1);
		$res = _buffer_running(_worker_set([
			'expires' => time() + 5,
			'child_cmd' => $data['cmd'],
			'child_pid' => $data['pid'],
		]));
		$eta = microtime(1) - $ts;
		if ($res === false){
			_kill_pid($data['pid']);
			return _failure("Setup composer interrupted. ($eta sec)");
		}
		//*/
		_echo("Setup done. ($eta sec)");

		//finalizing
		_worker_set([
			'expires' => 0,
			'child_cmd' => null,
			'child_pid' => null,
		]);
		$ts = microtime(1);
		_echo('Finalizing composer installation...');
		if (!is_file($phar_file)) return _failure("Installed file not found. ($phar_file)");
		_delete($setup_file);
		_delete($composer_file);
		if (!rename($phar_file, $composer_file)) return _failure("Rename failed. ($phar_file > $composer_file)");
		$eta = microtime(1) - $ts;
		_echo("Installed: $composer_file ($eta sec)", 1, 0);
	});
}

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

//test requirements
function _test_requirements(){
	
	//init vars
	$errors = [];
	$fails = 0;
	$sp = ($is_console = Process::is_console()) ? '  ' : ' ';
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
	_echo("Testing Requirements... (is_console=$is_console)");
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
	$status = !$fails ? 'PASSED' : "FAILED ($fails)";
	_echo('');
	_echo("Test $status");
}