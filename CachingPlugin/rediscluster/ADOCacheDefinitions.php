<?php
/**
* Definitions Passed to the ADOCaching Module for the redis cluster module
*
* You cannot use this to connect to a non-clustered setup
*
* This file is part of the ADOdb package.
*
* @copyright 2021-2024 Mark Newnham
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
	public array   $clusterHosts     = array();
	
	/*
	* The connection timeout retry interval
	*/
	public int 	   $retryInterval 		  = 0;

	/*
	* The connection read timeout
	*/
	public int 	   $readTimeout   		  = 0;
			
	/*
	* Sets the initial redis database
	*/
	public int $redisDatabase = 0;
	
	/*
	* Client options as available
	*/
	public array $clientOptions = array();
	
	/*
	* Are we adding a persistent connection 
	*/
	public bool $persistentConnection = false;
	
	/*
	* Allow asynchronous deletion actions
	*/
	public bool $asynchronousConnection = false;
	
	/*
	* If used, must contain a string connection password
	*/
	public ?string $redisPassword = null;

	/*
	* If used, must contain an array userid,password
	*/
	public array $authCredentials = array();

	/*
	* If used, must contain a string which is the name of a callback function
	* that returns an array of user,pass
	*/
	public ?string $authCallback = '';
		
}