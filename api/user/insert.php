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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/user/help\">user</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new user</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name of the new user. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, user_name, username, login)</li>
			<li>pass : The password of the user. <span class=\"required\">required</span>. (alias : password, user_pass, user_password)</li>
			<li>firstname : The first name of the user. <span class=\"optional\">optional</span>. (alias : givenname, first_name, user_firstname, user_givenname, user_first_name, user_given_name)</li>
			<li>lastname : The last name of the user. <span class=\"optional\">optional</span>. (alias : sn, user_lastname, user_sn, user_last_name)</li>
			<li>email : The email of the user. <span class=\"optional\">optional</span>. (alias : mail, address, user_email, user_mail, user_address)</li>
			<li>ip : IP address of the user. <span class=\"optional\">optional</span>. </li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created user {'name', 'id', 'user_ldap'}</li>
	<li><h2>Required grants :</h2> ACCESS, USER_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'USER_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('name', 'user_name', 'username', 'login', 'user'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$pass = request::getCheckParam(array(
	'name'=>array('pass', 'password', 'user_password', 'user_pass'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::PHRASE|request::SPECIAL
	));
$firstname = request::getCheckParam(array(
	'name'=>array('firstname', 'givenname', 'first_name', 'user_firstname', 'user_givenname', 'user_first_name', 'user_given_name'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$lastname = request::getCheckParam(array(
	'name'=>array('lastname', 'sn', 'user_lastname', 'user_sn', 'user_last_name'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$mail = request::getCheckParam(array(
	'name'=>array('mail', 'email', 'address', 'user_email', 'user_mail', 'user_address'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$"
	));
$ip = request::getCheckParam(array(
	'name'=>array('ip'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::ALL
	));
	
if( is_numeric($user) )
	throw new ApiException("Parameter validation failed", 412, "Parameter user may not be numeric : " . $user);

// =================================
// CHECK IF LOCAL USER EXISTS
// =================================
$sql = "SELECT user_id FROM users WHERE user_name = '".security::escape($user)."'";
$result = $GLOBALS['db']->query($sql);

if( $result !== null || $result['user_id'] !== null )
	throw new ApiException("User already exists", 412, "Existing local user : " . $user);

// =================================
// CHECK IF REMOTE USER EXISTS
// =================================
try
{
	$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/users/'.$user);
	// this should throw a 404 if the user does NOT exist
	throw new ApiException("User already exists", 412, "Existing remote user : " . $user);
}
catch(Exception $e)
{
	// if this is not the 404 we expect, rethrow it
	if( !($e instanceof ApiException) || !preg_match("/404 Not Found/s", $e.'') )
		throw $e;
}

// =================================
// INSERT REMOTE USER
// =================================
$params = array('user'=>$user, 'userPassword'=>base64_encode($pass));
if( $firstname !== null )
	$params['givenName'] = $firstname;
if( $lastname !== null )
	$params['sn'] = $lastname;
if( $mail !== null )
	$params['mailForwardingAddress'] = $mail;

if( $ip )
	$params['gecos'] = security::escape($ip);
else
	$params['gecos'] = $_SERVER["HTTP_X_REAL_IP"];
	
$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/users', 'POST', $params);
if( !isset($result['uidNumber']) || !is_numeric($result['uidNumber']) )
	throw new ApiException("Unexpected response from upstream API", 500, "Unexpected API response : ".print_r($result, true));

// =================================
// INSERT LOCAL USER
// =================================
$sql = "INSERT INTO users (user_name, user_ldap,user_date) VALUES ('".security::escape($user)."', {$result['uidNumber']}, ".time().")";
$GLOBALS['db']->query($sql, mysql::NO_ROW);
$uid = $GLOBALS['db']->last_id();

// =================================
// INSERT PIWIK USER
// =================================
$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.addUser&userLogin={$user}&password={$pass}&email={$user}@{$GLOBALS['CONFIG']['DOMAIN']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
@file_get_contents($url);

responder::send(array("name"=>$user, "id"=>$uid));

?>