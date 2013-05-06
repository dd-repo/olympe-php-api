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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/account/help\">account</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> del, remove, destroy</li>
	<li><h2>Description :</h2> removes an account</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>account : The name or id of the account to remove. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, id, account_id, uid)</li>
			<li>domain : The name of the domain that account belong to. <span class=\"required\">required</span>. (alias : domain_name)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, ACCOUNT_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'ACCOUNT_DELETE'));

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
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));

// =================================
// CHECK OWNER
// =================================
if( $user !== null )
{
	$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

	if( is_numeric($account) )
		$result = asapi::send('/'.$domain.'/users', 'GET', array('uidNumber'=>$account));
	else
		$result = asapi::send('/'.$domain.'/users/'.$account, 'GET');
		
	if( $result['gidNumber'] != $userdata['user_ldap'] )
		throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the account {$account} ({$result['gidNumber']})");
}

// =================================
// DELETE REMOTE ACCOUNT
// =================================
if( is_numeric($account) )
{
	$params = array('uidNumber' => $account);
	asapi::send('/'.$domain.'/users/', 'DELETE', $params);
}
else
	asapi::send('/'.$domain.'/users/'.$account, 'DELETE');

responder::send("OK");

?>