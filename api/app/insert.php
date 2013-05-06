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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/app/help\">app</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new app</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>app : The name of the new app. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, app_name)</li>
			<li>site : The name of the site linked to the app. <span class=\"required\">required</span>. (alias : name, site_name)</li>
			<li>database : The database of the app. <span class=\"required\">required</span>. (alias : db)</li>
			<li>password : The database password. <span class=\"required\">required</span>. (alias : pass)</li>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>dir : The directory of the app. <span class=\"required\">required</span>. (alias : directory)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created app {'name', 'id'}</li>
	<li><h2>Required grants :</h2> ACCESS, APP_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'APP_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$app = request::getCheckParam(array(
	'name'=>array('name', 'app_name', 'app'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::UPPER|request::LOWER|request::NUMBER,
	'action'=>true
	));
$site = request::getCheckParam(array(
	'name'=>array('name', 'site_name', 'site'),
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$database = request::getCheckParam(array(
	'name'=>array('database', 'db'),
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::UPPER|request::NUMBER,
	));
$pass = request::getCheckParam(array(
	'name'=>array('pass', 'password'),
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
$dir = request::getCheckParam(array(
	'name'=>array('dir', 'directory'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>200,
	'match'=>request::PHRASE|request::SPECIAL
	));
	
if( is_numeric($app) )
	throw new ApiException("Parameter validation failed", 412, "Parameter app may not be numeric : " . $app);

// =================================
// GET USER DATA
// =================================
$sql = "SELECT user_ldap, user_name FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
$userdata = $GLOBALS['db']->query($sql);
if( $userdata == null || $userdata['user_ldap'] == null )
	throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

// =================================
// CHECK IF REMOTE APP EXISTS
// =================================
try
{
	$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps/'.$app);
	// this should throw a 404 if the user does NOT exist
	throw new ApiException("Site already exists", 412, "Existing remote app : " . $app);
}
catch(Exception $e)
{
	// if this is not the 404 we expect, rethrow it
	if( !($e instanceof ApiException) || !preg_match("/404 Not Found/s", $e.'') )
		throw $e;
}

// =================================
// CHECK IF REMOTE SITE EXISTS
// =================================
$site_info = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains/'.$site, 'GET');

if( !$site_info['uidNumber'] )
	throw new ApiException("Site does not exist", 412, "Site not found : " . $site);
	
// =================================
// INSERT REMOTE APP
// =================================
$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
$name = '';
for( $u = 1; $u <= 8; $u++ )
{
	$number = strlen($chars);
	$number = mt_rand(0,($number-1));
	$name .= $chars[$number];
}

$command = "/usr/local/bin/install-app {$app} /dns/in/olympe/{$site} mysql sql.olympe.in {$database} {$database} {$pass} {$site_info['uidNumber']} {$dir} {$site}";
$params = array('app'=>$app.'-'.$name, 'ownerGid'=>$site_info['uidNumber'], 'gidNumber'=>$userdata['user_ldap'], 'description'=>$database, 'gecos'=>$site, 'command'=>$command, 'homeDirectory'=>'/dns/in/olympe/'.$site.'/'.$dir);

$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps', 'POST', $params);
if( !isset($result['uidNumber']) || !is_numeric($result['uidNumber']) )
	throw new ApiException("Unexpected response from upstream API", 500, "Unexpected API response : ".print_r($result, true));

responder::send(array("name"=>$app, "id"=>$result['uidNumber']));

?>