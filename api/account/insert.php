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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/account/help\">account</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new account</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>account : The name of the new account. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, account_name)</li>
			<li>domain : The name of the domain that subdomains belong to. <span class=\"required\">required</span>. (alias : domain_name)</li>
			<li>pass : The password of the account. <span class=\"required\">required</span>. (alias : password, account_pass, account_password)</li>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>firstname : The first name of the account. <span class=\"optional\">optional</span>. (alias : givenname, first_name, account_firstname, account_givenname, account_first_name, account_given_name)</li>
			<li>lastname : The last name of the account. <span class=\"optional\">optional</span>. (alias : sn, account_lastname, account_sn, account_last_name)</li>
			<li>redirection : The redirection email of the account. <span class=\"optional\">optional</span>. (alias : mail, redirect, account_redirect)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created account {'name', 'id'}</li>
	<li><h2>Required grants :</h2> ACCESS, ACCOUNT_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'ACCOUNT_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$account = request::getCheckParam(array(
	'name'=>array('name', 'account_name', 'account'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$domain = request::getCheckParam(array(
	'name'=>array('domain', 'domain_name'),
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$pass = request::getCheckParam(array(
	'name'=>array('pass', 'password', 'account_password', 'account_pass'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::PHRASE|request::SPECIAL
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$firstname = request::getCheckParam(array(
	'name'=>array('firstname', 'givenname', 'first_name', 'account_firstname', 'account_givenname', 'account_first_name', 'account_given_name'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$lastname = request::getCheckParam(array(
	'name'=>array('lastname', 'sn', 'account_lastname', 'account_sn', 'account_last_name'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$redirection = request::getCheckParam(array(
	'name'=>array('redirection', 'mail', 'redirect', 'account_redirect'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$"
	));

if( is_numeric($account) )
	throw new ApiException("Parameter validation failed", 412, "Parameter account may not be numeric : " . $account);

// =================================
// CHECK IF REMOTE ACCOUNT EXISTS
// =================================
try
{
	$result = asapi::send('/'.$domain.'/users/'.$account);
	// this should throw a 404 if the user does NOT exist
	throw new ApiException("Account already exists", 412, "Existing remote account : " . $account);
}
catch(Exception $e)
{
	// if this is not the 404 we expect, rethrow it
	if( !($e instanceof ApiException) || !preg_match("/404 Not Found/s", $e.'') )
		throw $e;
}

// =================================
// GET USER DATA
// =================================
$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
$userdata = $GLOBALS['db']->query($sql);
if( $userdata == null || $userdata['user_ldap'] == null )
	throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

// =================================
// INSERT REMOTE ACCOUNT
// =================================
$params = array('user'=>$account, 'gidNumber'=>$userdata['user_ldap'], 'userPassword'=>base64_encode($pass));
if( $firstname !== null )
	$params['givenName'] = $firstname;
if( $lastname !== null )
	$params['sn'] = $lastname;
if( $mail !== null )
	$params['mailForwardingAddress'] = $redirection;
$result = asapi::send('/'.$domain.'/users', 'POST', $params);
if( !isset($result['uidNumber']) || !is_numeric($result['uidNumber']) )
	throw new ApiException("Unexpected response from upstream API", 500, "Unexpected API response : ".print_r($result, true));

responder::send(array("name"=>$account, "id"=>$result['uidNumber']));

?>