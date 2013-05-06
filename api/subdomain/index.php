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
		request::forward('/subdomain/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		request::forward('/subdomain/select'); break;
	case 'update':
	case 'modify':
	case 'change':
		request::forward('/subdomain/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		request::forward('/subdomain/delete'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: subdomain</h1>
<ul>
	<li><h2><a href=\"/subdomain/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/subdomain/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/subdomain/update/help\">update</a></h2> (alias : modify, change)</li>
	<li><h2><a href=\"/subdomain/delete/help\">delete</a></h2> (alias : del, remove, destroy)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>