<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$action = request::getAction();
switch($action)
{
	case 'view':
	case 'whoami':
	case 'detail':
	case 'details':
	case 'list':
	case 'select':
		security::requireGrants(array('ACCESS', 'SELF_SELECT'));
		request::clearParam(array('name', 'user_name', 'username', 'login', 'user', 'names', 'user_names', 'usernames', 'logins', 'users', 'id', 'user_id', 'uid', 'ids', 'user_ids', 'uids'));
		request::addParam('user', security::getUser());
		grantStore::add('USER_SELECT');
		request::forward('/user/select');
		break;
	case 'update':
	case 'modify':
	case 'change':
		security::requireGrants(array('ACCESS', 'SELF_UPDATE'));
		request::clearParam(array('name', 'user_name', 'username', 'login', 'user', 'id', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('USER_UPDATE');
		request::forward('/user/update');
		break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
	case 'suicide':
		security::requireGrants(array('ACCESS', 'SELF_DELETE'));
		request::clearParam(array('name', 'user_name', 'username', 'login', 'user', 'id', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('USER_DELETE');
		request::forward('/user/delete');
		break;
	case 'quota':
	case 'quotas':
	case 'limit':
	case 'limits':
		security::requireGrants(array('ACCESS', 'SELF_QUOTA_SELECT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('QUOTA_USER_SELECT');
		request::forward('/quota/user/select');
		break;
	case 'access':
	case 'grant':
	case 'grants':
	case 'can':
	case 'check':
		security::requireGrants(array('ACCESS', 'SELF_GRANT_SELECT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		request::clearParam('overall');
		request::addParam('overall', 'true');
		grantStore::add('GRANT_USER_SELECT');
		request::forward('/grant/user/select');
		break;
	case 'token':
	case 'tokens':
		request::forward('/self/token/index');
		break;
	case 'group':
	case 'groups':
		request::forward('/self/group/index');
		break;
	case 'site':
	case 'sites':
		request::forward('/self/site/index');
	case 'domain':
	case 'domains':
		request::forward('/self/site/index');
	case 'subdomain':
	case 'subdomains':
		request::forward('/self/subdomain/index');
	case 'database':
	case 'databases':
		request::forward('/self/database/index');
	case 'account':
	case 'accounts':
		request::forward('/self/account/index');
	case 'app':
	case 'apps':
		request::forward('/self/app/index');
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: self</h1>
<ul>
	<li><h2><a href=\"/user/select/help\">select</a></h2> (alias : view, whoami, detail, details, list)</li>
	<li><h2><a href=\"/user/update/help\">update</a></h2> (alias : modify, change)</li>
	<li><h2><a href=\"/user/delete/help\">delete</a></h2> (alias : del, remove, destroy, suicide)</li>
	<li><h2><a href=\"/quota/user/select/help\">quota</a></h2> (alias : quotas, limit, limits)</li>
	<li><h2><a href=\"/grant/user/help\">grant</a></h2> (alias : access, check, grants, can)</li>
	<li><h2><a href=\"/self/token/help\">token</a></h2> (alias : tokens)</li>
	<li><h2><a href=\"/self/group/help\">group</a></h2> (alias : groups)</li>
	<li><h2><a href=\"/self/site/help\">site</a></h2> (alias : sites)</li>
	<li><h2><a href=\"/self/domain/help\">domain</a></h2> (alias : domains)</li>
	<li><h2><a href=\"/self/subdomain/help\">subdomain</a></h2> (alias : subdomains)</li>
	<li><h2><a href=\"/self/database/help\">database</a></h2> (alias : databases)</li>
	<li><h2><a href=\"/self/account/help\">account</a></h2> (alias : accounts)</li>
	<li><h2><a href=\"/self/app/help\">app</a></h2> (alias : apps)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>