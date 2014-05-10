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
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
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
	// CHECK OWNER
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT m.message_id FROM users u LEFT JOIN messages m ON(m.message_user = u.user_id) WHERE message_id = {$id}'	AND ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$result = $GLOBALS['db']->query($sql);
		
		if( $result == null || $result['message_id'] == null )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the message {$message}");
	}
	else
	{
		$sql = "SELECT m.message_id FROM messages m	WHERE message_id = {$id}";
		$result = $GLOBALS['db']->query($sql);
		
		if( $result == null || $result['message_id'] == null )
			throw new ApiException("Forbidden", 403, "Message {$message} does not exist");
	}
	
	// =================================
	// DELETE MESSAHE
	// =================================
	$sql = "DELETE FROM messages WHERE message_id = '{$id}'";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	responder::send("OK");
});

return $a;

?>