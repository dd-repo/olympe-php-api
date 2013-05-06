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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/user/help\">user</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change</li>
	<li><h2>Description :</h2> changes the password of a user</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the user. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, user_name, username, login, id, user_id, uid)</li>
			<li>pass : The new password of the user. <span class=\"optional\">optional</span>. (alias : password, user_pass, user_password)</li>
			<li>firstname : The first name of the user. <span class=\"optional\">optional</span>. (alias : givenname, first_name, user_firstname, user_givenname, user_first_name, user_given_name)</li>
			<li>lastname : The last name of the user. <span class=\"optional\">optional</span>. (alias : sn, user_lastname, user_sn, user_last_name)</li>
			<li>email : The email of the user. <span class=\"optional\">optional</span>. (alias : mail, address, user_email, user_mail, user_address)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, USER_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'USER_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('name', 'user_name', 'username', 'login', 'user', 'id', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$pass = request::getCheckParam(array(
	'name'=>array('pass', 'password', 'user_password', 'user_pass'),
	'optional'=>true,
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

// =================================
// GET LOCAL USER INFO
// =================================
if( is_numeric($user) )
	$where = "u.user_id=".$user;
else
	$where = "u.user_name = '".security::escape($user)."'";

$sql = "SELECT u.user_id, u.user_name, u.user_ldap FROM users u WHERE {$where}";
$result = $GLOBALS['db']->query($sql);
if( $result == null || $result['user_id'] == null )
	throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

// =================================
// UPDATE REMOTE USER
// =================================
$params = array();
if( $pass !== null )
{
	$params['userPassword'] = base64_encode($pass);
	// update piwik password
	$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.updateUser&userLogin={$result['user_name']}&password={$pass}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
	@file_get_contents($url);
}
if( $firstname !== null )
	$params['givenName'] = $firstname;
if( $lastname !== null )
	$params['sn'] = $lastname;
if( $mail !== null )
	$params['mailForwardingAddress'] = $mail;
asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/users/'.$result['user_name'], 'PUT', $params);

responder::send("OK");

?>