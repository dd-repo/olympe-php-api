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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/site/help\">site</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new site</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>site : The name of the new site. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, site_name)</li>
			<li>pass : The password of the site. <span class=\"required\">required</span>. (alias : password, site_pass, site_password)</li>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created site {'name', 'id'}</li>
	<li><h2>Required grants :</h2> ACCESS, SITE_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'SITE_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$site = request::getCheckParam(array(
	'name'=>array('name', 'site_name', 'site'),
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>50,
	'match'=>"^[a-z0-9\\-]{2,50}$",
	'action'=>true
	));
$pass = request::getCheckParam(array(
	'name'=>array('pass', 'password', 'site_pass', 'site_password'),
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
	
if( is_numeric($site) )
	throw new ApiException("Parameter validation failed", 412, "Parameter site may not be numeric : " . $site);

// =================================
// GET USER DATA
// =================================
$sql = "SELECT user_ldap, user_name FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
$userdata = $GLOBALS['db']->query($sql);
if( $userdata == null || $userdata['user_ldap'] == null )
	throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

// =================================
// CHECK QUOTA
// =================================
grantStore::add('QUOTA_USER_INTERNAL');
request::forward('/quota/user/internal');
checkQuota('SITES', $user);

// =================================
// CHECK IF REMOTE SITE EXISTS
// =================================
try
{
	$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains/'.$site);
	// this should throw a 404 if the user does NOT exist
	throw new ApiException("Site already exists", 412, "Existing remote site : " . $site);
}
catch(Exception $e)
{
	// if this is not the 404 we expect, rethrow it
	if( !($e instanceof ApiException) || !preg_match("/404 Not Found/s", $e.'') )
		throw $e;
}

// =================================
// INSERT REMOTE SITE
// =================================
$params = array('subdomain'=>$site, 'userPassword'=>base64_encode($pass), 'gidNumber'=>$userdata['user_ldap']);
$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains', 'POST', $params);
if( !isset($result['uidNumber']) || !is_numeric($result['uidNumber']) )
	throw new ApiException("Unexpected response from upstream API", 500, "Unexpected API response : ".print_r($result, true));

// =================================
// INSERT PIWIK SITE
// =================================
$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=SitesManager.addSite&siteName={$site}&urls=http://{$site}.{$GLOBALS['CONFIG']['DOMAIN']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
$json = json_decode(@file_get_contents($url), true);
$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.setUserAccess&userLogin={$userdata['user_name']}&access=admin&idSites={$json['value']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
@file_get_contents($url);

// =================================
// SYNC QUOTA
// =================================
syncQuota('SITES', $user);

responder::send(array("name"=>$site, "id"=>$result['uidNumber']));

?>