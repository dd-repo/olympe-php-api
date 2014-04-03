<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$i = new index();
$i->addAlias(array('backup', 'backups'));
$i->setDescription("A backup is a dump of files or databases.");
$i->addEntry('get', array('get', 'download', 'new'));

return $i;

?>