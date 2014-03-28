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
		security::requireGrants(array('ACCESS', 'SELF_SITE_INSERT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('SITE_INSERT');
		request::forward('/site/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		security::requireGrants(array('ACCESS', 'SELF_SITE_SELECT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('SITE_SELECT');
		request::forward('/site/select'); break;
	case 'update':
	case 'modify':
	case 'change':
		security::requireGrants(array('ACCESS', 'SELF_SITE_UPDATE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('SITE_UPDATE');
		request::forward('/site/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		security::requireGrants(array('ACCESS', 'SELF_SITE_DELETE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('SITE_DELETE');
		request::forward('/site/delete'); break;
	case 'response':
	case 'responsetime':
	case 'time':
	case 'delay':
		security::requireGrants(array('ACCESS', 'SELF_SITE_SELECT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('SITE_SELECT');
		request::forward('/site/response'); break;
	case 'setrate':
		security::requireGrants(array('ACCESS', 'SELF_SITE_UPDATE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('SITE_UPDATE');
		request::forward('/site/setrate'); break;
	case 'getrate':
		security::requireGrants(array('ACCESS', 'SELF_SITE_SELECT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('SITE_SELECT');
		request::forward('/site/getrate'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/self/help\">self</a> :: site</h1>
<ul>
	<li><h2><a href=\"/site/insert/help?f=html\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/site/select/help?f=html\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/site/update/help?f=html\">update</a></h2> (alias : modify, change)</li>
	<li><h2><a href=\"/site/delete/help?f=html\">delete</a></h2> (alias : del, remove, destroy)</li>
	<li><h2><a href=\"/site/reponse/help?f=html\">reponse</a></h2> (alias : reponse, reponsetime, time, delay)</li>
	<li><h2><a href=\"/site/setrate/help?f=html\">setrate</a></h2> (alias : setrate)</li>
	<li><h2><a href=\"/site/getrate/help?f=html\">getrate</a></h2> (alias : getrate)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>