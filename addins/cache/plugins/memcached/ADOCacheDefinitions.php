<?php
/**
* Definitions Passed to the ADOCaching Module for the memcached module
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace ADOdb\addins\cache\plugins\memcached;

/**
* Defines the attributes passed to the monolog interface
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
	public string $serviceName = 'memcached';
	
	public string $serviceDescription = 'MEMCACHED';
	
	/*
	* build a list of as many controllers as needed
	*/
	public array $memCacheControllers = array();
	
	/*
	* Use the servers option for memcached, can specify
	* host, port weight for a group of controllers
	*
	* 'host'=>192.68.0.85','port'=>'11261','weight'=>66
	*/
	public array $memcacheControllerItem = array(
		'host'=>'',
		'port'=>11211,
		'weight'=> 100);
	
 	public array $memCacheOptions = array();
}