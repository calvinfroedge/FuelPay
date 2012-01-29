<?php

namespace Payment;

abstract class Payment
{
	//Array for storing class instances
    private static $instances = array();

	/**
	* Create a new instance
	**/
	public static function forge($key = 'default') 
	{
		$srcdir = dirname(__DIR__) . '/src/';
		$payments =  $srcdir.'php-payments/libraries/payments.php';
		
		include $payments;
		$i = new \Payments;
		
		//Don't copy the class to the instances array, create a reference to it
		static::$instances[$key] =& $i;

		//Return the reference
		return static::$instances[$key];
	}

	/**
	* Get a Named Instance or Call the Forge Method to Create a New One
	*
	* @param	A key to set or get.
	**/
	public static function instance($key = null)
	{
		//Check if the instance exists and return it if it does
		if(array_key_exists($key, static::$instances))
		{
			return static::$instances[$key];	
		}

		//Instance does not exist
		if(!is_null($key))
		{
			//Create a new instance with this name otherwise
			return self::forge($key);
		}
		else //Otherwise 
		{
			//Create a new instance, default key is used
			return self::forge();	
		}
	}

	/**
	* Use the Forge Method to Create a New Instance and Call the Appropriate Method
	*
	* @param	The PHP-Payments method
	* @param	The params for the method
	**/
	public static function __callStatic($method, $args)
	{
		//Create a new instance with a numeric key
		$i = self::forge();

		$gateway = $args[0];
		$params = $args[1];

		return $i->$method($gateway, $params);
	}
}
