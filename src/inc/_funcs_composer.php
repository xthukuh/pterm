<?php

//putenv - COMPOSER_HOME
function _putenv_composer_home(){
	$dir = _option('cwd') . '/vendor/bin/composer';
	if (!is_dir($dir) && !@mkdir($dir, 0775, 1)) return _failure("Create COMPOSER_HOME dir. ($dir)");
	if (!($COMPOSER_HOME = realpath($dir))) return _failure("Invalid COMPOSER_HOME dir realpath. ($dir)");
	putenv('COMPOSER_HOME=' . $COMPOSER_HOME);
	return $COMPOSER_HOME;
}

//install composer
function _install_composer(){
	$_restore = Process::no_limit();
	try {
		
		//vars
		$cwd = _option('cwd');
		$sig_file = $cwd . '/composer.sig';
		$setup_file = $cwd . '/composer-setup.php';
		$phar_file = $cwd . '/composer.phar';
		$composer_file = _option('composer_file');
		
		//install/update
		_echo(sprintf('%s composer...', is_file($composer_file) ? 'Updating' : 'Installing'));

		//fetch copy
		$_copy = function ($source, $dest){
			$tmp = basename($dest);
			_echo("Copy: $source -> $tmp");
			if (copy($source, $dest)) return true;
			throw new Exception("Copy failed ($source -> $dest)");
		};
		
		//get signature
		_echo("\nGet setup signature...");
		$_copy('https://composer.github.io/installer.sig', $sig_file);
		$sig = trim(_read($sig_file));
		_delete($sig_file); //delete read
		_echo("Signature: $sig");
	
		//get setup (verify hash signature)
		_echo("\nGet composer setup (verify hash)...");
		$_copy('https://getcomposer.org/installer', $setup_file);
		if (hash_file('sha384', $setup_file) !== $sig){
			_delete($setup_file); //delete corrupt
			throw new Exception('Corrupt installer - deleted');
		}
		_echo('Installer verified!');
		
		//install composer
		_echo("\nInstall composer...");
		_delete($phar_file); //delete existing

		//install - foreground process
		_putenv_composer_home();
		$cmd = _command('php composer-setup.php', 0);
		$proc = new Process($cmd);
		if (!$proc -> open()) throw new Exception($proc -> error);
		_echo(sprintf('[pid=%s, mypid=%s] %s', $proc -> pid, _option('mypid'), $cmd));
		$proc -> close_pipe(0);
		$proc -> output(1);
		$proc -> close();
		
		//check installation
		if (!(is_file($phar_file) && ($phar_file = realpath($phar_file)))) throw new Exception("Installed file not found. ($phar_file)");
		_delete($setup_file); //delete setup successful
		
		//rename installed
		$tmp = basename($composer_file);
		_echo("Rename: $phar_file -> $tmp");
		_delete($composer_file); //delete existing
		if (!rename($phar_file, $composer_file)) throw new Exception("Rename failed. ($phar_file -> $composer_file)");
		
		//done
		$_restore();
		_echo('Installation complete.', 1, 0);
	}
	catch (Exception $e){
		$_restore();
		return _failure($e -> getMessage());
	}
}