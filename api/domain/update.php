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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/domain/help\">domain</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change</li>
	<li><h2>Description :</h2> modify the domain</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>domain : The name or id of the domain to search for. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, domain_name, domain, domain_id, id, uid)</li>
			<li>mx1 : The primary MX server of the domain. <span class=\"required\">required</span>.</li>
			<li>mx2 : The secondary MX server of the domain. <span class=\"required\">required</span>.</li>
			<li>aRecord : The A Record of the domain. <span class=\"optional\">optional</span>. (alias : arecord)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, DOMAIN_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'DOMAIN_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$domain = request::getCheckParam(array(
	'name'=>array('name', 'domain_name', 'domain', 'domain_id', 'id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>"[a-z0-9_\-]{1,200}(\.[a-z0-9_\-]{1,5}){1,4}|[0-9]+",
	'action'=>true
	));
$mx1 = request::getCheckParam(array(
	'name'=>array('mx1'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>200,
	'match'=>request::ALPHANUM|request::PUNCT
	));
$mx2 = request::getCheckParam(array(
	'name'=>array('mx2'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>200,
	'match'=>request::ALPHANUM|request::PUNCT
	));
$arecord = request::getCheckParam(array(
	'name'=>array('arecord'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>20,
	'match'=>request::NUMBER|request::PUNCT
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
	
// =================================
// GET REMOTE DOMAIN INFO
// =================================
if( is_numeric($domain) )
	$result = asapi::send('/domains/', 'GET', array('uidNumber'=>$domain));
else
	$result = asapi::send('/domains/'.$domain, 'GET');

if( $result == null || $result['uidNumber'] == null )
	throw new ApiException("Unknown domain", 412, "Unknown domain : {$domain}");

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
		throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the domain {$domain} ({$result['gidNumber']})");
}

// =================================
// UPDATE REMOTE DOMAIN
// =================================
$params = array('mXRecord' => '10 '.$mx1);
if( $arecord !== null )
	$params['aRecord'] = $arecord;
asapi::send('/domains/'.$result['associatedDomain'], 'PUT', $params);

$params = array('mXRecord' => '20 '.$mx2, 'mode' => 'add');
asapi::send('/domains/'.$result['associatedDomain'], 'PUT', $params);

responder::send("OK");

?>