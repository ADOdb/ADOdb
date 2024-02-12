<?php
/**
* Methods associated with caching recordsets using YAC 
*
* This file is part of the ADOdb package.
*
* @copyright 2020-2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\CachingPlugin\yac;

use ADOdb\CachingPlugin\ADOCacheObject;
use ADOdb\CachingPlugin\yac\ADOCacheDefinitions;

final class ADOCacheMethods extends \ADOdb\CachingPlugin\ADOCacheMethods
{
	/*
	* Service flag. Do not modify value
	*/
	public string $service = 'yac';
	
	public string $serviceName = 'YAC';
	
		
	/**
	* Connect to one of the available 
	* 
	* @return bool
	*/
	final protected function connect() : bool 
	{
				
		/*
		* do we have Yac installed
		*/
		
		$library = class_exists('Yac');
		
		$this->writeLoggingPair(
				$library,
				'Loaded the Yac Libary',
				'The YAC PHP extension was not found or is disabled'
				);
		
					
		if (!$library) 
			return false;
		
		/*
		* Global flag
		*/
		$this->cachingIsAvailable = true;
			
		/*
		* The Yac connection object
		*/
		$this->cacheLibrary = new \Yac; 
		
		return true;
	}
}
