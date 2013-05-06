<?php

class ip
{
	// IPV6 is aaaa:bbbb:cccc:dddd:eeee:ffff:gggg:hhhh
	private $object = 0; // aaaa:bbbb:cccc
	private $interface = 0; // dddd
	private $instance = 0; // eeee:ffff:gggg:hhhh
	
	// CAUTION : when comparing with > or < then PHP considers the first bit as the SIGN BIT.
	// therefore, do never compare with 0xFxxx.xxxx.xxxx.xxxx but with 0x7xxx.xxxx.xxxx.xxxx !
	// CAUTION : this also applies to bitwise masks & or | which do not consider the SIGN BIT and set the result to 0 !
	// However, applying equality operator with == or != works !

	public function __construct($ip = null)
	{
		if( $ip === null )
			return;
			
		if( !preg_match('/^([0-9a-f]{4}(:|$)){8}$/i', $ip) )
			throw new Exception('Invalid ip');
		
		$this->setObject(substr($ip, 0, 14));
		$this->setInterface(substr($ip, 15, 4), true);
		$this->setInstance(substr($ip, 20));
	}

	public function setObject($object)
	{
		if( !is_numeric($object) )
		{
			if( !preg_match('/^([0-9a-f]{4}):([0-9a-f]{4}):([0-9a-f]{4})$/i', $object, $matches) )
				throw new Exception('Invalid object id');
			
			$object = hexdec($matches[1]) << 32 | hexdec($matches[2]) << 16 | hexdec($matches[3]);
		}
		
		if( $object > 0xFFFFFFFFFFFF )
			throw new Exception('Object out of range');
		
		$this->object = $object & 0xFFFFFFFFFFFF;
	}

	public function setInstance($instance)
	{
		if( !is_numeric($instance) )
		{
			if( !preg_match('/^([0-9a-f]{4}):([0-9a-f]{4}):([0-9a-f]{4}):([0-9a-f]{4})$/i', $instance, $matches) )
				throw new Exception('Invalid instance id');
			
			$instance = hexdec($matches[1]) << 48 | hexdec($matches[2]) << 32 | hexdec($matches[3]) << 16 | hexdec($matches[4]);
		}
		
		// instance cannot be out of range because it uses the full int64 range
		// throw new Exception('Instance out of range');
		
		$this->instance = $instance;// & 0xFFFFFFFFFFFFFFFF;
	}

	public function setInterface($interface, $hex = false)
	{
		if( $hex || !is_numeric($interface) )
		{
			if( !preg_match('/^[0-9a-f]{4}$/i', $interface) )
				throw new Exception('Invalid interface id');

			$interface = hexdec($interface);
		}
		
		if( ($interface > 0xFF && $interface > 0xFF00) || ($interface < 0x100 && $interface < 0x0) )
			throw new Exception('Interface out of range');
		
		if( $interface > 0xFF )
			$this->interface = $interface & 0xFF00;
		else
			$this->interface = $interface & 0xFF;
	}

	public function setInput($input)
	{
		if( !is_numeric($input) )
		{
			if( !preg_match('/^[0-9a-f]{2}$/i', $input) )
				throw new Exception('Invalid input id');

			$input = hexdec($input);
		}
		
		if( $input < 0x0 || $input > 0xFF )
			throw new Exception('Input out of range');

		$this->interface = $input & 0xFF;
	}

	public function setOutput($output)
	{
		if( !is_numeric($output) )
		{
			if( !preg_match('/^[0-9a-f]{2}$/i', $output) )
				throw new Exception('Invalid output id');

			$output = hexdec($output);
		}
		
		if( $output < 0x1 || $output > 0xFF )
			throw new Exception('Output out of range');
			
		$this->interface = $output << 8 & 0xFF00;
	}

	public function __toString()
	{
		$ip = 
			str_pad(dechex($this->object >> 32 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
			str_pad(dechex($this->object >> 16 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' .
			str_pad(dechex($this->object & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' .
			str_pad(dechex($this->interface),4,'0',STR_PAD_LEFT) . ':' .
			str_pad(dechex($this->instance >> 48 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
			str_pad(dechex($this->instance >> 32 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
			str_pad(dechex($this->instance >> 16 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
			str_pad(dechex($this->instance & 0xFFFF),4,'0',STR_PAD_LEFT);
		return $ip;
	}
	
	public function ipv6()
	{
		return $this->__toString();
	}

	public function hex()
	{
		return array(
			'object'=>str_pad(dechex($this->object >> 32 & 0xFFF),4,'0',STR_PAD_LEFT) . ':' . 
				str_pad(dechex($this->object >> 16 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
				str_pad(dechex($this->object & 0xFFFF),4,'0',STR_PAD_LEFT),
			'instance'=>str_pad(dechex($this->instance >> 48 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
				str_pad(dechex($this->instance >> 32 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
				str_pad(dechex($this->instance >> 16 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
				str_pad(dechex($this->instance & 0xFFFF),4,'0',STR_PAD_LEFT),
			'interface'=>dechex($this->interface),
			'input'=>dechex($this->interface & 0xFF),
			'output'=>dechex($this->interface >> 8 & 0xFF),
			'siid'=>str_pad(dechex($this->instance >> 48 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
				str_pad(dechex($this->instance >> 32 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
				str_pad(dechex($this->instance >> 16 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
				str_pad(dechex($this->instance & 0xFFFF),4,'0',STR_PAD_LEFT),
			'giid'=>str_pad(dechex($this->object >> 32 & 0xFFF),4,'0',STR_PAD_LEFT) . ':' . 
				str_pad(dechex($this->object >> 16 & 0xFFFF),4,'0',STR_PAD_LEFT) . ':' . 
				str_pad(dechex($this->object & 0xFFFF),4,'0',STR_PAD_LEFT)
		);
	}

	public function dec()
	{
		return array(
			'object'=>$this->object,
			'instance'=>$this->instance,
			'interface'=>$this->interface,
			'input'=>$this->interface & 0xFF,
			'output'=>$this->interface >> 8 & 0xFF,
			'siid'=>$this->instance,
			'giid'=>$this->object
		);
	}
	
	public function setSiid($siid)
	{
		$this->setInstance($siid);
	}
	
	public function setGiid($giid)
	{
		$this->setObject($giid);
	}
}

?>