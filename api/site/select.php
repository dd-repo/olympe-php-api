<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a site");
$a->addGrant(array('ACCESS', 'SITE_SELECT'));
$a->setReturn(array(array(
	'name'=>'the site name', 
	'id'=>'the id of the site', 
	'hostname'=>'the complete site hostname', 
	'homeDirectory'=>'the directory of the site',
	'cNAMERecord'=>'the CNAME Record of the site',
	'aRecord'=>'the aRecord of the site',
	'user'=>array(array(
		'id'=>'the user id', 
		'name'=>'the username')
	),
	)));
$a->addParam(array(
	'name'=>array('site', 'site_id', 'id'),
	'description'=>'The name or id of the target site.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>false
	));
$a->addParam(array(
	'name'=>array('directory'),
	'description'=>'Select directory.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('category'),
	'description'=>'If directory is true, select only this category.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>2,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('count'),
	'description'=>'Whether or not to include only the number of matches. Default is false.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
	
$a->setExecute(function() use ($a)
{
	// =================================
	// CHECK AUTH
	// =================================
	$a->checkAuth();

	// =================================
	// GET PARAMETERS
	// =================================
	$site = $a->getParam('site');
	$user = $a->getParam('user');
	$count = $a->getParam('count');
	
	if( $count == '1' || $count == 'yes' || $count == 'true' || $count === true || $count === 1 ) $count = true;
	else $count = false;
	if( $directory == '1' || $directory == 'yes' || $directory == 'true' || $directory === true || $directory === 1 ) $directory = true;
	else $directory = false;	
	
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
	// GET DIRECTORY
	// =================================
	if( $directory === true )
	{
		$where = '';
		if( $category != null )
			$where .= " AND site_cateory = {$category}";
		
		$sql = "SELECT * FROM directory WHERE site_status = 1 {$where}";
		$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
		
		$sites = array();
		foreach( $result as $r )
		{
			$s['id'] = $r['site_ldap_id'];
			$s['title'] = $r['site_title'];
			$s['description'] = $r['site_description'];
			$s['category'] = $r['site_category'];
			$s['url'] = $r['site_url'];
			
			$sites[] = $s;
		}
		
		responder::send($sites);
	}
	
	// =================================
	// SELECT REMOTE ENTRIES
	// =================================
	if( $site !== null )
	{
		if( is_numeric($site) )
			$dn = $GLOBALS['ldap']->getDNfromUID($site);
		else
			$dn = ldap::buildDN(ldap::SUBDOMAIN, $GLOBALS['CONFIG']['DOMAIN'], $site);
		
		$result = $GLOBALS['ldap']->read($dn);
	}
	else if( $user !== null )
	{
		$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
		$result = $GLOBALS['ldap']->search(ldap::buildDN(ldap::DOMAIN, $GLOBALS['CONFIG']['DOMAIN']), ldap::buildFilter(ldap::SUBDOMAIN, "(owner={$user_dn})"), $count);
	}
	else
		$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::SUBDOMAIN), $count);

	if( $count === true )
		responder::send($result);
		
	// =================================
	// FORMAT RESULT
	// =================================
	$sites = array();
	if( $site !== null )
	{
		if( is_array($result['owner']) )
			$result['owner'] = $result['owner'][0];
			
		if( $user !== null && $GLOBALS['ldap']->getUIDfromDN($result['owner']) != $userdata['user_ldap'] )
			throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the site {$site} ({$result['gidNumber']})");
		
		$sql = "SELECT user_id, user_name FROM users WHERE user_ldap = ".$GLOBALS['ldap']->getUIDfromDN($result['owner']);
		$info = $GLOBALS['db']->query($sql);
	
		$sql = "SELECT storage_size FROM storages WHERE storage_path = '{$result['homeDirectory']}'";
		$storage = $GLOBALS['db']->query($sql);
		
		$s['name'] = $result['uid'];
		$s['id'] = $result['uidNumber'];
		$s['hostname'] = $result['associatedDomain'];
		$s['homeDirectory'] = $result['homeDirectory'];
		$s['size'] = $storage['storage_size'];
		$s['cNAMERecord'] = $result['cNAMERecord'];
		$s['aRecord'] = $result['aRecord'];
		$s['description'] = $result['description'];
		$s['directory'] = $result['gecos'];
		$s['user'] = array('id'=>$info['user_id'], 'name'=>$info['user_name']);
		
		$sites[] = $s;
	}
	else
	{
		foreach( $result as $r )
		{
			$sql = "SELECT storage_size FROM storages WHERE storage_path = '{$r['homeDirectory']}'";
			$storage = $GLOBALS['db']->query($sql);
			$sql = "SELECT * FROM directory WHERE site_ldap_id = '{$r['uidNumber']}'";
			$directory = $GLOBALS['db']->query($sql);
			
			$s['name'] = $r['uid'];
			$s['id'] = $r['uidNumber'];
			$s['hostname'] = $r['associatedDomain'];
			$s['homeDirectory'] = $r['homeDirectory'];
			$s['size'] = $storage['storage_size'];
			$s['cNAMERecord'] = $r['cNAMERecord'];
			$s['aRecord'] = $r['aRecord'];
			$s['title'] = $directory['site_title'];
			$s['description'] = $directory['site_description'];
			$s['directory'] = $directory['site_status'];
			$s['user'] = array('id'=>'', 'name'=>'');
			
			$sites[] = $s;	
		}
	}

	responder::send($sites);
});

return $a;

?>