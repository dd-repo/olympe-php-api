<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

// WARNING : THIS PAGE ONLY PROVIDES 2 FUNCTIONS TO CHECK/SYNC
// THE USER QUOTA. IT SHOULD NOT BE CALLED DIRECTLY.

security::requireGrants(array('QUOTA_USER_INTERNAL'));

function checkQuota($type, $user)
{
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";
	
	$sql = "SELECT uq.quota_max, uq.quota_used
			FROM quotas q 
			LEFT JOIN user_quota uq ON(q.quota_id = uq.quota_id)
			LEFT JOIN users u ON(u.user_id = uq.user_id)
			WHERE q.quota_name='".security::escape($type)."' 
			AND {$where}";
	$result = $GLOBALS['db']->query($sql);
	
	if( $result == null || $result['quota_max'] == null || $result['quota_used'] >= $result['quota_max'] )
		throw new ApiException("Unsufficient quota", 412, "Quota limit reached or not set : {$result['quota_used']}/{$result['quota_max']}");
}

function syncQuota($type, $user)
{
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";

	$count = "quota_used";
	switch($type)
	{
		case 'SITES':
			$sql = "SELECT user_ldap FROM users u WHERE {$where}";
			$result = $GLOBALS['db']->query($sql);
			if( $result == null || $result['user_ldap'] == null )
				throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
			$result = asapi::send('/olympe.in/subdomains', 'GET', array('gidNumber' => $result['user_ldap']));
			$count = count($result);
			break;
		case 'DOMAINS':
			$sql = "SELECT user_ldap FROM users u WHERE {$where}";
			$result = $GLOBALS['db']->query($sql);
			if( $result == null || $result['user_ldap'] == null )
				throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
			$result = asapi::send('/domains', 'GET', array('gidNumber' => $result['user_ldap']));
			$count = count($result);
			break;
		case 'DATABASES':
			$sql = "SELECT COUNT(*) as c
					FROM `databases` d
					LEFT JOIN users u ON(u.user_id = d.database_user)
					WHERE {$where}";
			$result = $GLOBALS['db']->query($sql);
			if( $result == null || $result['c'] == null )
				throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
			$count = $result['c'];
			break;
		default:
			throw new ApiException("Undefined quota type", 500, "Not preconfigured for quota type : {$type}");
	}
	
	$sql = "UPDATE IGNORE user_quota 
			SET quota_used=LEAST({$count},quota_max)
			WHERE quota_id IN (SELECT q.quota_id FROM quotas q WHERE q.quota_name='".security::escape($type)."')
			AND user_id IN (SELECT u.user_id FROM users u WHERE {$where})";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
}

?>