<?php
function _repo_download($options, &$error=null){
	$error = null;

	//options
	$options = is_array($options) ? $options : [];
	if (!(isset($options['user']) && ($user = trim($options['user'])))){
		$error = 'Undefined options -> user.';
		return false;
	}
	if (!(isset($options['token']) && ($token = trim($options['token'])))){
		$error = 'Undefined options -> token.';
		return false;
	}
	if (!(isset($options['repo']) && ($repo = trim($options['repo'])))){
		$error = 'Undefined options -> repo.';
		return false;
	}
	if (!(isset($options['saveAs']) && ($file = trim($options['saveAs'])))) $file = $repo . '-latest.zip';
	if (!(isset($options['branch']) && ($branch = trim($options['branch'])))) $branch = 'master';
	if (!(isset($options['progress']) && is_callable($progress = $options['progress']))) $progress = null;

	//vars
	$endpoint = 'https://api.github.com/repos/' . $user . '/' . $repo . '/zipball/' . $branch;
	$header = [
		'Authorization: token ' . $token,
		'User-Agent: NCMSAPP',
	];

	//init
	$max_time = (int) ini_get('max_execution_time');
	set_time_limit(0);
	$fw = null;
	$ch = null;
	$_close = function() use (&$fw, &$ch){
		if (is_resource($ch)){
			curl_close($ch);
			$ch = null;
		}
		if (is_resource($fw)){
			fclose($fw);
			$fw = null;
		}
	};
	$_done = function($rm=0) use (&$max_time, &$file, &$_close){
		$_close();
		if ($rm && is_file($file)) unlink($file);
		set_time_limit($max_time);
	};
	$_size_now;
	$_size_prev;
	$_size_total;
	$_progress = function($ch=null, $d_total=null, $d_now=null, $u_total=null, $u_now=null) use (&$progress, &$_size_prev, &$_size_now, &$_size_total){
		if (!($done = is_null($ch))){
			$_size_total = $d_total;
			$_size_now = $d_now;
			if ($_size_prev && $_size_prev === $d_now) return; //ignore unchanged
			$_size_prev = $d_now;
		}
		if (!($size = $_size_now)) return; //ignore no bytes
		if (!is_callable($progress)) return; //ignore no callback
		call_user_func_array($progress, [$size, $_size_total, $done]);
	};
	try {
		
		//curl request
		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 2); #
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		//progress
		curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, $_progress);
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);
		curl_setopt($ch, CURLOPT_BUFFERSIZE, 4096);
		
		//output
		if (!is_resource($fw = fopen($file, 'w+'))) throw new Exception('File fopen failure. (' . $file . ')');
		curl_setopt($ch, CURLOPT_FILE, $fw);

		//exec
		$res = curl_exec($ch);
		if ($n = curl_errno($ch)) throw new Exception('Curl Error [' . $n . ']: ' . curl_error($ch));
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($http_code === 200 && $_size_now) $_progress();
		_print("- http_code: $http_code\n"); //DEBUG:
		
		//result
		$_done();
		return $res;
	}
	catch (Exception $e){
		$_done(1);
		$error = $e -> getMessage();
		return false;
	}
}