<?php

class ApiException extends Exception
{
	private $debug;
	
	public function __construct($message="", $code=500, $debug="", $ex=null)
	{
		if( $ex != null )
		{
			$e = $ex.'';
		
			// normal exception
			if( preg_match("/^exception '[a-zA-Z0-9_]*' with message '(.*?)' in /", $e, $matches) )
				$message .= ' :: ' . $matches[1];
			// Site/Api exception
			else if( preg_match("/^(.+)\nStack trace:/s", $e, $matches) )
				$message .= ' :: ' . $matches[1];
			// other
			else
				$message .= ' :: ' . $e;

			// get current line
			if( preg_match("/' in (.+)$/m", $e, $matches) )
			{
				$debug .= "\n##- ".$matches[1];
			}
			
			// get stack trace
			if( preg_match("/Stack trace:\n(.+)$/s", $e, $matches) )
			{
				$debug .= "\n".preg_replace("/^(#.+?)$/m", "#\$1", $this->stripArgs($matches[1]));
			}
		}
		
		parent::__construct($message, $code);
		$this->debug = $debug;
	}
	
	public function __toString()
	{
		if( $GLOBALS['CONFIG']['DEBUG'] )
		{
			$error = $this->getMessage();
			$error .= "\nStack trace:\n";
			$error .= $this->debug."\n#- ".$this->getFile().":".$this->getLine()."\n";
			$error .= $this->stripArgs($this->getTraceAsString());
			return $error;
		}
		else
		{
			return $this->getMessage();
		}
	}
	
	public function getDebug()
	{
		return $this->debug;
	}
	
	private function stripArgs($stacktrace)
	{
		return preg_replace("/^(#.+?: .*?)\\(.*?\\)$/m", "\$1()", $stacktrace);
	}
}

?>