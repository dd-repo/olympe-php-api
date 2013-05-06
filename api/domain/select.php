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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/site/help\">domain</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a domain</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>domain : The name or id of the domain to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, domain_name, domain, domain_id, id, uid)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>count : [0 : not count (default) | 1 : Return only count]</li> <span class=\"optional\">optional</span>.</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching domains [{'hostname', 'id', 'homeDirectory', 'mXRecord', 'nSRecord', 'aRecord', 'user':{'id', 'name'}},...]</li>
	<li><h2>Required grants :</h2> ACCESS, DOMAIN_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'DOMAIN_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$domain = request::getCheckParam(array(
	'name'=>array('name', 'domain_name', 'domain', 'domain_id', 'id', 'uid'),
	'optional'=>true,
	'minlength'=>0,
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
$count = request::getCheckParam(array(
	'name'=>array('count'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>1,
	'match'=>request::NUMBER
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
$params = array();
if( $count !== null )
	$params['count'] = $count;

try
{
	if( $domain !== null )
	{
		if( is_numeric($domain) )
		{
			$params['uidNumber'] = $domain;
			$result = asapi::send('/domains/', 'GET', $params);
		}
		else
			$result = asapi::send('/domains/'.$domain, 'GET', $params);
	}
	else if( $user !== null )
	{
		$params['gidNumber'] = $userdata['user_ldap'];
		$result = asapi::send('/domains', 'GET', $params);
	}
	else
		$result = asapi::send('/domains', 'GET', $params);
}
catch(Exception $e)
{
	// if this is not a 404
	if( !($e instanceof ApiException) || !preg_match("/404 Not Found/s", $e.'') )
		throw $e;
	else
		$result = array();
}

if( $count == 1 )
	responder::send($result);
else if( count($result) == 0 )
	responder::send(array());
	
// =================================
// FORMAT RESULT
// =================================
$domains = array();
if( $domain !== null )
{
	if( $user !== null && $result['gidNumber'] != $userdata['user_ldap'] )
		throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the domain {$domain} ({$result['gidNumber']})");
		
	$sql = "SELECT user_id, user_name FROM users WHERE user_ldap = {$result['gidNumber']}";
	$info = $GLOBALS['db']->query($sql);
		
	$d['hostname'] = $result['associatedDomain'];
	$d['id'] = $result['uidNumber'];
	$d['homeDirectory'] = $result['homeDirectory'];
	$d['aRecord'] = $result['aRecord'];
	$d['mxRecord'] = $result['mxRecord'];
	$d['nSRecord'] = $result['nSRecord'];
	$d['user'] = array('id'=>$info['user_id'], 'name'=>$info['user_name']);
	
	$domains[] = $d;
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
		$d['hostname'] = $r['associatedDomain'];
		$d['id'] = $r['uidNumber'];
		$d['homeDirectory'] = $r['homeDirectory'];
		$d['aRecord'] = $r['aRecord'];
		$d['mxRecord'] = $r['mxRecord'];
		$d['nSRecord'] = $r['nSRecord'];
		$d['user'] = array('id'=>'', 'name'=>'');
		
		foreach( $info as $i )
		{
			if( $i['user_ldap'] == $r['gidNumber'] )
			{
				$d['user']['id'] = $i['user_id'];
				$d['user']['name'] = $i['user_name'];
				break;
			}
		}
		
		$domains[] = $d;
	}
}

responder::send($domains);

?>