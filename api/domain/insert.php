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
	
	if( !preg_match("/^([a-zA-Z0-9\\-_]+\\.)+[a-zA-Z0-9\\-_]+$/", $domain) )
		throw new ApiException("Parameter validation failed", 412, "Eh ho... t'sais pas ce que c'est un nom de domaine ou quoi !? Nana mais sans blague, tu crois vraiment que \"" . $domain . "\" ca va marcher ? Et si tu allais t'acheter un cerveau, et des lunettes pour lire la doc sur ce que c'est un nom de domaine... Et vas aussi lire les <a href=\"http://fr.wikipedia.org/wiki/Darwin_Awards\">Darwin Awards</a>, ca te donnera peut-être des idées... pfff le boulet quoi !");
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
	// INSERT REMOTE DOMAIN
	// =================================
	$dn = ldap::buildDN(ldap::DOMAIN, $domain);
	$split = explode('.', $domain);
	$name = $split[0];
	$params = array('dn' => $dn, 'uid' => $name, 'domain' => $domain, 'owner' => $user_dn);
	
	$handler = new domain();
	$data = $handler->build($params);
	
	$GLOBALS['ldap']->create($dn, $data);
	
	// =================================
	// GET SITE INFO
	// =================================
	if( is_numeric($site) )
		$dn_site = $GLOBALS['ldap']->getDNfromUID($site);
	else
		$dn_site = ldap::buildDN(ldap::SUBDOMAIN, $GLOBALS['CONFIG']['DOMAIN'], $site);
		
	$site_data = $GLOBALS['ldap']->read($dn_site);
	
	// =================================
	// POST-CREATE SYSTEM ACTIONS
	// =================================
	$data['destination'] = $site_data['homeDirectory'] . '/' . $dir;
	$data['site_data'] = $site_data;
	$GLOBALS['system']->create(system::DOMAIN, $data);
	
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
