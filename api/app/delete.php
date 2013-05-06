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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/app/help\">app</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> del, remove, destroy</li>
	<li><h2>Description :</h2> removes a app</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>app : The name or id of the app to remove. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, app_name, id, app_id)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, APP_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'APP_DELETE'));

// =================================
// GET PARAMETERS
// =================================
$app = request::getCheckParam(array(
	'name'=>array('name', 'app_name', 'app', 'id', 'app_id'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>70,
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
// CHECK OWNER
// =================================
if( $user !== null )
{
	$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

	if( is_numeric($app) )
		$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps', 'GET', array('uidNumber'=>$app));
	else
		$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps/'.$app, 'GET');
		
	if( $result['gidNumber'] != $userdata['user_ldap'] )
		throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the app {$app}");
}
else
{
	if( is_numeric($app) )
		$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps', 'GET', array('uidNumber'=>$app));
	else
		$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps/'.$app, 'GET');	
}

// =================================
// DELETE REMOTE APP
// =================================
$command = "/usr/local/bin/uninstall-app {$result['homeDirectory']} mysql sql.olympe.in {$result['description']} {$result['description']} true";
if( is_numeric($app) )
{
	$params = array('uidNumber' => $app, 'command'=>$command);
	asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps/', 'DELETE', $params);
}
else
	asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps/'.$app, 'DELETE', array('command'=>$command));

responder::send("OK");

?>