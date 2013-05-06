<?php

class request
{
	const NUMBER = 1;
	const LOWER = 2;
	const UPPER = 4;
	const ALPHA = 6;
	const ALPHANUM = 7;
	const SPACE = 8;
	const ACCENT = 16;
	const PUNCT = 32;
	const PHRASE = 63;
	const SPECIAL = 64;
	const NEWLINE = 128;
	const ALL = 255;
	private static function getRegex($rule)
	{
		$regex = "";
		if( ($rule & self::NUMBER) > 0 )
			$regex .= "0-9";
		if( ($rule & self::LOWER) > 0 )
			$regex .= "a-z";
		if( ($rule & self::UPPER) > 0 )
			$regex .= "A-Z";
		if( ($rule & self::SPACE) > 0 )
			$regex .= "\\x20";
		if( ($rule & self::ACCENT) > 0 )
			$regex .= "a]|\\x{c3}[\\x{80}-\\x{bf}]|[a"; // fix for UTF8. We assume that if accents are allowed, then the letter 'a' also is. This prevents an empty '[]' at the front or end of the regexp
		if( ($rule & self::PUNCT) > 0 )
			$regex .= "\\._\\-";
		if( ($rule & self::SPECIAL) > 0 )
			$regex .= "\\x21-\\x2f\\x3a-\\x40\\x5b-\\x60\\x7b-\\x7e]|\\x{c2}[\\x{a1}-\\x{bf}]|[a"; // fix for UTF8. We assume that if special characters are allowed, then the letter 'a' also is. This prevents an empty '[]' at the end of the regexp
		if( ($rule & self::NEWLINE) > 0 )
			$regex .= "\\r\\n";
		
		if( strlen($regex) == 0 )
			throw new ApiException("Internal parameter check error", 500, "Cannot check on an empty regex");

		return '(['.$regex.'])*';
	}
	
	//=====================================
	// HANDLE PARAMS
	//=====================================
	private static $_params = array();
	
	public static function hasParam($name)
	{
		if( is_array($name) )
		{
			foreach($name as $n)
				if( self::hasParam($n) === true )
					return true;
			return false;
		}
		else
			return isset(self::$_params[strtolower($name)]);
	}
	
	public static function addParam($name, $value)
	{
		if( is_array($name) )
		{
			$v = array_values($name);
			$name = $v[0];
		}
		
		$name = strtolower($name);
		if( isset(self::$_params[$name]) )
		{
			$existing = self::$_params[$name];
			if( is_array($value) && is_array($existing) )
				$existing = array_merge($existing, $value);
			else if( !is_array($value) && is_array($existing) )
				$existing = array_merge($existing, preg_split("/\\s*(,|;|\\s)\\s*/", $value, -1, PREG_SPLIT_NO_EMPTY));
			else if( !is_array($value) && !is_array($existing) )
				$existing = $existing . ';' . $value;
			else if( is_array($value) && !is_array($existing) )
				$existing = array_merge(preg_split("/\\s*(,|;|\\s)\\s*/", $existing, -1, PREG_SPLIT_NO_EMPTY), $value);
			self::$_params[$name] = $existing;
		}
		else
			self::$_params[$name] = $value;
	}
	
	public static function getParam($name)
	{
		if( is_array($name) )
		{
			$params = null;
			foreach($name as $n)
			{
				$param = self::getParam($n);
				if( $param !== null )
				{
					if( $params === null )
						$params = $param;
					else if( is_array($param) && is_array($params) )
						$params = array_merge($params, $param);
					else if( !is_array($param) && is_array($params) )
						$params = array_merge($params, preg_split("/\\s*(,|;|\\s)\\s*/", $param, -1, PREG_SPLIT_NO_EMPTY));
					else if( !is_array($param) && !is_array($params) )
						$params = $params . ';' . $param;
					else if( is_array($param) && !is_array($params) )
						$params = array_merge(preg_split("/\\s*(,|;|\\s)\\s*/", $params, -1, PREG_SPLIT_NO_EMPTY), $param);
				}
			}
			return $params;
		}
		else
		{
			$name = strtolower($name);
			if( isset(self::$_params[$name]) && ((is_array(self::$_params[$name]) && count(self::$_params[$name]) > 0) || strlen(self::$_params[$name]) > 0) )
				return self::$_params[$name];
		}
		
		return null;
	}
	
