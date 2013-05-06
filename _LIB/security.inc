<?php

class grantStore
{
	private static $volatile = array();
	
	public static function add($grant)
	{
		if( !is_array($grant) )
			$grant = array($grant);
		
		foreach( $grant as $g )
			if( !in_array($g, self::$volatile) )
				self::$volatile[] = $g;
	}
	
	public static function has($grant)
	{
		if( !is_array($grant) )
			$grant = array($grant);
		
		foreach( $grant as $g )
			if( !in_array($g, self::$volatile) )
				return false;
		
		return true;
	}
}

class security
{
	public static function requireGrants($grants=array())
	{
		if( !self::hasGrants($grants) )
			throw new ApiException('Unsufficient privileges', 403, 'Not all required grants available : ' . implode($grants, ', '));
	}
	
	public static function hasGrants($grants=array(), $token=null)
	{
		if( $token == null )
			$token = request::getParam($GLOBALS['CONFIG']['PARAMETERS']['TOKEN']);
		
		// check if there is a temporary pass trough
		$remain = array();
		for( $i = 0; $i < count($grants); $i++ )
		{
			if( !grantStore::has($grants[$i]) )
				$remain[] = $grants[$i];
		}
		$grants = $remain;
		if( count($grants) == 0 )
			return true;
		
		$token = strtolower($token);
		if( !preg_match("/^[a-z0-9\\._\\-]{3,30}:[a-z0-9]{32,32}$/", $token) )
			return false;
		
		// split the token and the user
		$token = explode(':', $token);
		$user = $token[0];
		$token = $token[1];
		
		$grantlist = '';
		foreach( $grants as $grant )
		{
			if( !preg_match("/^[a-zA-Z0-9_]{1,254}$/", $grant) )
				throw new ApiException('', 500, 'Invalid grant name : ' . $grant);
			$grantlist .= $grant . "','";
		}
		
		$sql = "SELECT COUNT(DISTINCT g.grant_id) as grant_count FROM users u
				LEFT JOIN tokens t ON(t.token_user = u.user_id)
				LEFT JOIN token_grant tg ON(t.token_id = tg.token_id)
				LEFT JOIN grants g ON(g.grant_id = tg.grant_id)
				WHERE t.token_value = '{$token}' AND u.user_name = '".security::escape($user)."'
				AND (t.token_lease > UNIX_TIMESTAMP() OR t.token_lease = 0)
				AND g.grant_name IN('{$grantlist}')";
		$result = $GLOBALS['db']->query($sql);
		
		if( !$result || $result['grant_count'] != count($grants) )
			return false;
		else
			return true;
	}
	
	private static $getUserCache = null;
	public static function getUser($field=null, $token=null)
	{
		if( $token == null )
			$token = request::getParam($GLOBALS['CONFIG']['PARAMETERS']['TOKEN']);

		if( $token == null )
			return null;

		$token = strtolower($token);
		if( !preg_match("/^[a-zA-Z0-9\\._\\-]{3,30}:[a-zA-Z0-9]{32,32}$/", $token) )
			throw new ApiException('Invalid token', 403, 'Invalid token : ' . $token);

		// split the token and the user
		$token = explode(':', $token);
		$user = $token[0];
		$token = $token[1];
		
		// check the user data cache
		if( isset(self::$getUserCache[$user]) )
		{
			if( $field != null && isset(self::$getUserCache[$user][$field]) )
				return self::$getUserCache[$user][$field];
			else
				return self::$getUserCache[$user]['user_id'];
		}
		else
		{
			$sql = "SELECT u.* FROM users u
					LEFT JOIN tokens t ON(t.token_user = u.user_id)
					WHERE t.token_value = '{$token}' AND u.user_name = '".security::escape($user)."'
					AND (t.token_lease > UNIX_TIMESTAMP() OR t.token_lease = 0)";
			$result = $GLOBALS['db']->query($sql);
			
			if( !$result )
				throw new ApiException('Invalid token', 403, 'Invalid token : ' . $token);
			
			// store in the user data cache
			self::$getUserCache[$user] = $result;
			
			if( $field != null && isset($result[$field]) )
				return $result[$field];
			else
				return $result['user_id'];
		}
	}
	
	public static function getToken($token=null)
	{
		if( $token == null )
			$token = request::getParam($GLOBALS['CONFIG']['PARAMETERS']['TOKEN']);

		if( $token == null )
			return null;

		$token = strtolower($token);
		if( !preg_match("/^[a-zA-Z0-9\\._\\-]{3,30}:[a-zA-Z0-9]{32,32}$/", $token) )
			throw new ApiException('Invalid token', 403, 'Invalid token : ' . $token);
		
		// split the token and the user
		$token = explode(':', $token);
		return $token[1];
	}
	
	public static function encode($text, $escapeall = false, $escapeslashes = true)
	{
		// WARNING : mysql NEEDS escaped \'s so we assume that $escapeslashes is true by default.
		// Thus we ADD the \ if it is not set by the magic_quotes_gpc.
		// If explicitrly specified, we can strip the escaped \'s by the magic_quotes_gpc()
			
		// returns a SQL safe text with html line breaks
		if( get_magic_quotes_gpc() && !$escapeslashes )
			$text = stripslashes($text);
		if( !get_magic_quotes_gpc() && $escapeslashes )
			$text = addslashes($text);
		
		if( $escapeall )
			return preg_replace('/\s\s+/', ' ',nl2br(htmlentities(trim($text),ENT_QUOTES, 'UTF-8')));
		else
			return preg_replace('/\s\s+/', ' ',nl2br(htmlspecialchars(trim($text),ENT_QUOTES, 'UTF-8')));
	}
	
	public static function escape($text)
	{
		return preg_replace("/(\\x00|\\n|\\r|'|\"|\\\\|\\x1a)/", "\\\\\\1", $text);
	}
}

?>
