<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Create a new message");
$a->addGrant(array('ACCESS', 'MESSAGE_INSERT'));
$a->setReturn(array(array(
	'id'=>'the message id'
)));

$a->addParam(array(
	'name'=>array('content', 'message'),
	'description'=>'The message content.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>2000,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('type', 'message_type'),
	'description'=>'The message type.',
	'optional'=>false,
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
	'name'=>array('parent', 'message_parent'),
	'description'=>'The message parent.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>50,
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
	$content = $a->getParam('content');
	$type = $a->getParam('type');
	$title = $a->getParam('title');
	$parent = $a->getParam('parent');
	$user = $a->getParam('user');
	
	// =================================
	// INSERT MESSAGE
	// =================================
	$sql = "INSERT INTO `messages` (message_parent, message_title, message_content, message_date, message_user, message_type) VALUE ('".($parent!==null?security::escape($parent):"1")."', '".security::escape($title)."', '".security::escape($content)."', UNIX_TIMESTAMP(), '".security::escape($user)."',  '".security::escape($type)."')";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	$id = $GLOBALS['db']->last_id();
	
	responder::send(array("id"=>$id));
});

return $a;

?>