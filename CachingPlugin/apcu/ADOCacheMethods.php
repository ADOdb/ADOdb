<?php
/**
* Methods associated with caching recordsets using the apcu server
*
* This file is part of the ADOdb package.
*
* @copyright 2020-2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\CachingPlugin\apcu;

use ADOdb\CachingPlugin\apcu\ADOExtensionLibrary;

final class ADOCacheMethods extends \ADOdb\CachingPlugin\ADOCacheMethods
{
		
	/*
	* Service flag. Do not modify value
	*/
	public string $service = 'apcu';
	
	public string $serviceName = 'APCu';
	
	/**
	* Connect to the APCu Library
	* 
	* @return bool
	*/
	final protected function connect() : bool 
	{
		/*
		*	do we have the apcu extension loaded?
		*/
		$apcu = function_exists('apcu_enabled') && apcu_enabled();
		
		$this->writeLoggingPair(
			$apcu,
			'Loaded the APCu Libary',
			'The APCu PHP extension was not found or is disabled'
			);
					
		/*
		* Global flag
		*/
		$this->cachingIsAvailable = true;
			
		/*
		* The APCU connection object
		*/
		$this->cacheLibrary = new ADOExtensionLibrary;
		
		return true;
	}
		
	
}
