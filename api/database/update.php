<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Edit a database");
$a->addGrant(array('ACCESS', 'DATABASE_UPDATE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('database', 'name', 'database_name'),
	'description'=>'The name of the database to remove.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::UPPER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('pass', 'password'),
	'description'=>'The password of the account.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('desc', 'description'),
	'description'=>'The description of the database.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL
	));
$a->addParam(array(
	'name'=>array('user', 'name', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
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
	$pass = $a->getParam('pass');
	$desc = $a->getParam('desc');
	$user = $a->getParam('user');
	
	// =================================
	// GET LOCAL DATABASE INFO
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT d.database_type
				FROM users u
				LEFT JOIN `databases` d ON(d.database_user = u.user_id)
				WHERE database_name = '".security::escape($database)."'
				AND ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	}
	else
	{
		$sql = "SELECT d.database_type
				FROM `databases` d
				WHERE database_name = '".security::escape($database)."'";
	}
	
	$result = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
	if( $result == null || $result['database_type'] == null )
		throw new ApiException("Forbidden", 403, "Database {$database} not found (for user {$user} ?)");

	// =================================
	// UPDATE LOCAL DATABASE
	// =================================
	if( $desc != null )
	{
		$sql = "UPDATE `databases` SET database_desc = '".security::escape($desc)."'";
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
	}
	
	// =================================
	// UPDATE REMOTE DATABASE
	// =================================
	switch( $result['database_type'] )
	{
		case 'mysql':
			$link = mysql_connect($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'] . ':' . $GLOBALS['CONFIG']['MYSQL_ROOT_PORT'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD']);
			mysql_query("SET PASSWORD FOR '{$database}'@'%' = PASSWORD('".security::escape($pass)."')", $link);
			mysql_close($link);
		break;	
	}

	responder::send("OK");
});

return $a;

?>