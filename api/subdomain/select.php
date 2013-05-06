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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/subdomain/help\">subdomain</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a subdomain</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>domain : The name of the domain that subdomains belong to. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : domain_name)</li>
			<li>subdomain : The name or id of the subdomain to search for. <span class=\"optional\">optional</span>. (alias : subdomain_id, id, uid)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching subdomains [{'name', 'id', 'hostname', 'homeDirectory', 'aRecord', 'cNAMERecord', 'user':{'id', 'name'}},...]</li>
	<li><h2>Required grants :</h2> ACCESS, SUBDOMAIN_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'SUBDOMAIN_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$domain = request::getCheckParam(array(
	'name'=>array('domain', 'domain_name'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>"[a-z0-9_\-]{1,200}(\.[a-z0-9_\-]{1,5}){1,4}|[0-9]+",
	'action'=>true
	));
$subdomain = request::getCheckParam(array(
	'name'=>array('subdomain', 'subdomain_id', 'id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>"[a-z0-9_\\-]{2,50}"
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));

// =================================
// GET USER DATA
// =================================
if( $user !== null )
{ 
	$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
}

// =================================
// SELECT REMOTE ENTRIES
// =================================
if( $subdomain !== null )
{
	if( is_numeric($subdomain) )
		$result = asapi::send('/'.$domain.'/subdomains', 'GET', array('uidNumber'=>$subdomain));
	else
		$result = asapi::send('/'.$domain.'/subdomains/'.$subdomain, 'GET');
}
else if( $user !== null )
{
	$params = array('gidNumber' => $userdata['user_ldap']);
	$result = asapi::send('/'.$domain.'/subdomains', 'GET', $params);
}
else
	$result = asapi::send('/'.$domain.'/subdomains', 'GET');

// =================================
// FORMAT RESULT
// =================================
$subdomains = array();
if( $subdomain !== null )
{
	if( $user !== null && $result['gidNumber'] != $userdata['user_ldap'] )
		throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the subdomain {$subdomain} ({$result['gidNumber']})");
	
	$sql = "SELECT user_id, user_name FROM users WHERE user_ldap = {$result['gidNumber']}";
	$info = $GLOBALS['db']->query($sql);
	
	$s['name'] = $result['uid'];
	$s['id'] = $result['uidNumber'];
	$s['hostname'] = $result['associatedDomain'];
	$s['homeDirectory'] = $result['homeDirectory'];
	$s['cNAMERecord'] = $result['cNAMERecord'];
	$s['aRecord'] = $result['aRecord'];
	$s['user'] = array('id'=>$info['user_id'], 'name'=>$info['user_name']);
	
	$subdomains[] = $s;
}
else
{
	$ldaps = '';
	foreach( $result as $r )
		$ldaps .= ','.$r['gidNumber'];
	
	$sql = "SELECT user_id, user_name, user_ldap FROM users WHERE user_ldap IN(-1{$ldaps})";
	$info = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	foreach( $result as $r )
	{
		$s['name'] = $r['uid'];
		$s['id'] = $r['uidNumber'];
		$s['hostname'] = $r['associatedDomain'];
		$s['homeDirectory'] = $r['homeDirectory'];
		$s['cNAMERecord'] = $r['cNAMERecord'];
		$s['aRecord'] = $r['aRecord'];
		$s['user'] = array('id'=>'', 'name'=>'');
		
		foreach( $info as $i )
		{
			if( $i['user_ldap'] == $r['gidNumber'] )
			{
				$s['user']['id'] = $i['user_id'];
				$s['user']['name'] = $i['user_name'];
				break;
			}
		}
		
		$subdomains[] = $s;		
	}
}

responder::send($subdomains);

?>