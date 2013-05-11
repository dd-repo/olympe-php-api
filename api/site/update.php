<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Modify a site");
$a->addGrant(array('ACCESS', 'SITE_UPDATE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('site', 'name', 'id', 'site_id'),
	'description'=>'The name or id of the site to modify.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('arecord'),
	'description'=>'The A Record of the site.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>20,
	'match'=>request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('cnamerecord'),
	'description'=>'The CNAME Record of the site.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>20,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('pass', 'password', 'account_password', 'account_pass'),
	'description'=>'The password of the account.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
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
	$arecord = $a->getParam('arecord');
	$cnamerecord = $a->getParam('cnamerecord');
	$pass = $a->getParam('pass');
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
	// UPDATE REMOTE SITE
	// =================================
	if( $pass !== null )
		$params['userPassword'] = $pass;

	if( $arecord !== null )
	{
		$params = array('aRecord'=>$arecord);
		$GLOBALS['ldap']->replace($dn, array('cNAMERecord'=>$result['cNAMERecord']), ldap::DELETE);
	}
	else if( $cnamerecord !== null )
	{
		$params = array('cNAMERecord'=>$cnamerecord);
		$GLOBALS['ldap']->replace($dn, array('aRecord'=>$result['aRecord']), ldap::DELETE);
	}

	$GLOBALS['ldap']->replace($dn, $params);

	responder::send("OK");
});

return $a;

?>