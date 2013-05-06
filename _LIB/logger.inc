<?php

class logger
{
	public static function log($message)
	{
		error_log($message);
	}
}

?>
