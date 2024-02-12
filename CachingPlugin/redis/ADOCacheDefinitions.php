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

namespace ADOdb\CachingPlugin\redis;

/**
* Defines the attributes passed to the redis interface
*/
final class ADOCacheDefinitions extends \ADOdb\CachingPlugin\ADOCacheDefinitions
{
	
	/*
	* Service flag. Do not modify value
	*/
	public string $serviceName = 'redis';
	
	/*
	* Service Name.
	*/
	public string $serviceDescription = 'REDIS';
		

	/*
	* The lazy connector object
	* @sample 
	array
		'host' => '127.0.0.1',
		'port' => 6379,
		'connectTimeout' => 2.5,
		'auth' => ['phpredis', 'phpredis'],
		'ssl' => ['verify_peer' => false],
		'backoff' => [
			'algorithm' => Redis::BACKOFF_ALGORITHM_DECORRELATED_JITTER,
			'base' => 500,
			'cap' => 750,
		],
	]);
	*/
	public array $lazyConnector = array(); 
	
	/*
	* A host or IP for a redis server
	*/
	public string $host = '';

	/*
	* A host:port pair for a redis server
	* defaukt is 6379
	*/
	public string $port = '6379';
		
	/*
	* Sets the initial redis database
	*/
	public int $redisDatabase = 0;
	
	/*
	* Client options as available
	*/
	public array $clientOptions = array();
	
	/*
	* The connection timeout retry interval
	*/
	public int $retryInterval 		  = 0;

	/*
	* The connection read timeout
	*/
	public int $readTimeout   		  = 0;

	/*
	* Are we adding a persistent connection 
	*/
	public bool $persistentConnection = false;
	
	/*
	* Allow asyncronous deletion actions
	*/
	public bool $asynchronousConnection = false;
	
	/*
	* If used, must contain an array with at least one element
	*/
	public ?array $authCredentials = null;
	
	/*
	* If used, must contain a string which is the name of a function 
	* that returns an array of user,pass
	*/
	public string $authCallback = '';
		
	
}