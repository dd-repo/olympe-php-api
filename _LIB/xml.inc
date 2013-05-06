<?php

class xml
{
	private static $cdata;
	
	public static function encode($object, $depth=0)
	{
		$xml = '';
		$pad = "\t";
		for( $i = 0; $i < $depth; $i++ ) $pad .= "\t";
		
		if( $depth == 0 )
			$xml .= "<response>\n";

		if( !is_array($object) )
			$xml .= $pad . "<![CDATA[" . $object . "]]>\n";
		else
		{
			foreach( $object as $key=>$value )
			{
				if( self::is_array_strict($value) )
				{
					foreach( $value as $v )
						$xml .= "{$pad}<".(is_numeric($key)?'item_':'')."{$key}>\n" . self::encode($v, $depth+1) . "{$pad}</".(is_numeric($key)?'item_':'')."{$key}>\n";
				}
				else
					$xml .= "{$pad}<".(is_numeric($key)?'item_':'')."{$key}>\n" . self::encode($value, $depth+1) . "{$pad}</".(is_numeric($key)?'item_':'')."{$key}>\n";
			}
		}
		
		if( $depth == 0 )
			$xml .= "</response>";
		
		return $xml;
	}
	
	private static function is_array_strict($array)
	{
		// checks if the array is strictly a indexed one (not assoc)
		if( !is_array($array) )
			return false;
		
		for( reset($array); is_int(key($array)); next($array) ); 
		return is_null(key($array)); 
	}
	
	public static function parse($xml)
	{
		$xml = self::extractCDATA($xml);
		$assoc = self::recurse($xml);
		
		if( count($assoc) == 1 )
		{
			$keys = array_keys($assoc);
			$root = $keys[0];
			if( is_array($assoc[$root]) )
				return $assoc[$root]; // ommit the root node
			else
				return $assoc;
		}
		else
			return $assoc;
	}
	
	private static function extractCDATA($xml)
	{
		self::$cdata = array();
		return preg_replace_callback("/<!\\[CDATA\\[\\s*(.*?)\\s*\\]\\]>/s", 'self::extractCDATAcallback', $xml);
	}
	
	private static function extractCDATAcallback($found)
	{
		self::$cdata[] = $found[1];
		return '#@_'.(count(self::$cdata)-1).'_@#';
	}
	
	private static function insertCDATA($xml)
	{
		return preg_replace_callback("/#@_[0-9]+_@#/s", 'xml::insertCDATAcallback', $xml);
	}
	
	private static function insertCDATAcallback($found)
	{
		$index = substr($found[0], 3, -3);
		if( isset(self::$cdata[$index]) )
			return self::$cdata[$index];
		else
			return $found;
	}
	
	private static function recurse($xml)
	{
		if( preg_match_all("/<\\s*([a-z0-9_\\.-]+)\\s*>\\s*(.*?)\\s*<\\/\\s*\\1\\s*>/is", $xml, $matches, PREG_SET_ORDER) == 0 )
			return self::insertCDATA($xml);
		
		$node = array();
		foreach( $matches as $m )
		{
			$key = $m[1];
			$value = $m[2];
			if( isset($node[$key]) )
			{
				if( is_array($node[$key]) )
					$node[$key][] = self::recurse($value);
				else
					$node[$key] = array($node[$key], self::recurse($value));
			}
			else
				$node[$key] = self::recurse($value);
		}
		return $node;
	}
}

?>