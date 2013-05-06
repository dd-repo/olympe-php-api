<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$help = request::getAction(false, false);
if( $help == 'help' || $help == 'doc' )
{
	$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/database/help\">database</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new database</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>type : Type of the database. <span class=\"required\">required</span>. (alias : database_type)</li>
			<li>pass : The password of the database. <span class=\"required\">required</span>. (alias : password, database_pass, database_password)</li>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>desc : The description of the database. <span class=\"optional\">optional</span>. (alias : description)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created site {'name', 'id'}</li>
	<li><h2>Required grants :</h2> ACCESS, DATABASE_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'DATABASE_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$type = request::getCheckParam(array(
	'name'=>array('type', 'database_type', 'site'),
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>10,
	'match'=>request::LOWER
	));
$pass = request::getCheckParam(array(
	'name'=>array('pass', 'password', 'database_pass', 'database_password'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::PHRASE|request::SPECIAL
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$desc = request::getCheckParam(array(
	'name'=>array('desc', 'description'),
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::PHRASE|request::SPECIAL
	));

// =================================
// GET USER DATA
// =================================
$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
$userdata = $GLOBALS['db']->query($sql);
if( $userdata == null || $userdata['user_ldap'] == null )
	throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	
// =================================
// CHECK QUOTA
// =================================
grantStore::add('QUOTA_USER_INTERNAL');
request::forward('/quota/user/internal');
checkQuota('DATABASES', $user);

// =================================
// INSERT REMOTE DATABASE
// =================================
while(true)
{
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$base = '';
	for( $u = 1; $u <= 8; $u++ )
	{
		$number = strlen($chars);
		$number = mt_rand(0,($number-1));
		$base .= $chars[$number];
	}
	
	// check if that database name already exists
	$sql = "SELECT database_name FROM `databases` WHERE database_name='{$base}'";
	$exists = $GLOBALS['db']->query($sql);
	if( $exists == null || $exists['database_name'] == null )
		break;
}

$params = array('database'=>$base, 'userPassword'=>base64_encode($pass), 'type'=>$type);
$result = asapi::send('/databases/', 'POST', $params);

// =================================
// INSERT LOCAL DATABASE
// =================================
$sql = "INSERT INTO `databases` (database_name, database_type, database_user, database_desc) VALUE ('{$base}', '{$type}', {$userdata['user_id']}, '".security::escape($desc)."')";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

// =================================
// SYNC QUOTA
// =================================
syncQuota('DATABASES', $user);

responder::send(array("name"=>$base));

?>