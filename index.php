<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

define('BASE_DIR', dirname(__FILE__));
define('BASE_URL', str_replace($_SERVER['DOCUMENT_ROOT'], '', BASE_DIR));
define('BASE_URL_RALBUM', BASE_URL . '/ralbum');

require 'app/vendor/autoload.php';

$app = new \Ralbum\App();
$app->run();