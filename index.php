<?php
$_process = __DIR__ . '/Process.php';
$_funcs = __DIR__ . '/_funcs.php';
$_main = __DIR__ . '/_main.php';
$_html = __DIR__ . '/_html.php';

//handle combine (default name: __ins.php)
$combined = null;
if ((isset($argv) && is_array($argv) && isset($argv[1]) && trim($argv[1]) === 'combine')){
	$combined = 1;
	if (isset($argv[2]) && ($tmp = trim($argv[2]))) $combined = $tmp;
}
elseif (isset($_GET) && is_array($_GET) && array_key_exists('combine', $_GET)){
	$combined = 1;
	if ($tmp = trim($_GET['combine'])) $combined = $tmp;
}
if ($combined){
	if (is_string($combined)){
		if (!preg_match('/\.php$/i', $combined)) $combined .= '.php';
	}
	else $combined = '__ins.php';
	$file = getcwd() . '/' . $combined;
	@unlink($file);
	$_append = function($data) use (&$file){
		$fw = fopen($file, 'a');
		fwrite($fw, $data);
		fclose($fw);
	};
	$_read_write = function($path, $is_php=0) use (&$_append){
		$replaced = 0;
		$fr = fopen($path, 'rb');
		while (!feof($fr)){
			$buffer = fread($fr, 4096 * 2);
			if (strlen($buffer)){
				if (!$replaced && $is_php){
					if (strpos($buffer, '<?php') !== false){
						$buffer = trim(str_replace('<?php', '', $buffer));
						$replaced = 1;
					}
				}
				$_append($buffer);
			}
		}
		fclose($fr);
	};
	$_append("<?php");
	$_append("\n\n\$GLOBALS['__TARGET__'] = __FILE__;");
	$_append("\n\n#================================  _main    =================================\n\n");
	$_read_write($_main, 1);
	$_append("\n\n#================================  Process  =================================\n\n");
	$_read_write($_process, 1);
	$_append("\n\n#================================  _funcs   =================================\n\n");
	$_read_write($_funcs, 1);
	$_append("\n\n#================================  _html    =================================\n?>\n\n");
	$_read_write($_html);
	exit;
}

//imports
$GLOBALS['__TARGET__'] = __FILE__;
require_once $_process;
require_once $_funcs;
require_once $_main;
require_once $_html;