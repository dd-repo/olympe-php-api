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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/database/help\">database</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> del, remove, destroy</li>
	<li><h2>Description :</h2> removes a database</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>database : The name of the database to remove. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, database_name)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, DATABASE_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'DATABASE_DELETE'));

// =================================
// GET PARAMETERS
// =================================
$database = request::getCheckParam(array(
	'name'=>array('database', 'name', 'database_name'),
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::UPPER|request::NUMBER,
	'action'=>true
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));

// =================================
// CHECK OWNER
// =================================
if( $user !== null )
{
	$sql = "SELECT d.database_type, d.database_user
			FROM users u
			LEFT JOIN `databases` d ON(d.database_user = u.user_id)
			WHERE database_name = '".security::escape($database)."'
			AND ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$result = $GLOBALS['db']->query($sql);
	
	if( $result == null || $result['database_type'] == null )
		throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the database {$database}");
}
else
{
	$sql = "SELECT d.database_type, d.database_user
			FROM `databases` d
			WHERE database_name = '".security::escape($database)."'";
	$result = $GLOBALS['db']->query($sql);
	
	if( $result == null || $result['database_type'] == null )
		throw new ApiException("Forbidden", 403, "Database {$database} does not exist");
}

// =================================
// DELETE REMOTE DATABASE
// =================================
$params = array('type' => $result['database_type']);
asapi::send('/databases/'.$database, 'DELETE', $params);

// =================================
// DELETE LOCAL DATABASE
// =================================
$sql = "DELETE FROM `databases` WHERE database_name = '".security::escape($database)."'";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

// =================================
// SYNC QUOTA
// =================================
grantStore::add('QUOTA_USER_INTERNAL');
request::forward('/quota/user/internal');
syncQuota('DATABASES', $result['database_user']);

responder::send("OK");

?>