<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a database");
$a->addGrant(array('ACCESS', 'DATABASE_SELECT'));
$a->setReturn(array(array(
	'name'=>'the database name', 
	'type'=>'the id of the database', 
	'desc'=>'the complete database description', 
	'user'=>array(array(
		'id'=>'the user id', 
		'name'=>'the username')
	),
	)));
$a->addParam(array(
	'name'=>array('database', 'name', 'database_name'),
	'description'=>'The name of the database to remove.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::UPPER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('count'),
	'description'=>'Return only the number of entries.',
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
	$database = $a->getParam('database');
	$count = $a->getParam('count');
	$user = $a->getParam('user');
	
	if( $count == '1' || $count == 'yes' || $count == 'true' || $count === true || $count === 1 )
		$count = true;
	else
		$count = false;
		
	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	$where = '';
	if( $database !== null )
		$where .= " AND d.database_name = '".security::escape($database)."'";
	if( $user !== null )
	{
		if( is_numeric($user) )
			$where .= " AND u.user_id = " . $user;
		else
			$where .= " AND u.user_name = '".security::escape($user)."'";
	}
	
	// =================================
	// SELECT RECORDS
	// =================================
	$sql = "SELECT d.database_name, d.database_type, d.database_desc, u.user_id, u.user_name 
			FROM `databases` d
			LEFT JOIN users u ON(u.user_id = d.database_user)
			WHERE true {$where}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	if( $count === true )
		responder::send(array('count'=>count($result)));
		
	// =================================
	// FORMAT RESULT
	// =================================
	$databases = array();
	foreach( $result as $r )
	{
		$sql = "SELECT storage_size FROM storages WHERE storage_path = '/databases/{$r['database_name']}'";
		$storage = $GLOBALS['db']->query($sql);
		
		$d['name'] = $r['database_name'];
		$d['type'] = $r['database_type'];
		$d['desc'] = $r['database_desc'];
		$d['size'] = $storage['storage_size'];
		$d['user'] = array('id'=>$r['user_id'], 'name'=>$r['user_name']);
		
		$databases[] = $d;		
	}

	responder::send($databases);
});

return $a;

?>