<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a messages");
$a->addGrant(array('ACCESS', 'MESSAGE_SELECT'));
$a->setReturn(array(array(
	'title'=>'the title of the message', 
	'content'=>'the message content',
	'user'=>'the message user',
	'date'=>'the message date'
	)));
$a->addParam(array(
	'name'=>array('id', 'message', 'message_id'),
	'description'=>'The id of the message',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('parent', 'parent_id'),
	'description'=>'The id of parent of the message',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('type', 'message_type'),
	'description'=>'The type of the message',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>1,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('limit'),
	'description'=>'Limit the result',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('unanswered'),
	'description'=>'Search only unanswered.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('topic'),
	'description'=>'Search only topics.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>false
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
	$parent = $a->getParam('parent');
	$type = $a->getParam('type');
	$unanswered = $a->getParam('unanswered');
	$topic = $a->getParam('topic');
	$limit = $a->getParam('limit');
	$user = $a->getParam('user');
	
	if( $unanswered == '1' || $unanswered == 'yes' || $unanswered == 'true' || $unanswered === true || $unanswered === 1 )
		$unanswered = true;
	else
		$unanswered = false;
		
	if( $topic == '1' || $topic == 'yes' || $topic == 'true' || $topic === true || $topic === 1 )
		$topic = true;
	else
		$topic = false;
	
	if( $limit === null )
		$limit = 5;
	
	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	$where = '';
	if( $id !== null )
		$where .= " AND message_id = '".security::escape($id)."'";
	if( $user !== null )
	{
		if( is_numeric($user) )
			$where .= " AND u.user_id = " . $user;
		else
			$where .= " AND u.user_name = '".security::escape($user)."'";
	}
	if( $id !== null )
		$where .= " AND message_id = '".security::escape($id)."'";
	if( $unanswered === true )
		$where .= " AND message_status = 1";
	if( $topic === true )
		$where .= " AND message_parent = NULL";
	
	// =================================
	// SELECT RECORDS
	// =================================
	$sql = "SELECT m.message_title, m.message_content, m.message_date, m.message_parent, m.message_id, m.message_type, m.message_status, u.user_name, u.user_id
	FROM messages m LEFT JOIN users u ON(u.user_id = m.message_user)
	WHERE true {$where} ORDER BY m.message_id DESC LIMIT 0,{$limit}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	// =================================
	// FORMAT RESULT
	// =================================
	$messages = array();
	foreach( $result as $r )
	{
		$m['id'] = $r['message_id'];
		$m['title'] = $r['message_title'];
		$m['content'] = $r['message_content'];
		$m['parent'] = $r['message_parent'];
		$m['date'] = $r['message_date'];
		$m['type'] = $r['message_type'];
		$m['status'] = $r['message_status'];
		$m['user'] = array('id'=>$r['user_id'], 'name'=>$r['user_name']);
		
		$messages[] = $m;
	}

	responder::send($messages);
});

return $a;

?>