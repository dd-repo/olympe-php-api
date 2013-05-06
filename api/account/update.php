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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/account/help\">account</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change</li>
	<li><h2>Description :</h2>modify an account</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>account : The name or id of the account to remove. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, id, account_id, uid)</li>
			<li>domain : The name of the domain that accounts belong to. <span class=\"required\">required</span>. (alias : domain_name)</li>
			<li>pass : The new password of the account. <span class=\"optional\">optional</span>. (alias : password, account_pass, account_password)</li>
			<li>firstname : The new first name of the account. <span class=\"optional\">optional</span>. (alias : givenname, first_name, account_firstname, account_givenname, account_first_name, account_given_name)</li>
			<li>lastname : The new last name of the account. <span class=\"optional\">optional</span>. (alias : sn, account_lastname, account_sn, account_last_name)</li>
			<li>redirection : The new redirection email of the account. <span class=\"optional\">optional</span>. (alias : mail, redirect, account_redirect)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, ACCOUNT_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'ACCOUNT_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$account = request::getCheckParam(array(
	'name'=>array('account', 'name', 'id', 'account_id', 'uid'),
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
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::PHRASE|request::SPECIAL
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
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
	
// =================================
// GET REMOTE ACCOUNT INFO
// =================================
if( is_numeric($account) )
	$result = asapi::send('/'.$domain.'/users', 'GET', array('uidNumber'=>$account));
else
	$result = asapi::send('/'.$domain.'/users/'.$account, 'GET');

if( $result == null || $result['uidNumber'] == null )
	throw new ApiException("Unknown account", 412, "Unknown account : {$account}");

// =================================
// CHECK OWNER
// =================================
if( $user !== null )
{
	$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	if( $userdata['user_ldap'] != $result['gidNumber'] )
		throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the account {$account} ({$result['gidNumber']})");
}

// =================================
// UPDATE REMOTE ACCOUNT
// =================================
$params = array();
if( $pass !== null )
	$params['userPassword'] = base64_encode($pass);
if( $firstname !== null )
	$params['givenName'] = $firstname;
if( $lastname !== null )
	$params['sn'] = $lastname;
if( $redirection !== null )
	$params['mailForwardingAddress'] = $redirection;
else
	asapi::send('/'.$domain.'/users/'.$result['uid'], 'PUT', array('mailForwardingAddress'=>$result['mailForwardingAddress'], 'mode'=>'delete'));

asapi::send('/'.$domain.'/users/'.$result['uid'], 'PUT', $params);

responder::send("OK");

?>