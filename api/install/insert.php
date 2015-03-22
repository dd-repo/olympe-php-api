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
	'url'=>'the URL to the remote installation'
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
	'name'=>array('user'),
	'description'=>'The id of the user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('path'),
	'description'=>'The installation directory.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>200,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('type', 'select'),
	'description'=>'The CMS you want to install.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>200,
	'match'=>request::LOWER
	));
$a->addParam(array(
	'name'=>array('type', 'select'),
	'description'=>'The CMS you want to install.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>200,
	'match'=>request::LOWER
	));
$a->addParam(array(
	'name'=>array('database', 'database_id'),
	'description'=>'The database that will be used for the installation.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>200,
	'match'=>request::ALL
	));
	
$a->setExecute(function() use ($a)
{
	// =================================
	// CHECK AUTH
	// =================================
	$a->checkAuth();
	$site = $a->getParam('site');
	$path = $a->getParam('path');
	$type = $a->getParam('type');
	$config = $a->getParam('database');
	$user = $a->getParam('user');
	
	$sql = "SELECT * FROM users WHERE user_id = {$user}"; 
	$userdata = $GLOBALS['db']->query($sql);
	
	if( !$userdata )
		throw new ApiException("Unknown user", 412, "User {$user} does not exist");
	
	$dn = $GLOBALS['ldap']->getDNfromUID($site);
	$data = $GLOBALS['ldap']->read($dn);
	
	$data['owner'] = explode( ',', explode ( '=', $data['owner'] )[1] )[0];
	
	if( !$data['uid'] || $data['owner'] != $userdata['user_name'] )
		throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the site {$site}");
	
	$command[] = "cp -Rf /dns/in/olympe/on/import/{$type}-en_EN.zip  {$data['homeDirectory']}{$path}/file.zip";
	// $GLOBALS['gearman']->sendAsync($command);	
	
	$command[] = "cd {$data['homeDirectory']}{$path} && unzip file.zip";
	$result['command'] = $command;
	
	
	
	// TODO: Refactor all installations using a real shell script
	responder::send($result);
}
	
	// =================================
	// LOG ACTION
	// =================================	
	// logger::insert('backup/insert', $a->getParams(), $userdata['user_id']);
	
);

return $a;

?>