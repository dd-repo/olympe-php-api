<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$help = request::getAction(false, false);
if( $help == 'help' || $help == 'doc' )
{
	$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/database/help\">database</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a database</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>database : The name of the database to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, database_name)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching databases [{'name', 'type', 'desc', 'user':{'id', 'name'}},...]</li>
	<li><h2>Required grants :</h2> ACCESS, DATABASE_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'DATABASE_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$database = request::getCheckParam(array(
	'name'=>array('database', 'name', 'database_name'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::UPPER|request::NUMBER,
	'action'=>true
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));

// =================================
// PREPARE WHERE CLAUSE
// =================================
$where = '';
if( $database !== null )
	$where .= " AND d.database_name = '".security::escape($database)."'";
if( $user !== null )
{
	if( is_numeric($user) )
		$where .= " AND u.user_id = " . $user;
	else
		$where .= " AND u.user_name = '".security::escape($user)."'";
}
	
// =================================
// SELECT RECORDS
// =================================
$sql = "SELECT d.database_name, d.database_type, d.database_desc, u.user_id, u.user_name 
		FROM `databases` d
		LEFT JOIN users u ON(u.user_id = d.database_user)
		WHERE true {$where}";
$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

// =================================
// FORMAT RESULT
// =================================
$databases = array();
foreach( $result as $r )
{
	$d['name'] = $r['database_name'];
	$d['type'] = $r['database_type'];
	$d['desc'] = $r['database_desc'];
	$d['user'] = array('id'=>$r['user_id'], 'name'=>$r['user_name']);
	
	$databases[] = $d;		
}

responder::send($databases);

?>