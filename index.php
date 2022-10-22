<?php

/**
 * =============================================================================
 * NCMS P-Term ~ By @xthukuh (https://github.com/xthukuh)
 * =============================================================================
 */

if (!defined('P_TERM')){
	if (!(($tmp = trim(Phar::running(false))) && is_file($tmp))) $tmp = __FILE__;
	define('P_TERM', $tmp);
}
require_once __DIR__ . '/src/index.php';