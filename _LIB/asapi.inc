<?php

class asapi
{
	public static function send($uri, $method='GET', $params=array(), $login=null, $password=null)
	{
		if( $login === null )
			$params['login'] = $GLOBALS['CONFIG']['API_USERNAME'];
		else
			$params['login'] = $login;
		
		if( $password === null )
			$params['password'] = base64_encode($GLOBALS['CONFIG']['API_PASSWORD']);
		else
			$params['password'] = base64_encode($password);
	
		$uri = ltrim(rtrim($uri, '/'), '/');
		$uri = $GLOBALS['CONFIG']['API_HOST'] . '/' . $uri . '/';
		
		$request = array( 'http' => array( 'user_agent' => 'PHP/5.x (Olympe) API/1.0', 'method' => $method, 'timeout' => 10.0 ));
		if( $method == 'GET' )
			$uri .= '?'.http_build_query($params);
		else //if( $method == 'POST' || $method == 'DELETE' || $method == 'PUT' )
		{
			$request['http']['content'] = http_build_query($params);
			$request['http']['header'] = 'Content-Length: ' . strlen($request['http']['content']);
		}

		try
		{
			$fh = fopen($uri, 'r', false, stream_context_create( $request ));
			if( $fh === false )
				throw new ApiException("The upstream API did not respond", 500, "Failed opening API url : " . $uri);

			$response = stream_get_contents($fh);
			fclose($fh);
			return json_decode($response, true);
		}
		catch(Exception $e)
		{
			// get the E_WARNING from the fopen
			$ex = preg_replace("/fopen\\((.*?)\\)/", "fopen(-http stream-)", $e.'');
			throw new ApiException("Upstream API failure", 500, "Upstream API failure", $ex);
		}
	}
}

?>