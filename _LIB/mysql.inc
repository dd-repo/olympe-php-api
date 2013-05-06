<?php

class mysql
{
	private $base;
	private $options = array();
	
	const NO_ROW = 1;
	const ONE_ROW = 2;
	const ANY_ROW = 4;
	
	public function __construct($host, $username, $password, $database, $port='3306')
	{
		$this->options['host'] = $host;
		$this->options['port'] = $port;
		$this->options['user'] = $username;
		$this->options['pass'] = $password;
		$this->options['data'] = $database;
	}
	
	private function connect()
	{
		// create a new mysql(improved) object
		$this->base = new mysqli($this->options['host'], $this->options['user'], $this->options['pass'], $this->options['data'], $this->options['port']);
		
		if( !is_object($this->base) || $this->base->connect_errno != 0 )
			throw new ApiException('Database connection failed', 500, 'Error ['.mysqli_connect_errno().'] : '.mysqli_connect_error());
			
		// set autocommit to true
		if( ! $this->base->autocommit(true))
			throw new ApiException('Database options failed', 500, 'Error ['.$this->base->errno.'] : '.$this->base->error);
			
		// set the correct character set
		if( !$this->base->set_charset("utf8"))
			throw new ApiException('Database options failed', 500, 'Error ['.$this->base->errno.'] : '.$this->base->error);
	}
	
	public function __destruct()
	{
		if( is_object($this->base) )
			$this->base->close();    
    }
	
	public function query($sql, $mode = self::ONE_ROW)
	{
		// read the class usage for a description of this function.
		$this->ping();
		
		switch( $mode )
		{
			case self::NO_ROW : $result = $this->base->query($sql, MYSQLI_STORE_RESULT); break;
			default :
			case self::ONE_ROW :
			case self::ANY_ROW : $result = $this->base->query($sql, MYSQLI_USE_RESULT); break;
		}
		
		if( $result === false )
			throw new ApiException('Database query failed', 500, 'Error ['.$this->base->errno.'] : '.$this->base->error);
		
		switch( $mode )
		{
			case self::NO_ROW :
				return $this->base->affected_rows;
			default :
			case self::ONE_ROW :
				$row = $result->fetch_assoc();
				$result->free();
				return $row;
			case self::ANY_ROW :
				$row = array();
				while( $r = $result->fetch_assoc() ) $row[] = $r;
				$result->free();
				return $row;
		}
	}
	
	public function last_id()
	{
		// returns the ID used by mysql for an INSERT with AUTO_INCREMENT
		return $this->base->insert_id;
	}
	
	private function ping()
	{
		if( !is_object($this->base) || !$this->base->ping() )
			$this->connect();
	}
}

?>
