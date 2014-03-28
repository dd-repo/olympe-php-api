<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('setrate'));
$a->setDescription("Rate a site");
$a->addGrant(array('ACCESS', 'SITE_UPDATE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('site', 'name', 'id', 'site_id'),
	'description'=>'The name or id of the site to rate.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('rate', 'rating', 'score'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>1,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
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
	$rate = $a->getParam('rate');
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
	// PREPARE INSERT
	// =================================	
	if( !is_numeric($user) )
		$user = "(SELECT user_id FROM users where user_name = '" . security::escape($user) . "')";
	
	$sql = "SELECT rating_value FROM user_rating WHERE site_ldap_id = {$result['uidNumber']} AND user_id = {$user}";
	$check = $GLOBALS['db']->query($sql, mysql::ONE_ROW);

	if( $check['rating_value'] )
		$sql = "UPDATE user_rating SET rating_value = {$rate} WHERE site_ldap_id = {$result['uidNumber']} AND user_id = {$user}";
	else
		$sql = "INSERT INTO user_rating (user_id, site_ldap_id, rating_value) VALUES ({$user}, {$result['uidNumber']}, {$rate})";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	responder::send("OK");
});

return $a;

?>