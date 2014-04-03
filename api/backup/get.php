<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('get', 'download', 'new'));
$a->setDescription("Get the link of the backup");
$a->addGrant(array('ACCESS', 'SITE_SELECT'));
$a->setReturn(array(array(
	'url'=>'the URL to the backup'
	)));
$a->addParam(array(
	'name'=>array('site', 'site_id', 'id'),
	'description'=>'The name or id of the target site.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('database', 'name', 'database_name'),
	'description'=>'The name of the database to remove.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::UPPER|request::NUMBER|request::PUNCT
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
	$site = $a->getParam('site');
	$database = $a->getParam('database');
	$user = $a->getParam('user');
		
	// =================================
	// GET USER DATA
	// =================================
	if( $user !== null )
	{ 
		$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	}
	
	if( ($site === null && $database === null) || ($site !== null && $database !== null) )
			throw new ApiException("Bad request", 500, "Please fill one of the params database or site but one at the time");
	
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
	$sql = "SELECT d.database_name, d.database_type, d.database_desc, d.database_server, u.user_id, u.user_name 
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
		$sql = "SELECT COUNT(database_name) as count FROM `databases` WHERE database_server = 'sql.olympe.in'";
		$sql1 = $GLOBALS['db']->query($sql);
		$sql = "SELECT COUNT(database_name) as count FROM `databases` WHERE database_server = 'sql2.olympe.in'";
		$sql2 = $GLOBALS['db']->query($sql);
		
		$d['name'] = $r['database_name'];
		$d['type'] = $r['database_type'];
		$d['desc'] = $r['database_desc'];
		$d['server'] = $r['database_server'];
		$d['size'] = $storage['storage_size'];
		$d['user'] = array('id'=>$r['user_id'], 'name'=>$r['user_name']);
		$d['stats'] = array('sql.olympe.in'=>$sql1['count'], 'sql1.olympe.in'=>$sql1['count'], 'sql2.olympe.in'=>$sql2['count']);
		
		$databases[] = $d;
	}

	responder::send($databases);
});

return $a;

?>