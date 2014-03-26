<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('del', 'remove', 'destroy'));
$a->setDescription("Removes a message");
$a->addGrant(array('ACCESS', 'MESSAGE_DELETE'));
$a->setReturn("OK");
$a->addParam(array(
	'name'=>array('message_id', 'id'),
	'description'=>'The id of the message',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));

$a->setExecute(function() use ($a)
{
	// =================================
	// CHECK AUTH
	// =================================
	$a->checkAuth();

	// =================================
	// GET PARAMETERS
	// =================================
	$id = $a->getParam('id');

	// =================================
	// DELETE MESSAHE
	// =================================
	$sql = "DELETE FROM messages WHERE message_id = '{$id}'";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	responder::send("OK");
});

return $a;

?>