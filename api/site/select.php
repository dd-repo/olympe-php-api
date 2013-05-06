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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/site/help\">site</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a site</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>site : The name or id of the site to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, site_name, site, site_id, id, uid)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>valid : [0 : pending (default) | 1 : valid | 2 unvalid]. <span class=\"optional\">optional</span>. (alias : validation)</li>
			<li>count : [0 : not count (default) | 1 : Return only count]</li> <span class=\"optional\">optional</span>.</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching sites [{'name', 'id', 'hostname', 'homeDirectory', 'cNAMERecord', 'user':{'id', 'name'}, 'valid', 'explain'},...] || {'count'}</li>
	<li><h2>Required grants :</h2> ACCESS, SITE_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'SITE_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$site = request::getCheckParam(array(
	'name'=>array('name', 'site_name', 'site', 'site_id', 'id', 'uid'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
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
$valid = request::getCheckParam(array(
	'name'=>array('valid', 'validation'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>1,
	'match'=>request::NUMBER
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
if( $valid !== null )
	$params['gecos'] = $valid;
if( $count !== null )
	$params['count'] = $count;

try
{
	if( $site !== null )
	{
			if( is_numeric($site) )
			{
				$params['uidNumber'] = $site;
				$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains', 'GET', $params);
			}
			else
				$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains/'.$site, 'GET', $params);
	}
	else if( $user !== null )
	{
		$params['gidNumber'] = $userdata['user_ldap'];
		$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains', 'GET', $params);
	}
	else
		$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/subdomains', 'GET', $params);
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
	responder::send(array('count'=>$result['count']));
else if( count($result) == 0 )
	responder::send(array());
		
// =================================
// FORMAT RESULT
// =================================
$sites = array();
if( $site !== null )
{
	if( $user !== null && $result['gidNumber'] != $userdata['user_ldap'] )
		throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the site {$site} ({$result['gidNumber']})");
	
	$sql = "SELECT user_id, user_name FROM users WHERE user_ldap = {$result['gidNumber']}";
	$info = $GLOBALS['db']->query($sql);
	
	$s['name'] = $result['uid'];
	$s['id'] = $result['uidNumber'];
	$s['hostname'] = $result['associatedDomain'];
	$s['homeDirectory'] = $result['homeDirectory'];
	$s['cNAMERecord'] = $result['cNAMERecord'];
	$s['user'] = array('id'=>$info['user_id'], 'name'=>$info['user_name']);
	$s['valid'] = $result['gecos'];
	$s['explain'] = $result['description'];
	
	$sites[] = $s;
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
		$s['cNAMERecord'] = $r['cNAMERecord'];
		$s['valid'] = $r['gecos'];
		$s['explain'] = $r['description'];
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
		
		$sites[] = $s;		
	}
}

responder::send($sites);

?>