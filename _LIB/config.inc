<?php

// =================================
// CROSSDOMAIN
// =================================
if( preg_match("/^\\/?crossdomain.xml$/i", $_GET['__rewrite']) )
{
	header("Content-Type: text/xml");
	echo "<?xml version=\"1.0\"?><cross-domain-policy><allow-access-from domain=\"*\"/><site-control permitted-cross-domain-policies=\"all\"/><allow-http-request-headers-from domain=\"*\" headers=\"*\"/></cross-domain-policy>";
	exit;
}

// =================================
// DEFAULT PARAMETERS (OVERRIDE IN SITE CONFIG)
// =================================
$GLOBALS['CONFIG'] 					= array();
$GLOBALS['CONFIG']['CLASSPATH'] 	= __DIR__ . '/';
$GLOBALS['CONFIG']['ROOT'] 			= dirname($GLOBALS['CONFIG']['CLASSPATH']) . '/';
$GLOBALS['CONFIG']['APIDIR'] 		= $GLOBALS['CONFIG']['ROOT'] . 'api/';
$GLOBALS['CONFIG']['DEBUG'] 		= true;
$GLOBALS['CONFIG']['THROW_ERRORS'] 	= (E_ALL | E_STRICT) & ~E_NOTICE;

switch( $_SERVER["HTTP_HOST"] )
{
	case 'api.dev.olympe.in':
	case 'api.olympe.in':
		$conf = parse_ini_file('/dns/in/olympe/etc/settings/api.ini', true);
		break;
	case 'api.dev.sys.ht':
	case 'api.sys.ht':
		$conf = parse_ini_file('/dns/tm/sys/etc/settings/api.ini', true);
		break;
	case 'api.dev.bus-it.com':
	case 'api.bus-it.com':
		$conf = parse_ini_file('/dns/com/bus-it/etc/settings/api.ini', true);
		break;
	default:
		$conf = parse_ini_file('/dns/tm/sys/etc/settings/api.ini', true);
}

$services_json = getenv('VCAP_SERVICES');
$services = json_decode($services_json, true);
$mysql_config = $services['mysql-5.1'][0]['credentials'];

$GLOBALS['CONFIG']['DB_HOST'] 		= $mysql_config['hostname'];
$GLOBALS['CONFIG']['DB_PORT'] 		= $mysql_config['port'];
$GLOBALS['CONFIG']['DB_USERNAME'] 	= $mysql_config['user'];
$GLOBALS['CONFIG']['DB_PASSWORD'] 	= $mysql_config['password'];
$GLOBALS['CONFIG']['DB_DATABASE'] 	= $mysql_config['name'];
$GLOBALS['CONFIG']['API_HOST'] 		= 'https://' . $conf['Main']['API_HOST'];
$GLOBALS['CONFIG']['API_USERNAME']	= $conf['Main']['API_USERNAME'];
$GLOBALS['CONFIG']['API_PASSWORD']	= $conf['Main']['API_PASSWORD'];
$GLOBALS['CONFIG']['PIWIK_URL']		= $conf['Main']['PIWIK_URL'];
$GLOBALS['CONFIG']['PIWIK_TOKEN']	= $conf['Main']['PIWIK_TOKEN'];
$GLOBALS['CONFIG']['DOMAIN']		= $conf['Main']['DOMAIN'];
$GLOBALS['CONFIG']['JOB_SERVERS'] 	= $conf['Main']['JOB_SERVERS'];
$GLOBALS['CONFIG']['KEY'] 			= $conf['Main']['KEY'];
$GLOBALS['CONFIG']['SECRET'] 		= $conf['Main']['SECRET'];
$GLOBALS['CONFIG']['GPG_KEY'] 		= $conf['Main']['GPG_KEY'];

unset($conf);

$GLOBALS['CONFIG']['PARAMETERS'] 			= array();
$GLOBALS['CONFIG']['PARAMETERS']['FORMAT']	= array('f','format','output','outputformat','o');
$GLOBALS['CONFIG']['PARAMETERS']['ACTION']	= array('action');
$GLOBALS['CONFIG']['PARAMETERS']['NOHTTP']	= array('nohttperrorheader');
$GLOBALS['CONFIG']['PARAMETERS']['XML']		= array('xml','x');
$GLOBALS['CONFIG']['PARAMETERS']['JSON']	= array('json','j');
$GLOBALS['CONFIG']['PARAMETERS']['TOKEN']	= array('secret','auth','creditentials');

define('CONFIG_LOADED', true);

?>
