<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$i = new index();
$i->addAlias(array('database', 'databases'));
$i->setDescription("A database is a datastore apps are able to use.");
$i->addEntry('insert', array('insert', 'create', 'add'));
$i->addEntry('select', array('select', 'list', 'view', 'search'));
$i->addEntry('update', array('update', 'change', 'rename', 'modify'));
$i->addEntry('delete', array('delete', 'remove', 'del', 'destroy'));
$i->addEntry('/quota/database/index', array('quota', 'quotas'));

return $i;

?>