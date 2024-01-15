<?php
/**
* Definitions Passed to the ADOCaching Module for the apcu module
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace ADOdb\CachingPlugin\apcu;

/**
* Defines the attributes passed to the apcu interface
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
	public string $serviceName = 'apcu';
	
	/*
	* Service Name.
	*/
	public string $serviceDescription = 'APCu';
		
	 	
}