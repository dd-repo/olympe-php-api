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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/app/help\">app</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change</li>
	<li><h2>Description :</h2> update an app</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>app : The name or id of the app to updatre. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, app_name, id, app_id)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, APP_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'APP_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$app = request::getCheckParam(array(
	'name'=>array('name', 'app_name', 'app', 'id', 'app_id'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::UPPER|request::LOWER|request::NUMBER,
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
// GET REMOTE APP INFO
// =================================
if( is_numeric($app) )
	$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps', 'GET', array('uidNumber'=>$app));
else
	$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps/'.$app, 'GET');

if( $result == null || $result['uidNumber'] == null )
	throw new ApiException("Unknown app", 412, "Unknown app : {$app}");

// =================================
// CHECK OWNER
// =================================
if( $user !== null )
{
	$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	if( $result['gidNumber'] != $userdata['user_ldap'] )
		throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the site {$site} ({$result['gidNumber']})");
}

// =================================
// UPDATE REMOTE app
// =================================
//$params = array('userPassword'=>base64_encode($pass));
//asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps/'.$result['uid'], 'PUT', $params);

responder::send("OK");

?>