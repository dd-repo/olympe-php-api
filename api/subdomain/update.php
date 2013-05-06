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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/subdomain/help\">subdomain</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change</li>
	<li><h2>Description :</h2> modify a subdomain</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>subdomain : The name or id of the subdomain. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : subdomain_id, id, uid)</li>
			<li>domain : The name of the domain that subdomains belong to. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : domain_name)</li>
			<li>arecord : The A Record of the subdomain. <span class=\"optional\">optional</span>. (alias : arecord)</li>
			<li>cnamerecord : The CNAME Record of the subdomain. <span class=\"optional\">optional</span>. (alias : cnamerecord)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, SUBDOMAIN_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'SUBDOMAIN_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$subdomain = request::getCheckParam(array(
	'name'=>array('subdomain', 'subdomain_id', 'id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>"[a-z0-9_\\-]{2,50}",
	'action'=>true
	));
$domain = request::getCheckParam(array(
	'name'=>array('domain', 'domain_name'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>"[a-z0-9_\-]{1,200}(\.[a-z0-9_\-]{1,5}){1,4}|[0-9]+",
	'action'=>true
	));
$arecord = request::getCheckParam(array(
	'name'=>array('arecord'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>20,
	'match'=>request::NUMBER|request::PUNCT
	));
$cnamerecord = request::getCheckParam(array(
	'name'=>array('cnamerecord'),
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>200,
	'match'=>request::ALPHANUM|request::PUNCT,
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
	
// =================================
// GET REMOTE SUBDOMAIN INFO
// =================================
if( is_numeric($subdomain) )
	$result = asapi::send('/'.$domain.'/subdomains', 'GET', array('uidNumber'=>$subdomain));
else
	$result = asapi::send('/'.$domain.'/subdomains/'.$subdomain, 'GET');

if( $result == null || $result['uidNumber'] == null )
	throw new ApiException("Unknown subdomain", 412, "Unknown subdomain : {$site}");

// =================================
// CHECK OWNER
// =================================
if( $user !== null )
{
	$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	if( $result['gidNumber'] != $userdata['user_ldap'] )
		throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the subdomain {$subdomain} ({$result['gidNumber']})");
}

// =================================
// UPDATE REMOTE SUBDOMAIN
// =================================
if( $arecord !== null )
{
	$params = array('aRecord'=>$arecord);
	asapi::send('/'.$domain.'/subdomains/'.$result['uid'], 'PUT', array('cNAMERecord'=>$result['cNAMERecord'], 'mode'=>'delete'));
}
else if( $cnamerecord !== null )
{
	$params = array('cNAMERecord'=>$cnamerecord);
	asapi::send('/'.$domain.'/subdomains/'.$result['uid'], 'PUT', array('aRecord'=>$result['aRecord'], 'mode'=>'delete'));
}

asapi::send('/'.$domain.'/subdomains/'.$result['uid'], 'PUT', $params);

responder::send("OK");

?>