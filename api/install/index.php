<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$i = new index();
$i->addAlias(array('install', 'setup'));
$i->setDescription("Install on remote directory.");
$i->addEntry('insert', array('insert', 'create', 'add'));
$i->addGrant(array('ACCESS', 'INSTALL_INSERT'));

return $i;

?>