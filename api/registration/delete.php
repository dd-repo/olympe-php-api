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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/registration/help\">registration</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> del, remove, calcel</li>
	<li><h2>Description :</h2> removes a pending registration</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name of the target user. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, user_name, username, login)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, REGISTRATION_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'REGISTRATION_DELETE'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('name', 'user_name', 'username', 'login', 'user'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));

// =================================
// EXECUTE QUERY
// =================================
$sql = "DELETE FROM register WHERE register_user='".security::escape($user)."'";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>