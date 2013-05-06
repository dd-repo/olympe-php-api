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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/domain/help\">domain</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new domain</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>domain : The name of the new domain. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, domain_name)</li>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>site : The name of the site linked to the domain. <span class=\"required\">required</span>. (alias : site_name)</li>
			<li>directory : The directory where the domain follows. <span class=\"optional\">optional</span>. (alias : dir)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created domain {'domain', 'id'}</li>
	<li><h2>Required grants :</h2> ACCESS, DOMAIN_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'DOMAIN_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$domain = request::getCheckParam(array(
	'name'=>array('name', 'domain_name', 'domain'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>"[a-z0-9_\-]{1,200}(\.[a-z0-9_\-]{1,5}){1,4}|[0-9]+",
	'action'=>true
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$site = request::getCheckParam(array(
	'name'=>array('site_name', 'site'),
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	));
$dir = request::getCheckParam(array(
	'name'=>array('directory', 'dir'),
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	));
	
if( is_numeric($domain) )
	throw new ApiException("Parameter validation failed", 412, "Parameter domain may not be numeric : " . $domain);

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
checkQuota('DOMAINS', $user);

// =================================
// CHECK IF REMOTE DOMAIN EXISTS
// =================================
try
{
	$result = asapi::send('/domains/'.$domain);
	// this should throw a 404 if the domain does NOT exist
	throw new ApiException("Domain already exists", 412, "Existing remote domain : " . $domain);
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
// INSERT REMOTE DOMAIN
// =================================
$home = '/dns/in/olympe/' . $site;
if( count($dir) > 0 )
	$home .= '/' . $dir;

$params = array('domain'=>$domain, 'ownerGid'=>$site_info['uidNumber'], 'gidNumber'=>$userdata['user_ldap'], 'homeDirectory' => $home);
$result = asapi::send('/domains', 'POST', $params);
if( !isset($result['uidNumber']) || !is_numeric($result['uidNumber']) )
	throw new ApiException("Unexpected response from upstream API", 500, "Unexpected API response : ".print_r($result, true));

// =================================
// SYNC QUOTA
// =================================
syncQuota('DOMAINS', $user);

responder::send(array("domain"=>$domain, "id"=>$result['uidNumber']));

?>