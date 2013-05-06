<?php

function __autoload($class_name)
{
	require_once $GLOBALS['CONFIG']['CLASSPATH'] . $class_name . '.inc';
}

error_reporting($GLOBALS['CONFIG']['THROW_ERRORS']);
function exception_handler(Exception $e)
{
	restore_error_handler();
	restore_exception_handler();
	error_reporting(0);
	responder::send($e);
	exit;
}
set_exception_handler('exception_handler');

function error_handler($errno, $errstr, $errfile, $errline)
{
	if( ($errno & error_reporting()) > 0 )
		throw new ErrorException($errstr, 500, $errno, $errfile, $errline);
	else
		return false;
}
set_error_handler('error_handler');

// avoid warnings about the default timezone
date_default_timezone_set('Europe/Paris');

define('CORE_LOADED', true);

?>
