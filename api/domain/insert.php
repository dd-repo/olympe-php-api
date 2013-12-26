<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new domain");
$a->addGrant(array('ACCESS', 'DOMAIN_INSERT'));
$a->setReturn(array(array(
	'id'=>'the id of the domain', 
	'domain'=>'the domain name'
	)));
$a->addParam(array(
	'name'=>array('domain', 'domain_name', 'domain_id', 'id'),
	'description'=>'The name of the new domain.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::PHRASE,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('site_name', 'site', 'site_id'),
	'description'=>'The name or id of the target site',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	));
$a->addParam(array(
	'name'=>array('directory', 'dir'),
	'description'=>'The target directory in the site',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	));
$a->addParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'description' => 'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
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
	$domain = $a->getParam('domain');
	$site = $a->getParam('site');
	$dir = $a->getParam('dir');
	$user = $a->getParam('user');
	
	if( !preg_match("/^([a-z0-9\\-_]+\\.)+[a-z0-9\\-_]+$/", $domain) )
		throw new ApiException("Parameter validation failed", 412, "Merci de bien vouloir vous renseigner sur le rôle et la nature d'un nom de domaine avant d'en ajouter un qui ne correspond à aucune entrée valide.");
	if( is_numeric($domain) )
		throw new ApiException("Parameter validation failed", 412, "Parameter domain may not be numeric : " . $domain);

	// =================================
	// GET USER DATA
	// =================================
	$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

	// =================================
	// GET REMOTE USER DN
	// =================================	
	$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
	
	// =================================
	// CHECK QUOTA
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	checkQuota('DOMAINS', $user);

	// =================================
	// CHECK IF REMOTE DOMAIN EXISTS
	// =================================
	try
	{
		$dn = ldap::buildDN(ldap::DOMAIN, $domain);
		$result = $GLOBALS['ldap']->read($dn);
		
		// this should throw a 404 if the user does NOT exist
		throw new ApiException("Domain already exists", 412, "Existing remote domain : " . $domain);
	}
	catch(Exception $e)
	{
		// if this is not the 404 we expect, rethrow it
		if( !($e instanceof ApiException) || !preg_match("/Entry not found/s", $e.'') )
			throw $e;
	}

	// =================================
	// GET SITE INFO
	// =================================
	if( is_numeric($site) )
		$dn_site = $GLOBALS['ldap']->getDNfromUID($site);
	else
		$dn_site = ldap::buildDN(ldap::SUBDOMAIN, $GLOBALS['CONFIG']['DOMAIN'], $site);
		
	$site_data = $GLOBALS['ldap']->read($dn_site);
	
	// =================================
	// INSERT REMOTE DOMAIN
	// =================================
	$dn = ldap::buildDN(ldap::DOMAIN, $domain);
	$split = explode('.', $domain);
	$name = $split[0];
	$destination = $site_data['homeDirectory'] . '/' . $dir;
	$params = array('dn' => $dn, 'uid' => $name, 'domain' => $domain, 'owner' => $user_dn, 'gecos' => $destination);
	
	$handler = new domain();
	$data = $handler->build($params);
	
	$GLOBALS['ldap']->create($dn, $data);
	

	
	// =================================
	// POST-CREATE SYSTEM ACTIONS
	// =================================
	$data['destination'] = $destination;
	$data['site_data'] = $site_data;
	$commands[] = "mkdir -p {$data['homeDirectory']} && rmdir {$data['homeDirectory']} && mkdir -p {$data['destination']} && ln -s {$data['destination']} {$data['homeDirectory']} && chown -h {$data['site_data']['uidNumber']}:{$data['site_data']['gidNumber']} {$data['homeDirectory']} && chown {$data['site_data']['uidNumber']} {$data['destination']} && cd {$data['homeDirectory']} && mkdir Users && chown {$data['site_data']['uidNumber']}:{$data['site_data']['gidNumber']} Users && chmod g+s Users && ln -s . www && chown -h {$data['site_data']['uidNumber']}:{$data['site_data']['gidNumber']} www";
	$GLOBALS['system']->exec($commands);
	
	// =================================
	// INSERT REMOTE CONTAINERS
	// =================================
	$data_users = array('ou' => 'Users', 'objectClass' => array('top', 'organizationalUnit'));
	$data_groups = array('ou' => 'Groups', 'objectClass' => array('top', 'organizationalUnit'));
	$data_people = array('ou' => 'People', 'objectClass' => array('top', 'organizationalUnit'));
	$GLOBALS['ldap']->create('ou=Users,' . $dn, $data_users);
	$GLOBALS['ldap']->create('ou=Groups,' . $dn, $data_groups);
	$GLOBALS['ldap']->create('ou=People,' . $dn, $data_people);

	// =================================
	// INSERT DEFAULT SUBDOMAINS
	// =================================
	$dn = ldap::buildDN(ldap::SUBDOMAIN, $domain, 'www');
	$params = array('dn' => $dn, 'subdomain' => 'www', 'uid' => 'www', 'domain' => $domain, 'owner' => $user_dn);
	$handler = new subdomain();
	$data = $handler->build($params);
	$GLOBALS['ldap']->create($dn, $data);
	
	// =================================
	// SYNC QUOTA
	// =================================
	syncQuota('DOMAINS', $user);
	
	responder::send(array("domain"=>$domain, "id"=>$result['uidNumber']));
});

return $a;

?>