	public static function clearParam($name)
	{
		if( is_array($name) )
		{
			foreach($name as $n)
				self::clearParam($n);
		}
		else
		{
			$name = strtolower($name);
			if( isset(self::$_params[$name]) )
				unset(self::$_params[$name]);
		}
	}
	
	public static function getCheckParam($rules)
	{
		if( !isset($rules['name']) )
			return null;
		if( !is_array($rules['name']) )
			$rules['name'] = array($rules['name']);
		$p = self::getParam($rules['name']);
		
		if( isset($rules['action']) && $rules['action'] == true && $p === null )
			$p = self::getAction();
		
		if( isset($rules['array']) && $rules['array'] == true )
		{
			if( !is_array($p) )
			{
				if( isset($rules['delimiter']) && preg_match("/{$rules['delimiter']}/", $p) )
					$p = preg_split("/{$rules['delimiter']}/", $p);
				else
					$p = array($p);
			}
			
			if( isset($rules['mincount']) && count($p) < $rules['mincount'] )
				throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} count is too low ({$rules['mincount']}) : ".count($p));
			if( isset($rules['maxcount']) && count($p) > $rules['maxcount'] )
				throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} count is too high ({$rules['maxcount']}) : ".count($p));
		}
		else if( is_array($p) )
			throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} does not accept multiple values");

		if( !isset($rules['optional']) || $rules['optional'] == false )
		{
			if( is_array($p) )
			{
				if( count($p) == 0 )
					throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} is not optional");

				foreach( $p as $p2 )
					if( $p2 === null )
						throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} is not optional");
			}
			else if( $p === null )
				throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} is not optional");
		}
		else if( (is_array($p) && count($p) == 0) || $p === null )
			return $p;
		else if( is_array($p) )
		{
			// remove all empty array elements
			foreach( $p as $key=>$p2 )
				if( $p2 == null || strlen($p2) == 0 )
					unset($p[$key]);
		}
		
		if( isset($rules['minlength']) )
		{
			if( is_array($p) )
			{
				foreach( $p as $p2 )
					if( (!isset($rules['optional']) || $rules['optional'] == false) && strlen($p2) < $rules['minlength'] )
						throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} is too short ({$rules['minlength']} : {$p2}");
			}
			else if( strlen($p) < $rules['minlength'] )
				throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} is too short ({$rules['minlength']} : {$p}");
		}
		
		if( isset($rules['maxlength']) )
		{
			if( is_array($p) )
			{
				foreach( $p as $p2 )
					if( strlen($p2) > $rules['maxlength'] )
						throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} is too long ({$rules['maxlength']} : {$p2}");
			}
			else if( strlen($p) > $rules['maxlength'] )
				throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} is too long ({$rules['maxlength']}) : {$p}");
		}
		
		if( isset($rules['match']) )
		{
			if( is_numeric($rules['match']) )
				$regex = self::getRegex($rules['match']);
			else
				$regex = $rules['match'];
			
			if( is_array($p) )
			{
				foreach( $p as $p2 )
					if( !preg_match("/^{$regex}$/s", $p2) ) // Caution, do NOT set the '/u' flag otherwise UTF8 will NOT pass. See request::getRegex() for details
						throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} does not match /^{$regex}$/s : {$p2}");
			}
			else if( !preg_match("/^{$regex}$/s", $p) ) // Caution, do NOT set the '/u' flag otherwise UTF8 will NOT pass. See request::getRegex() for details
				throw new ApiException("Parameter validation failed", 412, "Parameter {$rules['name'][0]} does not match /^{$regex}$/s : {$p}");
		}
		
		return $p;
	}
	
	//=====================================
	// HANDLE ACTION
	//=====================================
	private static $_action = array();
	
	public static function hasAction()
	{
		return count(self::$_action) > 0;
	}
	
	public static function addAction($action, $back = true)
	{
		$action = strtolower($action);
		if( $back )
			array_push(self::$_action, $action);
		else
			array_unshift(self::$_action, $action);
	}
	
	public static function getAction($back = false, $pop = true)
	{
		if( $pop )
		{
			if( $back )
				return array_pop(self::$_action);
			else
				return array_shift(self::$_action);
		}
		else 
		{
			if( count(self::$_action) == 0 )
				return null;
			if( $back )
				return self::$_action[count(self::$_action)-1];
			else
				return self::$_action[0];
		}
	}
	
	//=====================================
	// GENERIC STUFF
	//=====================================
	public static function forward($page)
	{
		require_once($GLOBALS['CONFIG']['APIDIR'] . $page . '.php');
	}
}

?>