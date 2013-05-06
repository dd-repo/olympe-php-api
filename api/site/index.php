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
		request::forward('/site/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		request::forward('/site/select'); break;
	case 'update':
	case 'modify':
	case 'change':
		request::forward('/site/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		request::forward('/site/delete'); break;
	case 'quota':
	case 'quotas':
		request::forward('/quota/site/index'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: site</h1>
<ul>
	<li><h2><a href=\"/site/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/site/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/site/update/help\">update</a></h2> (alias : modify, change)</li>
	<li><h2><a href=\"/site/delete/help\">delete</a></h2> (alias : del, remove, destroy)</li>
	<li><h2><a href=\"/quota/site/help\">quota</a></h2> (alias : quotas)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>