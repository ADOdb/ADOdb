<?php
/**
* Base Onject holding default configurations for the ADOCaching Module
*
* This file is part of the ADOdb package.
*
* @copyright 2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace ADOdb\CachingPlugin;

/**
* Defines the attributes passed to the monolog interface
*/
class ADOCacheObject
{
	/*
	* Debugging for cache
	*/
	public bool $debug = false;
	
	/*
	* Expiry time in seconds
	*/
	public int $ttl = 2400;
	
	/*
	* Only available for certain target caches
	*/
    public ?string $serverKey;
		
}