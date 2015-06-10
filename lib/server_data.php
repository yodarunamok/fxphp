<?php
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

function fmdate( $cD, $cM, $cY ) {
	return substr( '00' . $cM, -2 ) . '/' . substr( '00' . $cD, -2 ) . '/' . $cY;
}

?>
