<?php
/**
* Definitions Passed to the ADOCaching Module for the memcached module
*
* This file is part of the ADOdb package.
*
* @copyright 2020-2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace ADOdb\CachingPlugin\filesystem;

/**
* Defines the attributes passed to the monolog interface
*/
final class ADOCacheDefinitions extends \ADOdb\CachingPlugin\ADOCacheDefinitions
{
		
	/*
	* Service flag. Do not modify value
	*/
	public string $serviceName = 'filesystem';
	
	public string $serviceDescription = 'FILESYSTEM';

	
	/*
	* The root level of the cache subdirectory system on the
	* local machine. To use a distributed service. Must
	* be set
	*/
	public string $cacheDirectory = '';
  	
	/*
	* Overrides the directory permissions from the definitions file
	* default is 0771
	*/
	public string $cacheDirectoryPermissions = '';
	
}