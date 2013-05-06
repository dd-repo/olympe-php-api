<?php

class responder
{
	const JSON = 1;
	const XML = 2;
	const PHP = 4;
	const DUMP = 8;
	
	private static $currentFormat = 0;
	public static $defaultFormat = 1; // default to JSON
	
	public static function send($result=null, $format=0, $andexit=true)
	{
		if( $result === null )
		{
			if( $andexit )
				exit;
			return;
		}
		
		if( $format > 0 )
			self::$currentFormat == $format;

		if( $result instanceof Exception )
		{
			self::sendError($result, $format);
			return;
		}
		
		switch(self::$currentFormat)
		{
			case self::JSON: echo json_encode(array('response'=>$result)); break;
			case self::XML: echo xml::encode($result); break;
			case self::PHP: echo serialize($result); break;
			case self::DUMP: echo print_r($result, true); break;
		}
		
		if( $andexit )
			exit;
	}
	
	public static function help($body)
	{
		echo "<!DOCTYPE html>
<html>
<head>
<title>API Help</title>
<style>
	li { list-style-type: circle; }
	ul li > ul { border : 1px dashed #CCCCCC; padding: 10px; margin: 0px; margin-left: 25px; }
	ul li > ul li { margin-left: 20px; }
	ul li > ul li > ul { border: 0px none white; }
	h3 { margin: 0px; display: inline; font-size: 1em; font-weight: normal; font-style: italic; }
	h2 { margin: 0px; display: inline; font-size: 1.3em;}
	h1 { margin: 0px; font-size: 1.5em; }
	span.required { font-weight: bold; color: #FF7575; }
	span.urlizable { font-weight: bold; color: #7979FF; }
	span.multiple { font-weight: bold; color: #27DE55; }
	span.optional { font-weight: bold; color: #DFE32D; }
</style>
</head>
<body>
{$body}
</body>
</html>";

		exit;
	}
	
	public static function detectFormat()
	{
		$format = request::getParam($GLOBALS['CONFIG']['PARAMETERS']['FORMAT']);
		if( $format === null )
		{
			self::$currentFormat = self::$defaultFormat;
			return;
		}

		switch(strtolower($format))
		{
			case 'json': header("Content-Type: application/json"); self::$currentFormat = self::JSON; return;
			case 'xml': header("Content-Type: text/xml"); self::$currentFormat = self::XML; return;
			case 'php': header("Content-Type: text/plain"); self::$currentFormat = self::PHP; return;
			case 'dump': header("Content-Type: text/plain"); self::$currentFormat = self::DUMP; return;
			default:
				throw new ApiException('', 400, 'Invalid output format : ' . $format);
		}
	}
	
	private static function sendError($e)
	{
		$nohttp = request::getParam($GLOBALS['CONFIG']['PARAMETERS']['NOHTTP']);

		// check if the error should send a HTTP header
		if( $nohttp === null || !$nohttp )
		{
			switch($e->getCode())
			{
				case 200: header("HTTP/1.1 200 OK"); break;
				case 400: header("HTTP/1.1 400 Bad Request"); break;
				case 401: header("HTTP/1.1 401 Unauthorized"); break;
				case 403: header("HTTP/1.1 403 Forbidden"); break;
				case 404: header("HTTP/1.1 404 Not Found"); break;
				case 405: header("HTTP/1.1 405 Method Not Allowed"); break;
				case 406: header("HTTP/1.1 406 Not Acceptable"); break;
				case 409: header("HTTP/1.1 409 Conflict"); break;
				case 412: header("HTTP/1.1 412 Precondition Failed"); break;
				default:
				case 500: header("HTTP/1.1 500 Internal Server Error"); break;
				case 501: header("HTTP/1.1 501 Not Implemented"); break;
				case 503: header("HTTP/1.1 503 Service Unavailable"); break;
			}
		}
		
		$error = array('error'=>array('code'=>$e->getCode(), 'message'=>($GLOBALS['CONFIG']['DEBUG']?$e->__toString():$e->getMessage())));
		
		switch(self::$currentFormat)
		{
			case self::JSON: echo json_encode($error); break;
			case self::XML: echo xml::encode($error); break;
			case self::PHP: echo serialize($error); break;
			case self::DUMP: echo print_r($error, true); break;
		}
	}
}

?>