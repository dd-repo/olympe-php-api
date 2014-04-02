<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Modify a user");
$a->addGrant(array('ACCESS', 'USER_UPDATE'));
$a->setReturn("OK");
$a->addParam(array(
	'name'=>array('name', 'user_name', 'username', 'login', 'user', 'id', 'user_id', 'uid'),
	'description'=>'The name or id of the user',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('pass', 'password', 'user_password', 'user_pass'),
	'description'=>'The password of the user.',
	'optional'=>true,
	'minlength'=>6,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('firstname', 'givenname', 'first_name', 'user_firstname', 'user_givenname', 'user_first_name', 'user_given_name'),
	'description'=>'The first name of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('lastname', 'sn', 'user_lastname', 'user_sn', 'user_last_name'),
	'description'=>'The last name of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('mail', 'email', 'user_email', 'user_mail'),
	'description'=>'The email of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$"
	));
$a->addParam(array(
	'name'=>array('address', 'postal', 'postal_address', 'user_address'),
	'description'=>'The postal address of the user (JSON encoded).',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>500,
	'match'=>request::PHRASE|request::SPECIAL|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('lastname', 'sn', 'user_lastname', 'user_sn', 'user_last_name'),
	'description'=>'The last name of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('language', 'lang'),
	'description'=>'The user language.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>2,
	'match'=>request::UPPER
	));
$a->addParam(array(
	'name'=>array('code', 'user_code'),
	'description'=>'The user code.',
	'optional'=>true,
	'minlength'=>8,
	'maxlength'=>8,
	'match'=>request::UPPER|request::LOWER|request::NUMBER|request::PUNCT
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
	$user = $a->getParam('user');
	$pass = $a->getParam('pass');
	$firstname = $a->getParam('firstname');
	$lastname = $a->getParam('lastname');
	$mail = $a->getParam('mail');
	$address = $a->getParam('address');
	$language = $a->getParam('language');
	$code = $a->getParam('code');
	
        // =================================
        // PROCESS PARAMETERS
        // =================================
        if( $valid == '1' || $valid == 'yes' || $valid == 'true' || $valid === true || $valid === 1 )
			$valid = true;
        else
			$valid = false;
	
	// =================================
	// GET LOCAL USER INFO
	// =================================
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";

	$sql = "SELECT u.user_id, u.user_code, u.user_name, u.user_ldap, u.user_status FROM users u WHERE {$where}";
	$result = $GLOBALS['db']->query($sql);
	if( $result == null || $result['user_id'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	
	// =================================
	// GET REMOTE USER INFO
	// =================================		
	$dn = ldap::buildDN(ldap::USER, $GLOBALS['CONFIG']['DOMAIN'], $result['user_name']);
	$data = $GLOBALS['ldap']->read($dn);
	
	// =================================
	// UPDATE REMOTE USER
	// =================================
	$params = array();

	if( $pass !== null )
		$params['userPassword'] = $pass;
	if( $firstname !== null )
		$params['givenName'] = $firstname;
	if( $lastname !== null )
		$params['sn'] = $lastname;
	if( $mail !== null )
		$params['mailForwardingAddress'] = $mail;
	if( $address !== null )
		$params['postalAddress'] = $address;	
	if( $language !== null )
		$params['gecos'] = $language;	
		
	$GLOBALS['ldap']->replace($dn, $params);
		
	try
	{
		if( $pass !== null )
		{	
			// =================================
			// UPDATE PIWIK USER
			// =================================
			$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.updateUser&userLogin={$result['user_name']}&password={$pass}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
			@file_get_contents($url);
		}
	}
	catch(Exception $e)
	{
	}
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('user/update', $a->getParams(), $result['user_id']);
	
	responder::send("OK");
});

return $a;

?>