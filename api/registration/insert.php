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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/registration/help\">registration</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> register, signup, subscribe, join, create, add</li>
	<li><h2>Description :</h2> creates a new registration request</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name of the target user. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, user_name, username, login)</li>
			<li>email : The email of the user. <span class=\"required\">required</span>. (alias : mail, address, user_email, user_mail, user_address)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created registration code {'code'}</li>
	<li><h2>Required grants :</h2> ACCESS, REGISTRATION_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'REGISTRATION_INSERT'));

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
$mail = request::getCheckParam(array(
	'name'=>array('mail', 'email', 'address', 'user_email', 'user_mail', 'user_address'),
	'optional'=>false,
	'minlength'=>6,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$"
	));

if( is_numeric($user) )
	throw new ApiException("Parameter validation failed", 412, "Parameter user may not be numeric : " . $user);

// =================================
// CLEANUP TOO OLD REQUESTS
// =================================
// > 10 days old
$sql = "DELETE FROM register WHERE register_date < (UNIX_TIMESTAMP() - 864000)";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

// =================================
// CHECK IF PENDING REQUEST EXISTS
// =================================
$sql = "SELECT register_id FROM register 
		WHERE register_user = '".security::escape($user)."'
		OR register_email = '".security::escape($mail)."'";
$result = $GLOBALS['db']->query($sql);

if( $result !== null || $result['user_id'] !== null )
	throw new ApiException("Pending request already exists", 412, "Existing pending request for : {$user} : {$mail}");

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
// INSERT PENDING REQUEST
// =================================
$code = md5($user.$email.time());
$sql = "INSERT INTO register (register_user, register_email, register_code, register_date)
		VALUES ('".security::escape($user)."', '".security::escape($mail)."', '{$code}', UNIX_TIMESTAMP())";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send(array("code"=>$code));

?>