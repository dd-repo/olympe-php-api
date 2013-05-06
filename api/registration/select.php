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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/registration/help\">registration</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a pending registration</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name of the target user. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, user_name, username, login)</li>
			<li>email : The email of the user. <span class=\"optional\">optional</span>. (alias : mail, address, user_email, user_mail, user_address)</li>
			<li>code : The validation code.  <span class=\"optional\">optional</span>. (alias : validation)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching pending registrations [{'user', 'email', 'date'},...]</li>
	<li><h2>Required grants :</h2> ACCESS, REGISTRATION_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'REGISTRATION_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('name', 'user_name', 'username', 'login', 'user'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$mail = request::getCheckParam(array(
	'name'=>array('mail', 'email', 'address', 'user_email', 'user_mail', 'user_address'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>150,
	'match'=>request::ALL
	));
$code = request::getCheckParam(array(
	'name'=>array('code', 'validation'),
	'optional'=>true,
	'minlength'=>32,
	'maxlength'=>32,
	'match'=>"[a-fA-F0-9]{32,32}",
	));

// =================================
// PREPARE WHERE CLAUSE
// =================================
$where = '';
if( $user !== null )
	$where .= " AND register_user LIKE '%".security::escape($user)."%'";
if( $mail !== null )
	$where .= " AND register_email LIKE '%".security::escape($mail)."%'";
if( $code !== null )
	$where .= " AND register_code = '".security::escape($code)."'";

// =================================
// SELECT RECORDS
// =================================
$sql = "SELECT register_user, register_email, register_date
		FROM register
		WHERE true {$where}";
$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

// =================================
// FORMAT RESULT
// =================================
$registrations = array();
foreach( $result as $r )
	$registrations[] = array('user'=>$r['register_user'], 'email'=>$r['register_email'], 'date'=>$r['register_date']);

responder::send($registrations);

?>