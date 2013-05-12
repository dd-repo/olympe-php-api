<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new site");
$a->addGrant(array('ACCESS', 'SITE_INSERT'));
$a->setReturn(array(array(
	'id'=>'the id of the site', 
	'name'=>'the site name'
	)));
$a->addParam(array(
	'name'=>array('site', 'name'),
	'description'=>'The new site name.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('pass', 'password', 'site_pass', 'site_password'),
	'description'=>'The new site password.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::PHRASE|request::SPECIAL
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>false
	));

$a->setExecute(function() use ($a)
{
	// =================================
	// CHECK AUTH
	// =================================
	$a->checkAuth();

	// =================================
	// GET PARAMETERS
	// =================================
	$site = $a->getParam('site');
	$pass = $a->getParam('pass');
	$user = $a->getParam('user');
	
	if( is_numeric($subdomain) )
		throw new ApiException("Parameter validation failed", 412, "Parameter site may not be numeric : " . $site);

	// =================================
	// GET USER DATA
	// =================================
	$sql = "SELECT user_ldap, user_id, user_name FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
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
	// GET REMOTE USER DN
	// =================================	
	$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
	
	// =================================
	// CHECK IF REMOTE SITE EXISTS
	// =================================
	try
	{
		$dn = ldap::buildDN(ldap::SUBDOMAIN, $GLOBALS['CONFIG']['DOMAIN'], $site);
		$result = $GLOBALS['ldap']->read($dn);
		
		// this should throw a 404 if the user does NOT exist
		throw new ApiException("Site already exists", 412, "Existing remote site : " . $site);
	}
	catch(Exception $e)
	{
		// if this is not the 404 we expect, rethrow it
		if( !($e instanceof ApiException) || !preg_match("/Entry not found/s", $e.'') )
			throw $e;
	}

	// =================================
	// INSERT REMOTE SITE
	// =================================
	$dn = ldap::buildDN(ldap::SUBDOMAIN, $GLOBALS['CONFIG']['DOMAIN'], $site);
	$parts = explode('.', $site);
	$params = array('dn' => $dn, 'subdomain' => $site, 'userPassword' => $pass, 'uid' => $parts[0], 'domain' => $GLOBALS['CONFIG']['DOMAIN'], 'owner' => $user_dn);
	
	$handler = new subdomain();
	$data = $handler->build($params);
	
	$result = $GLOBALS['ldap']->create($dn, $data);

	// =================================
	// UPDATE REMOTE USER
	// =================================
	$mod['member'] = $dn;
	$GLOBALS['ldap']->replace($user_dn, $mod, ldap::ADD);
	
	// =================================
	// GET REMOTE USER INFO
	// =================================
	$user_info = $GLOBALS['ldap']->read($user_dn);
	
	// =================================
	// INSERT PIWIK SITE
	// =================================
	$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=SitesManager.addSite&siteName={$site}&urls=http://{$site}.{$GLOBALS['CONFIG']['DOMAIN']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
	$json = json_decode(@file_get_contents($url), true);
	$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.setUserAccess&userLogin={$userdata['user_name']}&access=admin&idSites={$json['value']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
	@file_get_contents($url);
	
	// =================================
	// POST-CREATE SYSTEM ACTIONS
	// =================================
	$data['user_info'] = $user_info;
	$GLOBALS['system']->create(system::SUBDOMAIN, $data);
	
	// =================================
	// SYNC QUOTA
	// =================================
	syncQuota('SITES', $user);

	responder::send(array("name"=>$site, "id"=>$result['uidNumber']));
});

return $a;

?>