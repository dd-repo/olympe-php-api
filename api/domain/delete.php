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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/domain/help\">domain</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> del, remove, destroy</li>
	<li><h2>Description :</h2> removes a domain</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>site : The name or id of the domain to remove. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : domain_name, id, domain_id, uid)</li>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, DOMAIN_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'DOMAIN_DELETE'));

// =================================
// GET PARAMETERS
// =================================
$domain = request::getCheckParam(array(
	'name'=>array('domain', 'domain_name', 'id', 'domain_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>"[a-z0-9_\-]{1,200}(\.[a-z0-9_\-]{1,5}){1,4}|[0-9]+",
	'action'=>true
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));

// =================================
// CHECK OWNER
// =================================
if( $user !== null )
{
	$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

	if( is_numeric($domain) )
		$result = asapi::send('/domains/', 'GET', array('uidNumber'=>$domain));
	else
		$result = asapi::send('/domains/'.$domain, 'GET');
		
	if( $result['gidNumber'] != $userdata['user_ldap'] )
		throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the domain {$domain}");
}

// =================================
// DELETE REMOTE DOMAIN
// =================================
if( is_numeric($domain) )
{
	$params = array('uidNumber' => $domain);
	asapi::send('/domains/', 'DELETE', $params);
}
else
	asapi::send('/domains/'.$domain, 'DELETE');

// =================================
// SYNC QUOTA
// =================================
grantStore::add('QUOTA_USER_INTERNAL');
request::forward('/quota/user/internal');
syncQuota('DOMAINS', $userdata['user_id']);

responder::send("OK");

?>