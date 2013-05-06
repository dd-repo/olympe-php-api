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
		request::forward('/user/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		request::forward('/user/select'); break;
	case 'update':
	case 'modify':
	case 'change':
		request::forward('/user/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		request::forward('/user/delete'); break;
	case 'group':
	case 'groups':
		request::forward('/group/user/index'); break;
	case 'grant':
	case 'grants':
		request::forward('/grant/user/index'); break;
	case 'quota':
	case 'quotas':
		request::forward('/quota/user/index'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: user</h1>
<ul>
	<li><h2><a href=\"/user/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/user/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/user/update/help\">update</a></h2> (alias : modify, change)</li>
	<li><h2><a href=\"/user/delete/help\">delete</a></h2> (alias : del, remove, destroy)</li>
	<li><h2><a href=\"/group/user/help\">group</a></h2> (alias : groups)</li>
	<li><h2><a href=\"/grant/user/help\">grant</a></h2> (alias : grants)</li>
	<li><h2><a href=\"/quota/user/help\">quota</a></h2> (alias : quotas)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>