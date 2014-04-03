<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Modify a news");
$a->addGrant(array('ACCESS', 'MESSAGE_UPDATE'));
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
	'name'=>array('type', 'message_type'),
	'description'=>'The message type.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>1,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('status', 'message_status'),
	'description'=>'The message status.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>1,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('title', 'message_title'),
	'description'=>'The message title.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>150,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('content', 'message'),
	'description'=>'The message content.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>2000,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
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
	$content = $a->getParam('content');
	$type = $a->getParam('type');
	$title = $a->getParam('title');
	$status = $a->getParam('status');
	$user = $a->getParam('user');
	
	// =================================
	// CHECK OWNER
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT m.message_id FROM users u LEFT JOIN messages m ON(m.message_user = u.user_id) WHERE message_id = {$id} AND ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
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
	
	$set = '';
	if( $title !== null )
		$set .= ", message_title = '".security::escape($title)."'";
	if( $content !== null )
		$set .= ", message_content = '".security::escape($content)."'";
	if( $type !== null )
		$set .= ", message_type = '".security::escape($type)."'";
	if( $status !== null )
		$set .= ", message_status = '".security::escape($status)."'";

	$sql = "UPDATE messages SET message_id = message_id {$set} WHERE message_id = {$id}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	responder::send("OK");
});

return $a;

?>