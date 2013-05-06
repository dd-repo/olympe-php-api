<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$action = request::getAction();
switch($action)
{
	case 'create':
	case 'add':
	case 'insert':
	case 'join':
	case 'register':
	case 'signup':
	case 'subscribe':
		request::forward('/registration/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		request::forward('/registration/select'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'cancel':
		request::forward('/registration/delete'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: registration</h1>
<ul>
	<li><h2><a href=\"/registration/insert/help\">insert</a></h2> (alias : create, add, join, register, signup, subscribe)</li>
	<li><h2><a href=\"/registration/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/registration/delete/help\">delete</a></h2> (alias : del, remove, cancel)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>