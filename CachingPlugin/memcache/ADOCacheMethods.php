<?php
/**
* Methods associated with caching recordsets using the memcache server
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\CachingPlugin\memcache;

use ADOdb\CachingPlugin\ADOCacheObject;
use ADOdb\CachingPlugin\memcache\ADOCacheDefinitions;

final class ADOCacheMethods extends \ADOdb\CachingPlugin\ADOCacheMethods
{
	
	protected bool  $compress = false;
	
	protected array $controllerItem = array(
		'host'=>'',
		'port'=>11211,
		'persistent'=>true,
		'weight'=> 100,
		'timeout'=>1,
		'retry'=>15,
		'online'=>true,
		'failureCallback'=>null);
	
	protected array $controllerItems = array();
	/*
	* See the compression threshold documentation
	*/
	protected array $compressionThreshold = array('threshold'=>0,'savings'=>0);
	
	/**
	* Constructor
	*
	* @param ADOConnection 		 $connection   		A Valid ADOdb Connection
	* @param ADOCacheDefinitions $cacheDefinitions 	An ADOdbCacheDefinitions Class
	*
	* @return obj 
	*/	
	public function __construct(object $connection, ?object $cacheDefinitions=null)
	{
		
		$this->setDefaultEnvironment($connection,$cacheDefinitions);
		
		/*
		* Sets the required special variables
		*/	
		$this->controllerItems  	= $cacheDefinitions->controllerItems;
			
		$this->compress 			= $cacheDefinitions->compress;
		
		$this->compressionThreshold = $cacheDefinitions->compressionThreshold;
				
		/*
		* Startup the client connection
		*/
		$this->connect();

	}
	
	/**
	* Connect to one of the available 
	* 
	* @return bool
	*/
	final protected function connect() : bool 
	{
		/*
		*	do we have memcache library available?
		*/
		if (class_exists('Memcache')) {
			
			$memcache = new \MemCache;
			
			if ($this->debug)
			{
				$message = 'MEMCACHE: Loaded the MemCache Libary';
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
				
			}
		
		} else {
			
			$message = 'MEMCACHE: The Memcache PHP extension was not found!';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;
		
		}

		/*
		* Get the server list, create a connection group
		*/
		
		$failcnt    = 0;
		$failTarget = 0;
		
		if (count($this->controllerItems) == 0)
		{
			$message = 'MEMCACHE: You must specify at least one entry in the memcacheControllerItems';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;
		}
		
				
		$failTarget = count($this->controllerItems);

		
		
		foreach($this->controllerItems as $cIndex=>$controllerItem)
		{
					
			if (!is_array($controllerItem))
			{
				$message = sprintf('MEMCACHE: Entry %s in the memcacheControllerItems array is invalid', $cIndex + 1);
				$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
				return false;
			}
			
			if (!array_key_exists('host',$controllerItem))
			{
				$message = sprintf('MEMCACHE: Entry %s in the memcacheControllerItems array must contain a host entry', $cIndex + 1);
				$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
				return false;
			}
			
			$serverPush = array_merge($this->controllerItem,$controllerItem);
	
	
			if (count($this->controllerItems) == 1)
				$serverPush['weight'] = 100;
					
			/*
			* Default for unset failureCallback is undocumented
			* and I don't know what it is, so do it this way
			*/
			if ($serverPush['failureCallback'])
				$connected = $memcache->addServer(
						$serverPush['host'],
						$serverPush['port'],
						$serverPush['persistent'],
						$serverPush['weight'],
						$serverPush['timeout'],
						$serverPush['retry'],
						$serverPush['online'],
						$serverPush['failureCallback']);
			else		
				$connected = $memcache->addServer(
						$serverPush['host'],
						$serverPush['port'],
						$serverPush['persistent'],
						$serverPush['weight'],
						$serverPush['timeout'],
						$serverPush['retry'],
						$serverPush['online']);
				
			if (!$connected) {
				$failcnt++;
				$message = sprintf("MEMCACHE: Attempt to add entry %s server %s on port %s to memcache connection pool failed",  		
						$cIndex,
						$serverPush['host'],
						$serverPush['port']);
				
				$this->loggingObject->log($this->loggingObject::NOTICE,$message);
				
			} else if ($this->debug) {
				$message = sprintf("MEMCACHE: Added entry %s: server %s on port %s, weight %s to available connection pool",  		
						$cIndex,
						$serverPush['host'],
						$serverPush['port'],
						$serverPush['weight']
					);
						
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
		}
		
		if ($failcnt == $failTarget) {
			$message = 'MEMCACHE: Cannot connect to any memcache server';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;
		}
		
		/*
		* Validate and set compressionThreshold
		*/
		$cThreshold = $this->compressionThreshold['threshold'];
		$cSavings   = $this->compressionThreshold['savings'];
		if (!is_integer($cThreshold) || $cThreshold < 0)
		{
			$message = 'MEMCACHE: Compression threshold must be a positive integer value';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;	
		}
		
		if ($cSavings != 0 &&(!is_float($cSavings) || $cSavings < 0 || $cSavings > 1))
		{		
			$message = 'MEMCACHE: Compression savings must be a number between 0 and 1';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;	
		}
					
		if ($cThreshold > 0)
		{
			$memcache->setCompressThreshold($cThreshold,$cSavings);
				
			$message = sprintf('MEMCACHE: Compression set to threshold: %s, savings: %s',
								$cThreshold,$cSavings);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		
		/*
		* Global flag
		*/
		$this->cachingIsAvailable = true;
				
		/*
		* The memcache connection object
		*/
		$this->cacheLibrary = $memcache;
		
		return true;
	}
	
	/**
	* Builds a cached data set
	*
	* @param string $recordsetKey
	* @param string $contents
	* @param ADOCacheObject    $options
	*
	* @return bool
	*/
	final public function writecache(
		string $recordsetKey, 
		string $contents, 
		?ADOCacheObject $options=null) : bool {
		
		if (!$this->checkConnectionStatus())
			return false;

		if (!is_object($options))
			$options = $this->defaultCacheObject;
		
		if ($this->compress)
		{
			$this->loggingObject->log($this->loggingObject::DEBUG,'MEMCACHE: Compressing cache entry');
		}
		
		/*
		* Windows connection module, allows compression
		*/
		$success = $this->cacheLibrary->set($recordsetKey, $contents, $this->compress ? MEMCACHE_COMPRESSED : 0, $options->ttl);
		
		return $this->logWriteCacheEvent($recordsetKey,$options->ttl,$success);

	}
	/**
	* Returns an array of info about the cache
	*
	* @return array
	*/
	final public function cacheInfo() : array
	{
		if (!$this->checkConnectionStatus())
			return array();

		return $this->cacheLibrary->getExtendedStats();
	}
	
}
