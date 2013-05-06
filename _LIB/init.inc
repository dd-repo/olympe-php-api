<?php

if( $GLOBALS['CONFIG']['DB_HOST'] )
{
	$GLOBALS['db'] = new mysql($GLOBALS['CONFIG']['DB_HOST'], 
								$GLOBALS['CONFIG']['DB_USERNAME'], 
								$GLOBALS['CONFIG']['DB_PASSWORD'], 
								$GLOBALS['CONFIG']['DB_DATABASE'], 
								$GLOBALS['CONFIG']['DB_PORT']);
}
				
$GLOBALS['security'] = new security();

define('INIT_LOADED', true);

?>
