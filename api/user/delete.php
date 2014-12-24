<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('delete', 'del', 'remove', 'destroy'));
$a->setDescription("Removes a user");
$a->addGrant(array('ACCESS', 'USER_DELETE'));
$a->setReturn("OK");
$a->addParam(array(
	'name'=>array('user', 'name', 'user_name', 'username', 'login', 'id', 'user_id', 'uid'),
	'description'=>'The name or id of the user to delete.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
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
	$user = $a->getParam('user');
	
	// =================================
	// GET LOCAL USER INFO
	// =================================
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";

	$sql = "SELECT u.user_id, u.user_name, u.user_ldap FROM users u WHERE {$where}";
	$result = $GLOBALS['db']->query($sql);
	
	if( $result == null || $result['user_id'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

	// =================================
	// GET USER INFO
	// =================================	
	$dn = ldap::buildDN(ldap::USER, $GLOBALS['CONFIG']['DOMAIN'], $result['user_name']);
	$data = $GLOBALS['ldap']->read($dn);

	if( $dn )
	{
		// =================================
		// SITES
		// =================================
		$option = "(owner={$dn})";
		$sites = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::SUBDOMAIN, $option));
	
		foreach( $sites as $s )
		{
			if( $s['dn'] ) 
			{
				$GLOBALS['ldap']->delete($s['dn']);
				$command = "rm -Rf {$s['homeDirectory']}";
				$GLOBALS['gearman']->sendAsync($command);
				
				// =================================
				// DELETE DIRECTORY ENTRY
				// =================================
				$sql = "DELETE FROM directory WHERE site_ldap_id = {$s['uidNumber']}";
				$GLOBALS['db']->query($sql, mysql::NO_ROW);
				
				// =================================
				// DELETE PIWIK SITE
				// =================================
				$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=SitesManager.getSitesIdFromSiteUrl&url=http://{$s['associatedDomain']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
				$json = json_decode(@file_get_contents($url), true);
				$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=SitesManager.deleteSite&idSite={$json[0]['idsite']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
				@file_get_contents($url);
			}
		}
		
		// =================================
		// DOMAINS
		// =================================
		$option = "(owner={$dn})";
		$domains = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::DOMAIN, $option));
	
		foreach( $domains as $d )
		{
			if( $d['dn'] ) 
			{
				$GLOBALS['ldap']->delete($d['dn']);
				$command = "rm -Rf {$d['homeDirectory']}";
				$GLOBALS['gearman']->sendAsync($command);
			}
		}

		// =================================
		// DATABASES
		// =================================
		$sql = "SELECT d.database_type, d.database_name, d.database_server FROM `databases` d WHERE database_user = '{$result['user_id']}'";
		$databases = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
		foreach( $databases as $d )
		{
			switch( $result['database_type'] )
			{
				case 'mysql':
					if( $d['database_server'] == 'sql.olympe.in' || $d['database_server'] == 'sql1.olympe.in' )
					$link = new mysqli($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD'], 'mysql', $GLOBALS['CONFIG']['MYSQL_ROOT_PORT']);
				else if( $d['database_server'] == 'sql2.olympe.in' )
					$link = new mysqli($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD'], 'mysql', $GLOBALS['CONFIG']['MYSQL_ROOT_PORT2']);
					$link->query("DROP DATABASE '{$d['database_name']}'");
					$link->query("DROP USER '{$d['database_name']}'");
				break;	
				case 'pgsql':
					$command = "/dns/tm/sys/usr/local/bin/drop-db-pgsql {$d['database_name']}";
					$GLOBALS['gearman']->sendAsync($command);
				break;
				case 'mongodb':
					$command = "/dns/tm/sys/usr/local/bin/drop-db-mongodb {$d['database_name']}";
					$GLOBALS['gearman']->sendAsync($command);
				break;
			}
		}
		
		// =================================
		// DELETE REMOTE USER
		// =================================
		$GLOBALS['ldap']->delete($dn);
	}
	
	// =================================
	// DELETE LOCAL USER
	// =================================
	$sql = "DELETE FROM users WHERE user_id={$result['user_id']}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	// =================================
	// DELETE PIWIK USER
	// =================================
	$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.deleteUser&userLogin={$result['user_name']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
	@file_get_contents($url);

	// =================================
	// POST-DELETE SYSTEM ACTIONS
	// =================================
	$date = date('YmdHis');
	$command = "rm -Rf {$data['homeDirectory']}";
	$GLOBALS['gearman']->sendAsync($command);
	
	responder::send("OK");
});

return $a;

?>