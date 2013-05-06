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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/account/help\">account</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a account</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>domain : The name of the domain that subdomains belong to. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : domain_name)</li>
			<li>account : The name or id of the account to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, account_name, account_id, id, uid)</li>
			<li>user : The id of the user owning accounts to search for. <span class=\"optional\">optional</span>. (alias : user, user_id)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching account [{'name', 'id', 'firstname', 'lastname', 'redirection', 'homeDirectory', 'mail', 'user':{'id', 'name'}},...]</li>
	<li><h2>Required grants :</h2> ACCESS, ACCOUNT_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'ACCOUNT_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$domain = request::getCheckParam(array(
	'name'=>array('domain', 'domain_name'),
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$account = request::getCheckParam(array(
	'name'=>array('name', 'account_name', 'account', 'account_id', 'id', 'uid'),
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$user = request::getCheckParam(array(
	'name'=>array('user', 'user_id'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>50,
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
if( $account !== null )
{
	if( is_numeric($account) )
		$result = asapi::send('/'.$domain.'/users', 'GET', array('uidNumber'=>$account));
	else
		$result = asapi::send('/'.$domain.'/users/'.$account, 'GET');
}
else if( $user !== null )
{
	$params = array('gidNumber' => $userdata['user_ldap']);
	$result = asapi::send('/'.$domain.'/users', 'GET', $params);
}
else
	$result = asapi::send('/'.$domain.'/users', 'GET');

// =================================
// FORMAT RESULT
// =================================
$accounts = array();
if( $account !== null )
{
	if( $user !== null && $result['gidNumber'] != $userdata['user_ldap'] )
		throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the account {$account} ({$result['gidNumber']})");
	
	$sql = "SELECT user_id, user_name FROM users WHERE user_ldap = {$result['gidNumber']}";
	$info = $GLOBALS['db']->query($sql);
	
	$a['name'] = $result['uid'];
	$a['id'] = $result['uidNumber'];
	$a['firstname'] = $result['givenName'];
	$a['lastname'] = $result['sn'];
	$a['redirection'] = $result['mailForwardingAddress'];
	$a['mail'] = $result['mail'];
	$a['user'] = array('id'=>$info['user_id'], 'name'=>$info['user_name']);
	
	$accounts[] = $a;
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
		$a['firstname'] = $r['givenName'];
		$a['lastname'] = $r['sn'];
		$a['redirection'] = $r['mailForwardingAddress'];
		$a['mail'] = $r['mail'];
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
		
		$accounts[] = $a;		
	}
}

responder::send($accounts);

?>