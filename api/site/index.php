<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$i = new index();
$i->addAlias(array('site', 'sites'));
$i->setDescription("A site is a websiste space.");
$i->addEntry('insert', array('insert', 'create', 'add'));
$i->addEntry('select', array('select', 'list', 'view', 'search'));
$i->addEntry('update', array('update', 'change', 'rename', 'modify'));
$i->addEntry('delete', array('delete', 'remove', 'del', 'destroy'));
$i->addEntry('response', array('response', 'responsetime', 'time', 'delay'));
$i->addEntry('setrate', array('setrate'));
$i->addEntry('getrate', array('getrate'));

return $i;

?>