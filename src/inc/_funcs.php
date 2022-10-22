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
	if (is_integer($exit_status)) exit($exit_status);
}

//failure
function _failure($error){
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

//cache data
function _cache_data($data){
	if (!(
		is_array($data)
		&& isset($data[$key = 'cmd'])
		&& ($cmd = trim($data[$key]))
		&& isset($data[$key = 'pid'])
		&& ($pid = Process::pid($data[$key]))
	)) return false;
	return [
		'cmd' => $cmd,
		'pid' => $pid,
		'mypid' => _option('mypid'),
	];
}

//cache set
function _cache_set($data){
	if (!($data = _cache_data($data))) return _failure('Invalid cache data.');
	_write(_option('cache_file'), $data);
	return $data;
}

//cache get - cleanup (not running)
function _cache_get($is_running=0, $kill=0){
	if (is_file($path = _option('cache_file')) && ($data = _cache_data(_read($path, 1)))){
		if (!$is_running && !$kill) return $data; //default
		if ($pid = Process::exists($data['pid'], $err)){
			if ($kill){
				if (($res = Process::kill($pid, $error)) === false) return _failure($error);
				if ($res === 1){
					_echo('');
					_echo(sprintf('- killed [pid: %s, mypid=%s] %s', $pid, $data['mypid'], $data['cmd']));
					usleep(200 * 1000); //delay 200ms
				}
			}
			elseif ($is_running) return $data; //running
		}
		elseif ($pid === false) return _failure('Cache get process exists error: ' . $err);
	}
	if ($is_running < 0) return; //no cleanup
	_delete(_option('cache_file'));
	_delete(_option('log_file'));
}

//output buffer
function _output($data=null, $is_running=0){

	//vars
	$is_cache = !!($data = _cache_data($data));
	if (!$is_cache && !($data = _cache_get($is_running))) return;
	$cmd = $data['cmd'];
	$pid = $data['pid'];
	$mypid = $data['mypid'];
	if (!$is_cache) _echo("[pid=$pid, mypid=$mypid] $cmd");
	$_done = function($check_running=0) use (&$pid){
		if ($check_running && ($_pid = Process::exists($pid))) return $_pid;
	};
	
	//read stdout (log_file)
	if (is_file($path = _option('log_file'))){
		$res = Process::read_file($path, $pid, $_seek=0, $_print=true, $_buffer_cb=null, $error, $abort);
		if ($res === false){
			_echo("Buffer Error: $error");
			return $_done(1);
		}
		return $_done($abort);
	}
	
	//poll running
	if (!($pid = $_done(1))) return;
	$abort = 0;
	$_restore = Process::no_limit();
	$_poll_echo = function() use (&$pid){
		_echo("- poll running pid: $pid");
	};
	$_poll_echo();
	$res = Process::poll_exists($pid, false, function() use (&$abort, &$_poll_echo){
		if (connection_aborted()){
			$abort = 2;
			return true;
		}
		$_poll_echo();
	}, $_sleep_ms=1000, $_timeout=null, $error);
	$_restore();
	if ($res === false){
		_echo("Poll Error: $error");
		return $_done(1);
	}
	return $_done($abort);
}

//command line
function _command($cmd, $stdout=0, $php_self=0){
	static $php;
	if (!(is_string($cmd) && ($cmd = trim($cmd)))) return _failure('Empty command line.');
	
	//set php
	if (!$php){
		if (!($tmp = Process::find_php())) return _failure('Failed to get PHP executable path. Set it manually using $GLOBALS["__PHP_EXE__"]');
		$php = strpos($tmp, ' ') !== false ? '"' . $tmp . "'" : $tmp;
	}

	//php self
	if ($php_self) $cmd = $php . ' ' . _option('target_arg') . ' ' . $cmd;
	
	//parse cmd - check stdout, stderr
	if (strpos($cmd, '>') !== false){
		$parse = Process::parse($cmd);
		if (isset($parse['stdout']) && trim($parse['stdout'])) return _failure("command stdout pipe not supported. ($cmd)");
		if (isset($parse['stderr']) && trim($parse['stderr'])) return _failure("command stderr pipe not supported. ($cmd)");
	}

	//normalize
	if (stripos($cmd, 'php') === 0) $cmd = preg_replace('/^php(\s+)?/i', "$php\$1", $cmd);
	elseif (stripos($cmd, 'composer') === 0) $cmd = preg_replace('/^composer(\s+)?/i', "$php composer\$1", $cmd);

	//stdout log
	if ($stdout) $cmd .= ($stdout === 2 ? ' >> ' : ' > ') . _option('stdout');
	$cmd .= ' 2>&1';
	
	//result
	return $cmd;
}

//run background process
function _run_bg($cmd, $php_self=0){
	if ($pid = _output(null, 1)) return _failure("Resumed process is still running. (pid=$pid - cancel manually)");
	if (
		stripos($cmd, 'composer') !== false
		&& $cmd !== 'install-composer-worker'
		&& is_file(_option('composer_file'))
	) _putenv_composer_home();
	$_cmd = $cmd;
	$cmd = _command($cmd, 1, $php_self);
	$pid = Process::run_bg($cmd, $error);
	if (!$pid) return _failure("Process Error: $error");
	return _output(_cache_set([
		'cmd' => $_cmd,
		'pid' => $pid
	]));
}


