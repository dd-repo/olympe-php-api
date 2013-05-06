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
		request::forward('/app/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		request::forward('/app/select'); break;
	case 'update':
	case 'modify':
	case 'change':
		request::forward('/app/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		request::forward('/app/delete'); break;
	case 'quota':
	case 'quotas':
		request::forward('/quota/app/index'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: app</h1>
<ul>
	<li><h2><a href=\"/app/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/app/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/app/update/help\">update</a></h2> (alias : modify, change)</li>
	<li><h2><a href=\"/app/delete/help\">delete</a></h2> (alias : del, remove, destroy)</li>
	<li><h2><a href=\"/app/site/help\">quota</a></h2> (alias : quotas)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>