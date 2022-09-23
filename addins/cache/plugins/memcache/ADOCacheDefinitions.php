<?php
/**
* Definitions Passed to the ADOCaching Module for the memcache module
* This version is the only one that supports Windows
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace ADOdb\addins\cache\plugins\memcache;

/**
* Defines the attributes passed to the monolog interface
*/
final class ADOCacheDefinitions extends \ADOdb\addins\cache\ADOCacheDefinitions
{
	/*
	* Debugging for cache
	*/
	public bool $debug = false;
	
	/*
	* Service flag. Do not modify value
	*/
	public string $serviceName = 'memcache';
	
	public string $serviceDescription = 'MEMCACHE';
		 
	/*
	* Use 'true' to store the item compressed (uses zlib)
	*/
	public bool $memcacheCompress = false;
	
	/*
	* See the compression threshold documentation
	*/
	public array $memcacheCompressionThreshold = array('threshold'=>0,'savings'=>0);
	
	/*
	* build a list of as many controllers as needed
	*/
	public array $memcacheControllers = array();
	
	/*
	* Use the servers option for memcached, can specify
	* host, port weight for a group of controllers
	*
	* 'host'=>192.68.0.85','port'=>'11261','weight'=>66
	*/
	public array $memcacheControllerItem = array(
		'host'=>'',
		'port'=>11211,
		'persistent'=>true,
		'weight'=> 100,
		'timeout'=>1,
		'retry'=>15,
		'online'=>true,
		'failureCallback'=>null);
	
	
}