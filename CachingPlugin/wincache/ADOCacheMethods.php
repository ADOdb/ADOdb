<?php
/**
* Methods associated with caching recordsets using wincache 
*
* This file is part of the ADOdb package.
*
* @copyright 2021-2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\CachingPlugin\wincache;

use ADOdb\CachingPlugin\ADOCacheObject;
use ADOdb\CachingPlugin\wincache\ADOCacheDefinitions;
use ADOdb\CachingPlugin\wincache\ADOExtensionLibrary;

final class ADOCacheMethods extends \ADOdb\CachingPlugin\ADOCacheMethods
{
		
	/*
	* Service flag. Do not modify value
	*/
	public string $service = 'wincache';
	
	public string $serviceName = 'WinCache';
	
		
	/**
	* Connect to one of the available 
	* 
	* @return bool
	*/
	final protected function connect() : bool 
	{
		
		if (strtoupper(substr(PHP_OS,0,3)) != 'WIN') 
		{
			
			$message = 'WINCACHE: The WinCache PHP extension is only available on Windows Servers';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;
		}
		
		/*
		* do we have wincache installed
		*/
		$library = function_exists('wincache_lock');
				
		$this->writeLoggingPair(
				$library,
				'Loaded the WinCache Libary',
				'The Wincache PHP extension was not found or is disabled'
				);
					
		/*
		* Global flag
		*/
		if (!$library) 
			return false;
		$this->cachingIsAvailable = true;
					
		/*
		* Links the wincache.dll to the cacheLibrary object
		*/
		$this->cacheLibrary = new ADOExtensionLibrary;

		return true;
	}

}
