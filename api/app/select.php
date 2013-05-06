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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/app/help\">app</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a app</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>app : The name or id the app to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, app_name, id, app_id)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching app [{'id', 'name', 'homeDirectory', 'user':{'id', 'name'}},...]</li>
	<li><h2>Required grants :</h2> ACCESS, APP_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'APP_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$app = request::getCheckParam(array(
	'name'=>array('name', 'app_name', 'app', 'id', 'app_id'),
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>70,
	'match'=>request::UPPER|request::LOWER|request::NUMBER,
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
if( $app !== null )
{
	if( is_numeric($app) )
		$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps', 'GET', array('uidNumber'=>$app));
	else
		$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps/'.$app, 'GET');
}
else if( $user !== null )
{
	$params = array('gidNumber' => $userdata['user_ldap']);
	$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps', 'GET', $params);
}
else
	$result = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/apps', 'GET');

// =================================
// FORMAT RESULT
// =================================
$apps = array();
if( $app !== null )
{
	if( $user !== null && $result['gidNumber'] != $userdata['user_ldap'] )
		throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the app {$app} ({$result['gidNumber']})");
	
	$sql = "SELECT user_id, user_name FROM users WHERE user_ldap = {$result['gidNumber']}";
	$info = $GLOBALS['db']->query($sql);
	
	$a['name'] = $result['uid'];
	$a['id'] = $result['uidNumber'];
	$a['homeDirectory'] = $result['homeDirectory'];
	$a['site'] = $result['gecos'];
	$a['database'] = $result['description'];
	$a['user'] = array('id'=>$info['user_id'], 'name'=>$info['user_name']);
	
	$apps[] = $a;
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
		$a['name'] = $r['uid'];
		$a['id'] = $r['uidNumber'];
		$a['homeDirectory'] = $r['homeDirectory'];
		$a['site'] = $r['gecos'];
		$a['database'] = $r['description'];
		$a['user'] = array('id'=>'', 'name'=>'');
		
		foreach( $info as $i )
		{
			if( $i['user_ldap'] == $r['gidNumber'] )
			{
				$a['user']['id'] = $i['user_id'];
				$a['user']['name'] = $i['user_name'];
				break;
			}
		}
		
		$apps[] = $a;		
	}
}

responder::send($apps);

?>