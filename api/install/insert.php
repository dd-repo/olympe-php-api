<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}


$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Start installation");
$a->addGrant(array('ACCESS', 'INSTALL_INSERT'));
$a->setReturn(array(array(
	'url'=>'the URL to the backup'
	)));
$a->addParam(array(
	'name'=>array('site', 'site_id', 'id'),
	'description'=>'The name or id of the target site.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>false
	));
	
$a->setExecute(function() use ($a)
{

	// =================================
	// CHECK AUTH
	// =================================
	$a->checkAuth();
	$user = $a->getParam('user');
	
	$sql = "SELECT user_ldap, user_id, user_name FROM users WHERE user_id=".$user;
	$userdata = $GLOBALS['db']->query($sql);
	
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	
	$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
	$user_info = $GLOBALS['ldap']->read($user_dn);
	$data = $user_info;
	$command = "mkdir -p {$data['homeDirectory']} && chown {$data['uidNumber']}:33 {$data['homeDirectory']} && chmod 750 {$data['homeDirectory']}";
	
	
	// TODO: Refactor all installations using a real shell script
	$result['command'] = $command;
	$command = "ls -alh {$data['homeDirectory']}";
	$result['install'] = exec ( 'pecl install ssh2 http://pecl.php.net/get/ssh2-0.12.tgz' );
	

	$result['exists'] =  function_exists ( 'ssh2_exec' );
	$result['me'] = exec('ls -al ~/.ssh'); 
	/*
	// =================================
	// GET PARAMETERS
	// =================================
	$site = $a->getParam('site');
	$user = $a->getParam('user');
		
	// =================================
	// GET USER DATA
	// =================================
	if( $user !== null )
	{ 
		$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	}*/
	responder::send($result);
}
	
	// =================================
	// LOG ACTION
	// =================================	
	// logger::insert('backup/insert', $a->getParams(), $userdata['user_id']);
	
);

return $a;

?>