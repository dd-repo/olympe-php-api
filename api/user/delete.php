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
	'maxlength'=>30,
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
	
	// =================================
	// BACKUP USER
	// =================================	
	
	$commands[] = "mkdir -p /tmp/{$data['uid']} && ldapsearch -h ldap.olympe.in -x -b ou=Users,dc=olympe,dc=in,dc=dns -s one uid={$data['uid']} > /tmp/{$data['uid']}/account.ldif ";
	$GLOBALS['system']->exec($commands);

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
				$commands[] = "cp -a {$s['homeDirectory']} /tmp/{$data['uid']}/ && rm -Rf {$s['homeDirectory']}";
				$GLOBALS['system']->exec($commands);
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
				$commands[] = "rm -Rf {$d['homeDirectory']}";
				$GLOBALS['system']->exec($commands);
			}
		}

		// =================================
		// DATABASES
		// =================================
		$sql = "SELECT d.database_type, d.database_name FROM `databases` d WHERE database_user = '{$result['user_id']}'";
		$databases = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
		foreach( $databases as $d )
		{
			switch( $result['database_type'] )
			{
				case 'mysql':
					$link = mysql_connect($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'] . ':' . $GLOBALS['CONFIG']['MYSQL_ROOT_PORT'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD']);
					mysql_query("DROP USER '{$database}'", $link);
					mysql_query("DROP DATABASE `{$database}`", $link);
					mysql_close($link);
				break;	
			}

			$sql = "DELETE FROM `databases` WHERE database_name = '".security::escape($database)."'";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
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
	$commands[] = "rm -Rf {$data['homeDirectory']}";
	$commands[] = "cd /tmp && tar cvfz /dns/tm/sys/var/lib/backup/deleted/{$data['uid']}-{$date}.tgz {$data['uid']} && rm -Rf /tmp/{$data['uid']}";
	$GLOBALS['system']->exec($commands);
	
	responder::send("OK");
});

return $a;

?>