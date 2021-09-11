<?php
/**
* Core Compression session management plugin for the Sessions package
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\session\plugins;
use \ADOdb\addins\session;


abstract class ADOCompress {
	
	protected ?object $connection = null;
	
	public function __construct($connection)
	{
		$this->connection = $connection;
	}
		
	/**
	* Loads any override options
	*
	* @param array $options
	*
	* @return void
	*/
	abstract public function loadOptions(?array $options=null): void;
	
	/**
	* Converts inbound data to be written
	* @param string	$txt
	* @param string	$key
	*
	* @return string The encrypted pair
	*/
	final public function write(string $data, string $key) : string {
		
		return $this->compress($data, $key);
	
	}

	/**
	* Converts outbound data to be read
	*
	* @param string	$txt
	* @param string	$key
	*
	* @return string The encrypted pair
	*/
	final public function read(string $data, string $key) : string {
		
		return $this->decompress($data, $key);
	
	}
	
	/**
	* Compresses the text for the key
	*
	* @param string	$txt
	* @param string	$key
	*
	* @return string The encrypted pair
	*/
	abstract protected function compress(string $txt,string $key) : string;
	
	/**
	* Decompresses the text for the key
	*
	* @param string	$txt
	* @param string	$key
	*
	* @return string The decompressed pair
	*/
	abstract protected function decompress(string $txt, string $key) : string ;
	
	/**
	* Checks the quality and range of a specific key
	*
	* @param	array	$options
	* @param	string	$key
	* @param	int		$min
	* @param	int		$max
	*
	* @return array
	*/
	final protected function integerQuality(&$options, string $key, ?int $min=null, ?int $max=null) : array {
		
		if (!isset($options[$key]))
			return false;
		
		if (!preg_match('/^\d+$/', $options[$key]))
		{
			
			if ($this->connection->debug)
			{
				$message = sprintf('SESSION: Non-integer value %s was ignored',$key);
				$this->connection->loggingObject->log(Logger::DEBUG,$message);
			}
			return false;	
			
			unset($options[$key]);
		}
		
		$value = (int)$options[$key];
				
		if ($min !== null && $value < $min)
		{
			
			if ($this->connection->debug)
			{
				$message = sprintf('SESSION: Out of range minimum %s for key %s was ignored',
								   $options[$key],
								   $key);
								   
				$this->connection->loggingObject->log(Logger::DEBUG,$message);
			}
			
			unset($options[$key]);
			return false;

		}
		
		if ($max !== null && $value > $max)
		{
			unset($options[$key]);
			if ($this->connection->debug)
			{
				$message = sprintf('SESSION: Out of rance maximum value %s for key %s was ignored',
								   $options[$key],
								   $key
								   );
				$this->connection->loggingObject->log(Logger::DEBUG,$message);
			}
			return false;

		}
		
		return true;
	
	}
}
