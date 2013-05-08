<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('qcompute'));
$a->setDescription("Compute user quotas");
$a->addGrant(array('ACCESS', 'USER_SELECT'));
$a->setReturn("OK");
$a->addParam(array(
	'name'=>array('type', 'quota', 'quota_type'),
	'description'=>'The quota type.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::UPPER
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
	$type = $a->getParam('type');

	// =================================
	// GET USERS
	// =================================	
	$sql = "SELECT user_id FROM users u WHERE user_id != 1";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	// =================================
	// RESET MAIL QUOTA
	// =================================
	$sql = "UPDATE user_quota SET quota_used = 0 where quota_id = 15";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	// =================================
	// INIT QUOTAS
	// =================================		
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
		
	foreach( $result as $r )
	{
		// =================================
		// SYNC SITES QUOTA
		// =================================
		syncQuota('SITES', $r['user_id']);
		
		// =================================
		// SYNC DOMAINS QUOTA
		// =================================
		syncQuota('DOMAINS', $r['user_id']);

		// =================================
		// SYNC DATABASES QUOTA
		// =================================
		syncQuota('DATABASES', $r['user_id']);

		// =================================
		// SYNC BYTES QUOTA
		// =================================
		syncQuota('BYTES', $r['user_id']);
	}
	
	responder::send("OK");
});

return $a;

?>