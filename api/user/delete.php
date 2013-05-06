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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/user/help\">user</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> del, remove, destroy</li>
	<li><h2>Description :</h2> removes a user</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the user to remove. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, user_name, username, login, id, user_id, uid)</li>
			<li>recursive : [0 : no recursive (default) | 1 : recursive]. <span class=\"optional\">optional</span>.</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, USER_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'USER_DELETE'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('name', 'user_name', 'username', 'login', 'user', 'id', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$recursive = request::getCheckParam(array(
	'name'=>array('recursive'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>1,
	'match'=>request::NUMBER
	));
	
// =================================
// GET LOCAL USER INFO
// =================================
if( is_numeric($user) )
	$where = "u.user_id=".$user;
else
	$where = "u.user_name = '".security::escape($user)."'";

$sql = "SELECT u.user_id, u.user_name, u.user_ldap FROM users u WHERE {$where}";
$result = $GLOBALS['db']->query($sql);
if( $result == null || $result['user_id'] == null )
	throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

// =================================
// RECURSIVE
// =================================
if( $recursive == 1 )
{
	// =================================
	// DATABASES
	// =================================
	$sql = "SELECT database_name, database_type FROM `databases` WHERE database_user = {$result['user_id']}";
	$databases = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	foreach( $databases as $d )
	{
		$params = array('type' => $d['database_type']);
		asapi::send('/databases/'.$d['database_name'], 'DELETE', $params);
		
		$sql = "DELETE FROM `databases` WHERE database_name = '{$d['database_name']}'";
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
	}

	// =================================
	// DOMAINS
	// =================================
	$params = array('gidNumber' => $result['user_ldap']);
	$domains = asapi::send('/domains', 'GET', $params);
	
	foreach( $domains as $d )
	{
		asapi::send('/domains/'.$d['associatedDomain'], 'DELETE');
	}
	
	// =================================
	// SITES
	// =================================
	$params['gidNumber'] = $result['user_ldap'];
	$sites = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains', 'GET', $params);
	foreach( $sites as $s )
	{
		asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains/'.$s['uid'], 'DELETE');
	}
}
	
// =================================
// DELETE REMOTE USER
// =================================
asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/users/'.$result['user_name'], 'DELETE');

// =================================
// DELETE LOCAL USER
// =================================
$sql = "DELETE FROM users WHERE user_id={$result['user_id']}";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

// =================================
// DELETE PIWIK USER
// =================================
$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.deleteUser&userLogin={$user}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
@file_get_contents($url);

responder::send("OK");

?>