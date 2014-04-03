<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('del', 'remove', 'destroy'));
$a->setDescription("Removes a site");
$a->addGrant(array('ACCESS', 'SITE_DELETE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('site', 'name', 'id', 'site_id'),
	'description'=>'The name or id of the site to remove.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
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
	$user = $a->getParam('user');

	// =================================
	// GET REMOTE INFO
	// =================================
	if( is_numeric($site) )
		$dn = $GLOBALS['ldap']->getDNfromUID($site);
	else
		$dn = ldap::buildDN(ldap::SUBDOMAIN, $GLOBALS['CONFIG']['DOMAIN'], $site);
	
	$result = $GLOBALS['ldap']->read($dn);
		
	if( $result == null || $result['uidNumber'] == null )
		throw new ApiException("Unknown site", 412, "Unknown site : {$site}");
	
	// =================================
	// CHECK OWNER
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

		// =================================
		// GET REMOTE USER DN
		// =================================	
		$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);

		if( is_array($result['owner']) )
			$result['owner'] = $result['owner'][0];
			
		if( $result['owner'] != $user_dn )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the site {$site}");
	}

	// =================================
	// DELETE REMOTE SITE
	// =================================
	$GLOBALS['ldap']->delete($dn);

	// =================================
	// DELETE DIRECTORY ENTRY
	// =================================
	$sql = "DELETE FROM directory WHERE site_ldap_id = {$result['uidNumber']}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	// =================================
	// DELETE PIWIK SITE
	// =================================
	$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=SitesManager.getSitesIdFromSiteUrl&url=http://{$result['associatedDomain']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
	$json = json_decode(@file_get_contents($url), true);
	$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=SitesManager.deleteSite&idSite={$json[0]['idsite']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
	@file_get_contents($url);
	
	// =================================
	// POST-DELETE SYSTEM ACTIONS
	// =================================
	$command = "rm -Rf {$result['homeDirectory']}";
	$GLOBALS['gearman']->sendAsync($command);
	
	// =================================
	// SYNC QUOTA
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	syncQuota('SITES', $userdata['user_id']);

	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('site/delete', $a->getParams(), $userdata['user_id']);
	
	responder::send("OK");
});

return $a;

?>