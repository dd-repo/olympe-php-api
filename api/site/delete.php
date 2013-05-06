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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/site/help\">site</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> del, remove, destroy</li>
	<li><h2>Description :</h2> removes a site</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>site : The name or id of the site to remove. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, site_name, site, id, site_id, uid)</li>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, SITE_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'SITE_DELETE'));

// =================================
// GET PARAMETERS
// =================================
$site = request::getCheckParam(array(
	'name'=>array('name', 'site_name', 'site', 'id', 'site_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>false,
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

	if( is_numeric($site) )
		$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains', 'GET', array('uidNumber'=>$site));
	else
		$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains/'.$site, 'GET');
		
	if( $result['gidNumber'] != $userdata['user_ldap'] )
		throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the site {$site}");
}

// =================================
// DELETE REMOTE SITE
// =================================
if( is_numeric($site) )
{
	$params = array('uidNumber' => $site);
	asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains/', 'DELETE', $params);
}
else
	asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains/'.$site, 'DELETE');

// =================================
// SYNC QUOTA
// =================================
grantStore::add('QUOTA_USER_INTERNAL');
request::forward('/quota/user/internal');
syncQuota('SITES', $userdata['user_id']);

// =================================
// DELETE PIWIK SITE
// =================================
$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=SitesManager.getSitesIdFromSiteUrl&url=http://{$result['associatedDomain']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
$json = json_decode(@file_get_contents($url), true);
$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=SitesManager.deleteSite&idSite={$json[0]['idsite']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
@file_get_contents($url);

responder::send("OK");

?>