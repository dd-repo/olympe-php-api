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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/user/help\">user</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a user</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the users to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. <span class=\"multiple\">multiple</span>. (alias : name, user_name, username, login, names, user_names, usernames, logins, users, id, user_id, uid, ids, user_ids, uids)</li>
			<li>from : From subscription date. <span class=\"optional\">optional</span>.</li>
			<li>to : To subscription date. <span class=\"optional\">optional</span>.</li>
			<li>count : [0 : not count (default) | 1 : Return only count] <span class=\"optional\">optional</span>.</li>
			<li>fast : [0 : (default) | 1 : Return fast response with only user name and id] <span class=\"optional\">optional</span>.</li>
			<li>quota : [0 : (default) | 1 : Return user quotas as well] <span class=\"optional\">optional</span>. (alias : quotas)</li>
			<li>overquota :  [0 : (default) | 1 : Only return overquota user] <span class=\"optional\">optional</span>. </li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching users [{'name', 'id', 'firstname', 'lastname', 'email', 'date', 'ip', 'quotas':[{'id','name','max','used'},...]},...] || {'count'}</li>
	<li><h2>Required grants :</h2> ACCESS, USER_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'USER_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('name', 'user_name', 'username', 'login', 'user', 'names', 'user_names', 'usernames', 'logins', 'users', 'id', 'user_id', 'uid', 'ids', 'user_ids', 'uids'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*",
	'action'=>true
	));
$from = request::getCheckParam(array(
	'name'=>array('from'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));
$to = request::getCheckParam(array(
	'name'=>array('to'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));
$count = request::getCheckParam(array(
	'name'=>array('count'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>1,
	'match'=>request::NUMBER
	));
$fast = request::getCheckParam(array(
	'name'=>array('fast'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$quota = request::getCheckParam(array(
	'name'=>array('quota', 'quotas'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$overquota = request::getCheckParam(array(
	'name'=>array('overquota'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
	
if( $fast == '1' || $fast == 'yes' || $fast == 'true' || $fast === true || $fast === 1 )
	$fast = true;
else
	$fast = false;

if( $quota == '1' || $quota == 'yes' || $quota == 'true' || $quota === true || $quota === 1 )
	$quota = true;
else
	$quota = false;
	
if( $overquota == '1' || $overquota == 'yes' || $overquota == 'true' || $overquota === true || $overquota === 1 )
	$overquota = true;
else
	$overquota = false;
	
// =================================
// PREPARE WHERE CLAUSE
// =================================
$where_name = '';
$where_id = '';
if( $user !== null && count($user) > 0 )
{
	foreach( $user as $u )
	{
		if( is_numeric($u) )
		{
			if( strlen($where_id) == 0 ) $where_id = ' OR u.user_id IN(-1';
			$where_id .= ','.$u;
		}
		else
		{
			if( strlen($where_name) == 0 ) $where_name = '';
			$where_name .= " OR u.user_name LIKE '%".security::escape($u)."%'";
		}
	}
	if( strlen($where_id) > 0 ) $where_id .= ')';
}
elseif( $overquota )
	$where_name .= " OR uq.quota_used > uq.quota_max ";
else
	$where_name = " OR true";

// =================================
// SELECT RECORDS
// =================================
if( $quota )
{
	$sql = "SELECT u.user_id, u.user_name, u.user_ldap, u.user_date, q.quota_id, q.quota_name, uq.quota_max, uq.quota_used
			FROM users u
			LEFT JOIN user_quota uq ON(u.user_id = uq.user_id)
			LEFT JOIN quotas q ON(uq.quota_id = q.quota_id)
			WHERE false {$where_name} {$where_id}
			ORDER BY u.user_name";
}
else
{
	$sql = "SELECT u.user_id, u.user_name, u.user_ldap, u.user_date
			FROM users u
			WHERE false {$where_name} {$where_id}
			ORDER BY u.user_name";
}
$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

// =================================
// FORMAT RESULT
// =================================
$users = array();
$ids = array();
$current = null;
foreach( $result as $r )
{
	if( $current == null || $current['id'] != $r['user_id'] )
	{
		if( $current != null )
		{
			$users[] = $current;
			$ids[] = $current['uid'];
		}
		
		$current = array('name'=>$r['user_name'], 'id'=>$r['user_id'], 'uid'=>$r['user_ldap'], 'firstname'=>'', 'lastname'=>'', 'email'=>'', 'date'=>$r['user_date'], 'ip'=>'');
		
		if( $quota )
			$current['quotas'] = array();
	}
	
	if( $quota && $r['quota_id'] != null )
		$current['quotas'][] = array('id'=>$r['quota_id'], 'name'=>$r['quota_name'], 'max'=>$r['quota_max'], 'used'=>$r['quota_used']);
}
if( $current != null )
{
	$users[] = $current;
	$ids[] = $current['uid'];
}

if( $fast )
{
	responder::send($users);
	exit;
}

// =================================
// RETREIVE INFO FROM REMOTE USER
// =================================
try
{	
	if( $count == 1 )
		responder::send(array('count'=>count($ids)));
	
	$remote = asapi::send('/'.$GLOBALS['CONFIG']['DOMAIN'].'/users/', 'GET', array('array'=>json_encode($ids)));
	
	foreach( $remote as $r )
	{
		for( $i = 0; $i < count($users); $i++ )
		{
			if( $users[$i]['uid'] == $r['uidNumber'] )
			{
				$users[$i]['firstname'] = $r['givenName'];
				$users[$i]['lastname'] = $r['sn'];
				$users[$i]['ip'] = $r['gecos'];
				$users[$i]['email'] = (isset($r['mailForwardingAddress'])?$r['mailForwardingAddress']:$r['mail']);
				break;
			}
		}
	}
	
	if( $from !== null || $to !== null )
	{
		if( $to === null )
			$to = time();
		$output = array();
		foreach( $users as $u )
		{		
			if( $u['date'] >= $from && $u['date'] <= $to )
				$output[] = $u;
		}
	}
	else
		$output = $users;
		
}
catch(Exception $e) { }

responder::send($output);

?>