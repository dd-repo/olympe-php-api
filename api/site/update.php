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
	'maxlength'=>150,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('pass', 'password', 'site_password', 'site_pass'),
	'description'=>'The password of the site.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('directory'),
	'description'=>'Whether or not to include this site in the global directory?.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('title', 'site_title'),
	'description'=>'The title of the site.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>200,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('category', 'site_category'),
	'description'=>'The category of the site.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>2,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('description', 'site_description'),
	'description'=>'The description of the site.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>500,
	'match'=>request::ALL
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
	$directory = $a->getParam('directory');
	$title = $a->getParam('title');
	$category = $a->getParam('category');
	$description = $a->getParam('description');
	$user = $a->getParam('user');
	
	if( $directory == '1' || $directory == 'yes' || $directory == 'true' || $directory === true || $directory === 1 ) $directory = true;
	else if( $directory == '0' || $directory == 'no' || $directory == 'false' || $directory === false || $directory === 0 ) $directory = false;
	
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
	$set = '';
	$insert = '';
	if( $title !== null )
		$set .= ", site_title = '".security::escape($title)."'";
	if( $description !== null )
		$set .= ", site_description = '".security::escape($description)."'";
	if( $category !== null )
		$set .= ", site_category = '".security::escape($category)."'";
	if( $directory === true )
		$set .= ", site_status = 1";
	else if( $directory === false )
		$set .= ", site_status = 0";

	if( strlen($set) > 0 )
	{
		$sql = "SELECT site_id FROM directory WHERE site_ldap_id = {$result['uidNumber']}";
		$test = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
		
		if( $test['site_id'] )
			$sql = "UPDATE directory SET site_id = site_id {$set} WHERE site_ldap_id = {$result['uidNumber']}";
		else
		{
			$sql = "INSERT INTO directory (site_ldap_id, site_title, site_description, site_url, site_category) 
			VALUES ('{$result['uidNumber']}',  '".security::escape($title)."',  '".security::escape($description)."', '{$result['associatedDomain']}', '".security::escape($category)."')"; 
		}
		
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
	}
	
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