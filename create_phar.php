<?php

/**
 * Create pterm.phar
 * - REF: https://blog.programster.org/creating-phar-files#:~:text=Simply%20create%20a%20folder%20for,for%20your%20application's%20source%20code.&text=Copy%20all%20of%20your%20PHP,and%20modify%20the%20script%20below).
 * - php.ini -> phar.readonly = Off
 */

try
{
	//cleanup
	$pharFile = 'pterm.phar';
	if (file_exists($pharFile)) unlink($pharFile);
	if (file_exists($pharFile . '.gz')) unlink($pharFile . '.gz');

	//create phar
	$phar = new Phar($pharFile);

	//start buffering. Mandatory to modify stub to add shebang
	$phar -> startBuffering();

	//Create the default stub from main.php entrypoint
	$defaultStub = $phar -> createDefaultStub('index.php');

	//Add the rest of the apps files
	$phar -> buildFromDirectory(__DIR__ . '/src');

	//Customize the stub to add the shebang
	//FIX: $stub = "#!/usr/bin/env php \n" . $defaultStub;
	$stub = $defaultStub;

	//Add the stub
	$phar -> setStub($stub);
	$phar -> stopBuffering();

	//plus - compressing it into gzip  
	$phar -> compressFiles(Phar::GZ);

	//Make the file executable
	chmod(__DIR__ . "/{$pharFile}", 0770);
	echo "$pharFile successfully created" . PHP_EOL;
}
catch (Exception $e){
	echo $e -> getMessage();
}
exit;