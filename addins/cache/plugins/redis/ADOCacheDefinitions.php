<?php
/**
* Definitions Passed to the ADOCaching Module for the redis module
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace ADOdb\addins\cache\plugins\redis;

/**
* Defines the attributes passed to the redis interface
*/
final class ADOCacheDefinitions extends \ADOdb\addins\cache\ADOCacheDefinitions
{
	/*
	* Debugging for cache
	*/
	public bool $debug = true;
	
	/*
	* Service flag. Do not modify value
	*/
	public string $serviceName = 'redis';
	
	/*
	* Service Name.
	*/
	public string $serviceDescription = 'REDIS';
		
	public array $redisControllerItem = array(
		'host'=>'',
		'port'=>6379,
		'persistentId'=>null,
		'retryInterval'=>0,
		'readTimeout'=>0);
		
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
	* Allow asyncronous deletion actions
	*/
	public bool $redisAsynchronous = false;
	
	/*
	* If used, must contain an array with at least one element
	*/
	public ?array $redisAuth = null;
	
	/*
	* If used, must contain a string which is the name of a function 
	* that returns an array of user,pass
	*/
	public string $redisAuthFunction = '';
	
	
	
	
}