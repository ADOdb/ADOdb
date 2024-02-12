<?php
/**
* Definitions Passed to the ADOCaching Module for the memcache module
*
* This file is part of the ADOdb package.
*
* @copyright 2020-2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace ADOdb\CachingPlugin\memcache;

/**
* Defines the attributes passed to the monolog interface
*/
final class ADOCacheDefinitions extends \ADOdb\CachingPlugin\ADOCacheDefinitions
{
		
	/*
	* Service flag. Do not modify value
	*/
	public string $serviceName = 'memcached';
	
	public string $serviceDescription = 'MEMCACHED';
		 
	/*
	* Use 'true' to store the item compressed (uses zlib)
	*/
	//public bool $compress = false;
	
	/*
	* See the compression threshold documentation
	*/
	//public array $compressionThreshold = array('threshold'=>0,'savings'=>0);
	
	/*
	* Any Additional Options
	*/
	public array $memCacheOptions = array();
	
	/*
	* Use the servers option for memcached, can specify
	* host, port weight for a group of controllers
	*
	* 'host'=>192.68.0.85','port'=>'11261','weight'=>66
	*/
	public array $controllerItem = array(
		'host'=>'',
		'port'=>11211,
		'persistent'=>true,
		'weight'=> 100,
		'timeout'=>1,
		'retry'=>15,
		'online'=>true,
		'failureCallback'=>null);
	
	
}