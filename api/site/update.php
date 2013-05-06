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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/site/help\">site</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change</li>
	<li><h2>Description :</h2> changes the password of a site</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>site : The name or id of the site. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, site_name, id, uid, site_id)</li>
			<li>pass : The new password of the site. <span class=\"optional\">optional</span>. (alias : password, site_pass, site_password)</li>
			<li>valid : [0 : pending (default) | 1 : valid | 2 unvalid]. <span class=\"optional\">optional</span>. (alias : validation)</li>
			<li>explain : Why the validation status?. <span class=\"optional\">optional</span>. (alias : reason)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, SITE_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'SITE_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$site = request::getCheckParam(array(
	'name'=>array('name', 'site_name', 'site', 'id', 'uid', 'site_id'),
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$pass = request::getCheckParam(array(
	'name'=>array('pass', 'password', 'site_pass', 'site_password'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::PHRASE|request::SPECIAL
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$valid = request::getCheckParam(array(
	'name'=>array('valid', 'validation'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>1,
	'match'=>request::NUMBER
	));
$explain = request::getCheckParam(array(
	'name'=>array('explain', 'reason'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>25000,
	'match'=>request::ALL
	));

// =================================
// GET REMOTE SITE INFO
// =================================
if( is_numeric($site) )
	$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains', 'GET', array('uidNumber'=>$site));
else
	$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains/'.$site, 'GET');

if( $result == null || $result['uidNumber'] == null )
	throw new ApiException("Unknown site", 412, "Unknown site : {$site}");

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
// UPDATE REMOTE SITE
// =================================
$params = array();

if( $pass != null )
	$params['userPassword'] = base64_encode($pass);
if( $valid != null )
	$params['gecos'] = $valid;
if( $explain != null )
	$params['description'] = $explain;
	
asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains/'.$result['uid'], 'PUT', $params);

responder::send("OK");

?>