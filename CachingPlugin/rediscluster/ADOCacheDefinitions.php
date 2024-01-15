<?php
/**
* Definitions Passed to the ADOCaching Module for the redis cluster module
*
* You cannot use this to connect to a non-clustered setup
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace ADOdb\CachingPlugin\rediscluster;

/**
* Defines the attributes passed to the redis interface
*/
final class ADOCacheDefinitions extends \ADOdb\CachingPlugin\ADOCacheDefinitions
{
	/*
	* Debugging for cache
	*/
	public bool $debug = true;
	
	/*
	* Service flag. Do not modify value
	*/
	public string $serviceName = 'rediscluster';
	
	/*
	* Service Name.
	*/
	public string $serviceDescription = 'REDISCLUSTER';
		
	/*
	* The connection configuration
	* The cluster hosts format is array('host:port','host:port',.....)
	*/
	public array   $redisHosts     = array();
	public ?string $persistentId  = null;
	public int 	   $retryInterval = 0;
	public int 	   $readTimeout   = 0;
		
	/*
	* Sets the initial redis database
	*/
	public int $redisDatabase = 0;
	
	/*
	* Client options as available
	*/
	public array $redisClientOptions = array();
	
	
	/*
	* Are we adding a persistent connection 
	*/
	public bool $redisPersistent = false;
	
	/*
	* Allow asynchronous deletion actions
	*/
	public bool $redisAsynchronous = false;
	
	/*
	* If used, must contain a string with the password
	*/
	public ?string $redisPassword = null;
	
		
}