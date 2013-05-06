<?php

// if the config, core or index did not load properly : exit
if( !defined('CONFIG_LOADED') || !defined('CORE_LOADED') || !defined('INIT_LOADED') )
	throw new Exception('', 500);

// =================================
// CHECK URL
// =================================
if( !isset($_GET['__rewrite']) || !preg_match("/^[a-zA-Z0-9\\/_\\-\\.,;\\s]*$/i", $_GET['__rewrite']) )
	throw new ApiException('', 400, 'URL contains invalid characters : ' . $_GET['__rewrite']);
$uri = '/'.$_GET['__rewrite'];
unset($_GET['__rewrite']);

// =================================
// MERGE GET
// =================================
foreach( $_GET as $key=>$value )
	request::addParam($key, $value);
$_GET = array();
unset($_GET);

// =================================
// MERGE POST
// =================================
foreach( $_POST as $key=>$value )
	request::addParam($key, $value);
$_POST = array();
unset($_POST);

// =================================
// MERGE JSON
// =================================
$json = request::getParam($GLOBALS['CONFIG']['PARAMETERS']['JSON']);
request::clearParam($GLOBALS['CONFIG']['PARAMETERS']['JSON']);
if( $json !== null && strlen($json) > 2 )
{
	//$jsons = preg_split("/\\}\\s*(,|;|\\s|\\/)\\s*\\{/", $json, -1, PREG_SPLIT_NO_EMPTY);
	//foreach( $jsons as $j )
	//{
		$json = json_decode($json, true);
		if( !is_array($json) )
			throw new ApiException('', 400, 'Json parsing failed');
		
		foreach( $json as $key=>$value )
			request::addParam($key, $value);
	//}
}

// =================================
// MERGE XML
// =================================
$xml = request::getParam($GLOBALS['CONFIG']['PARAMETERS']['XML']);
request::clearParam($GLOBALS['CONFIG']['PARAMETERS']['XML']);
if( $xml !== null && strlen($xml) > 3 )
{
	//$xmls = preg_split("/>\\s*(,|;|\\s|\\/)\\s*</", $xml, -1, PREG_SPLIT_NO_EMPTY);
	//foreach( $xmls as $x )
	//{
		$xml = xml::parse($xml);
		if( !is_array($xml) )
			throw new ApiException('', 400, 'Xml parsing failed');
		
		foreach( $xml as $key=>$value )
			request::addParam($key, $value);
	//}
}

// =================================
// CHECK OUTPUT
// =================================
responder::detectFormat();

// =================================
// MERGE ACTION
// =================================
$action = request::getParam($GLOBALS['CONFIG']['PARAMETERS']['ACTION']);
request::clearParam($GLOBALS['CONFIG']['PARAMETERS']['ACTION']);
if( $action !== null )
{
	if( !preg_match("/^[a-zA-Z0-9\\/_\\-\\.,;\\s]*$/i", $action) )
		throw new ApiException('', 400, 'The action parameter contains invalid characters : ' . $action);
	$uri .= '/'.$action;
}
$uri = rtrim($uri, "/");
if( strlen($uri) == 0 || $uri == '/' )
	$uri = "/help";
$uri = strtolower($uri);

// the user is trying to mess up with ../
if( preg_match("/(^|\\/|,|;|\\s)\\.+($|\\/|,|;|\\s)/", $uri) !== 0 )
	throw new ApiException('', 400, 'Directory browsing attempt detected : ' . $uri);

// =================================
// FIND PAGE
// =================================
$path = $uri;
$include = null;
while( strlen($path) > 0 )
{
	if( file_exists($GLOBALS['CONFIG']['APIDIR'] . $path . '.php') )
	{
		$action = preg_split("/\\s*(,|;|\\s|\\/)\\s*/", substr($uri, strlen($path)), -1, PREG_SPLIT_NO_EMPTY);
		foreach( $action as $a ) request::addAction($a);
		$include = $GLOBALS['CONFIG']['APIDIR'] . $path . '.php';
		break;
	}
	else if( is_dir($GLOBALS['CONFIG']['APIDIR'] . $path) && file_exists($GLOBALS['CONFIG']['APIDIR'] . $path . '/index.php') )
	{
		$action = preg_split("/\\s*(,|;|\\s|\\/)\\s*/", substr($uri, strlen($path)), -1, PREG_SPLIT_NO_EMPTY);
		foreach( $action as $a ) request::addAction($a);
		$include = $GLOBALS['CONFIG']['APIDIR'] . $path . '/index.php';
		break;
	}
	else if( $path == '.' || $path == '/' )
		break;
	
	$path = dirname($path);
}

// =================================
// LOAD PAGE
// =================================
if( $include !== null )
{
	define('PROPER_START', true);
	require_once($include);
}
	throw new ApiException('', 404, 'No handler found for URI : ' . $uri);

?>