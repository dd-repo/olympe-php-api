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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/subdomain/help\">subdomain</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new subdomain</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>subdomain : The name of the new subdomain. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name)</li>
			<li>domain : The name of the domain that subdomains belong to. <span class=\"required\">required</span>. (alias : domain_name)</li>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created subdomain {'name', 'id'}</li>
	<li><h2>Required grants :</h2> ACCESS, SUBDOMAIN_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'SUBDOMAIN_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$subdomain = request::getCheckParam(array(
	'name'=>array('subdomain', 'name'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>"[a-z0-9_\\-]{1,50}",
	'action'=>true
	));
$domain = request::getCheckParam(array(
	'name'=>array('domain', 'domain_name'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>"[a-z0-9_\-]{1,200}(\.[a-z0-9_\-]{1,5}){1,4}|[0-9]+"
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
	
if( is_numeric($subdomain) )
	throw new ApiException("Parameter validation failed", 412, "Parameter subdomain may not be numeric : " . $subdomain);

// =================================
// GET USER DATA
// =================================
$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
$userdata = $GLOBALS['db']->query($sql);
if( $userdata == null || $userdata['user_ldap'] == null )
	throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

// =================================
// CHECK IF REMOTE SUBDOMAIN EXISTS
// =================================
try
{
	$result = asapi::send('/'.$domain.'/subdomains/'.$subdomain);
	// this should throw a 404 if the user does NOT exist
	throw new ApiException("Subdomain already exists", 412, "Existing remote subdomain : " . $subdomain);
}
catch(Exception $e)
{
	// if this is not the 404 we expect, rethrow it
	if( !($e instanceof ApiException) || !preg_match("/404 Not Found/s", $e.'') )
		throw $e;
}

// =================================
// INSERT REMOTE SUBDOMAIN
// =================================
$params = array('subdomain'=>$subdomain, 'gidNumber'=>$userdata['user_ldap']);
$result = asapi::send('/'.$domain.'/subdomains', 'POST', $params);
if( !isset($result['uidNumber']) || !is_numeric($result['uidNumber']) )
	throw new ApiException("Unexpected response from upstream API", 500, "Unexpected API response : ".print_r($result, true));

responder::send(array("name"=>$subdomain, "id"=>$result['uidNumber']));

?>