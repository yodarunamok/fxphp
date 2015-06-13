<?php
// in production, this should be set to NULL; more details: http://www.php.net/manual/en/function.error-reporting.php
error_reporting(E_ALL);

if (! defined('DEBUG')) {
    define('DEBUG', false);         // set to true to turn debugging on
}

if (! defined('DEBUG_FUZZY')) {
    define('DEBUG_FUZZY', false);   // set to true to activate the fuzzy debugger
}

$serverIP = '127.0.0.1';

$webCompanionPort = 80;
$dataSourceType = 'FMPro7';

$webUN = 'user';
$webPW = 'pass';

$scheme = 'http';
