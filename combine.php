<?php

/**
 * Merge pterm source to single file [default: __pterm.php].
 */

//source files
#$_process = __DIR__ . '/Process.dev.php';
$_process = __DIR__ . '/Process.php';
$_funcs = __DIR__ . '/_funcs.php';
$_main = __DIR__ . '/_main.php';
$_html = __DIR__ . '/_html.php';

//combined basename
$combined = '__pterm.php';
if (isset($argv) && is_array($argv) && isset($argv[1]) && ($tmp = trim($argv[1])) && ($tmp = trim(basename($tmp)))) $combined = $tmp;
if (!preg_match('/\.php$/i', $combined)) $combined .= '.php';

//output file overwrite
$file = __DIR__ . '/' . $combined;
echo "- Merging source...\n";
@unlink($file);

//helper - append
$_append = function($data) use (&$file){
	$fw = fopen($file, 'a');
	fwrite($fw, $data);
	fclose($fw);
};

//helper - read/write
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

//helper - source divider
$_div = function($val){
	$val = str_pad('  !' . trim($val), 12);
	return "\n\n#================================$val=================================\n";
};

//merge code
$_append("<?php");
$_append("\n\n/**");
$_append("\n * =============================================================================");
$_append("\n * NCMS P-Term ~ By @xthukuh (https://github.com/xthukuh)");
$_append("\n * =============================================================================");
$_append("\n */");
$_append("\n\n\n\$GLOBALS['__TARGET__'] = __FILE__;");

#!_main
$_append($_div('_main') . "\n");
$_read_write($_main, 1);

#!Process
$_append($_div('Process') . "\n");
$_read_write($_process, 1);

#!_funcs
$_append($_div('_funcs') . "\n");
$_read_write($_funcs, 1);

#!_html
$_append($_div('_html') . "?>\n\n");
$_read_write($_html);

//done
echo '- Done: ' . realpath($file) . "\n";
exit;