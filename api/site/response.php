<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('response', 'responsetime', 'time', 'delay'));
$a->setDescription("Select response times");
$a->addGrant(array('ACCESS', 'SITE_SELECT'));
$a->setReturn(array(array(
	'id'=>'the entry id', 
	'date'=>'the entry date',
	'site'=>'the site',
	'time'=>'the response time'
	)));
$a->addParam(array(
	'name'=>array('site', 'site_id', 'id'),
	'description'=>'The site id.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('from'),
	'description'=>'From date.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('to'),
	'description'=>'To date.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('count'),
	'description'=>'Return only the number of entries.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('order'),
	'description'=>'Order return.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>20,
	'match'=>"(response_id|response_date|response_site)"
	));
$a->addParam(array(
	'name'=>array('order_type'),
	'description'=>'Order type.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>4,
	'match'=>"(ASC|DESC)"
	));
$a->addParam(array(
	'name'=>array('start'),
	'description'=>'Start response.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('limit'),
	'description'=>'Limit response.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('group'),
	'description'=>'Group by?',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>request::UPPER
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
	
$a->setExecute(function() use ($a)
{
	// =================================
	// CHECK AUTH
	// =================================
	$a->checkAuth();

	// =================================
	// GET PARAMETERS
	// =================================
	$id = $a->getParam('id');
	$from = $a->getParam('from');
	$to = $a->getParam('to');
	$count = $a->getParam('count');
	$order = $a->getParam('order');
	$order_type = $a->getParam('order_type');
	$start = $a->getParam('start');
	$limit = $a->getParam('limit');
	$group = $a->getParam('group');
	$user = $a->getParam('user');

	if( $count == '1' || $count == 'yes' || $count == 'true' || $count === true || $count === 1 )
		$count = true;
	else
		$count = false;
		
	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	$limitation = '';
	$where = '';
	if( $id !== null )
		$where .= " AND response_site = {$id}";
	if( $from !== null )
		$where .= " AND response_date >= {$from}";
	if( $to !== null )
		$where .= " AND response_date >= {$to}";
	if( $start !== null && $limit !== null )
		$limitation .= $start . ", " . $limit;
	else
		$limitation .= "0, 12";
			
	if( $order === null )
		$order = 'response_date';
	if( $ordered === null )
		$ordered = 'DESC';
		
	// =================================
	// SELECT RECORDS
	// =================================
	if( $group !== null )
		$sql = "SELECT COUNT(*) as count, {$group} (FROM_UNIXTIME(response_date)) as {$group} FROM responsetimes WHERE 1 {$where} GROUP BY {$group} (FROM_UNIXTIME(response_date))";
	if( $count === true )
		$sql = "SELECT COUNT(response_id) as count FROM responsetimes WHERE 1 {$where} ORDER BY {$order} {$ordered} LIMIT {$limitation}";	
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	responder::send($result);
});

return $a;

?>