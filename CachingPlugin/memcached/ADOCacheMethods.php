<?php
/**
* Methods associated with caching recordsets using the memcached server
*
* This file is part of the ADOdb package.
*
* @copyright 2020-2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\CachingPlugin\memcached;

use ADOdb\CachingPlugin\ADOCacheObject;
use ADOdb\CachingPlugin\memcached\ADOCacheDefinitions;

final class ADOCacheMethods extends \ADOdb\CachingPlugin\ADOCacheMethods
{
	
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
			
		//$this->compress 			= $cacheDefinitions->compress;
		
		//$this->compressionThreshold = $cacheDefinitions->compressionThreshold;
		
		/*
		* Sets the required special variables
			
		$this->controllerItems  	= $cacheDefinitions->controllerItems;
			
		$this->memCacheOptions      = $this->cacheDefinitions->memCacheOptions;
		*/
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
		if (class_exists('Memcached')) {
			
			$memcache = new \MemCached;
			
			if ($this->debug)
			{
				$message = 'MEMCACHED: Loaded the MemCached Libary';
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
				
			} else if (!$memcache) {
				$message = 'MEMCACHED: The Memcached PHP extension was not found or is disabled';
				$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
				return false;
			}


		
		} else {
			
			$message = 'MEMCACHED: The Memcached PHP extension was not found';
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
			$message = 'MEMCACHED: You must specify at least one entry in the memcacheControllerItems';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;
		}
		
				
		$failTarget = count($this->controllerItems);

		foreach($this->controllerItems as $cIndex=>$controllerItem)
		{
					
			if (!is_array($controllerItem))
			{
				$message = sprintf('MEMCACHED: Entry %s in the memcacheControllerItems array is invalid', $cIndex + 1);
				$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
				return false;
			}
			
			if (!array_key_exists('host',$controllerItem))
			{
				$message = sprintf('MEMCACHED: Entry %s in the memcacheControllerItems array must contain a host entry', $cIndex + 1);
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
		
			$connected = $memcache->addServer(
					$serverPush['host'],
					$serverPush['port'],
					$serverPush['weight']
			);
			
			if (!$connected) {
				$failcnt++;
				$message = sprintf("MEMCACHED: Attempt to add entry %s server %s on port %s to memcache connection pool failed",  		
						$cIndex,
						$serverPush['host'],
						$serverPush['port']);
				$this->loggingObject->log($this->loggingObject::NOTICE,$message);
				
			} else if ($this->debug) {
				
				$message = sprintf("MEMCACHED: Added entry %s: server %s on port %s, weight %s to available connection pool",  		
						$cIndex,
						$serverPush['host'],
						$serverPush['port'],
						$serverPush['weight']
					);
						
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
		}
		
		$activeConnections = count($memcache->getServerList());
		if ($activeConnections == 0) {
			$message = 'MEMCACHED: Cannot connect to any memcache server';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;
		}
		
		/*
		* Validate and set compressionThreshold
		*
		$cThreshold = $this->compressionThreshold['threshold'];
		$cSavings   = $this->compressionThreshold['savings'];
		if (!is_integer($cThreshold) || $cThreshold < 0)
		{
			$message = 'MEMCACHED: Compression threshold must be a positive integer value';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;	
		}
		
		if ($cSavings != 0 &&(!is_float($cSavings) || $cSavings < 0 || $cSavings > 1))
		{		
			$message = 'MEMCACHED: Compression savings must be a number between 0 and 1';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;	
		}
					
		if ($cThreshold > 0)
		{
			$memcache->setCompressThreshold($cThreshold,$cSavings);
				
			$message = sprintf('MEMCACHED: Compression set to threshold: %s, savings: %s',
								$cThreshold,$cSavings);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		*/
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
	final public function xconnect() : bool 
	{
		/*
		* Is memcached loaded?
		*/
		if (class_exists('Memcached'))
			$memcache = new \MemCached;
		
		$this->writeLoggingPair(
			$memcache,
			'Loaded the Memcached Libary',
			'The Memcached PHP extension was not found or is disabled'
			);	
		
		/*
		* Get the server list, create a connection group
		*/
		$failcnt = 0;
		$failTarget = 0;
		
		if (count($this->controllerItems) == 0)
		{
			$message = 'MEMCACHED: You must specify at least one entry in the memcacheControllerItems';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;
		}
		
				
		$failTarget = count($this->controllerItems);
		
		foreach($this->controllerItems as $cIndex=>$controller)
		{
			
			if (!is_array($controller))
			{
				$message = sprintf('MEMCACHED: Entry %s in the memcacheControllerItems array is invalid', $cIndex + 1);
				$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
				return false;
			}
			
			if (!array_key_exists('host',$controller))
			{
				$message = sprintf('MEMCACHED: Entry %s in the memcacheControllerItems array must contain a host entry', $cIndex + 1);
				$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
				return false;
			}
			
			$serverPush = array_merge($this->controllerItems,$controller);
	
			if (count($this->controllerItems) == 1)
				$this->controllerItems[0]['weight'] = 100;
		}
			
		$connected = $memcache->addServers($this->controllerItems);
					
		$this->writeLoggingPair(
			$connected,
			'Added Server Pool',  		
			'Failed to add server pool'
			);
		
		if (!$connected)
			return false;
		
		/*
		
		* Global flag
		*/
		$this->cachingIsAvailable = true;
		
		/*
		* We do the Memcached options individually so 
		* that we can flag errors
		*/
		if (!is_array($this->cacheDefinitions->memCacheOptions))
			$this->cacheDefinitions->memCacheOptions = array();
		
		foreach($this->cacheDefinitions->memCacheOptions as $k=>$v)
		{
			$optionSuccess = $memcache->setOption($k,$v);
			$this->writeLoggingPair(
				$optionSuccess,
				sprintf('Successfully set memcached option %s to %s',	$k,$v),
				sprintf('Failed Setting memcached option %s to %s',$k,$v)
				);
		}
		
		/*
		* The memcache connection object
		*/
		$this->cacheLibrary = $memcache;
		return true;
	}
	
	/**
	* Flush an individual query from the apcu cache
	*
	* @param string $recordsetKey The md5 of the query
	* @param ADOCacheObject $additional options unused
	*
	* @return void
	*/
	final public function flushIndividualSet(?string $recordsetKey=null,?ADOCacheObject $options=null ) : void {	
		
		$text      = '';
			
		if (!$this->checkConnectionStatus())
			return;

		if (!$recordsetKey)
			$recordsetKey = $this->lastRecordsetKey;

		if (!$recordsetKey)
			return;	
						
		if (!is_object($options))
			$options = $this->defaultCacheObject;
			
		if ($options->serverKey)
		{
			$success = $this->cacheLibrary->deleteByKey(
				$options->serverKey,
				$recordsetKey);
			
			$text = sprintf(' [ using server key %s ]',$options->serverKey);
		}
		else
			$success = $this->cacheLibrary->delete($recordsetKey);
		
		$this->logflushCacheEvent($recordsetKey,$success,$text);
		
	}
	
	/**
	* Tries to return a recordset from the cache
	*
	* @param string $recordsetKey the md5 code of the request
	* @param string $arrayClass
	* @param ADOCacheObject $options
	*
	* @return recordset
	*/
	final public function readcache(
		string $recordsetKey,
		string $arrayClass,
		?ADOCacheObject $options=null) :array {
	
		if (!$this->checkConnectionStatus())
			return array(null,null);
		
		if (!is_object($options))
			$options = $this->defaultCacheObject;
			
		$text = '';
		
		if ($options->serverKey) 
		{
			$jObject = $this->cacheLibrary->getByKey(
				$options->serverKey,
				$recordsetKey
				);
			
			$text = sprintf(' [ using server key %s ]',$options->serverKey);
		} 
		else 
		{
			$jObject = $this->cacheLibrary->get($recordsetKey);
		}
			
		/*
		* Convert the json encoded ADOCacheRecordset object into an ADORecordset object
		*/
		list ($recordSet, $err) = $this->unpackCachedRecordset(
			$recordsetKey, 
			$jObject,
			$options->ttl,
			$text
		);
		
		return array($recordSet,$err);
		
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
		
		$subText = '';
		
		if ($options->serverKey)
		{
			/*
			* Set by key invoked using the free-text
			* server key
			*/
			$success = $this->cacheLibrary->setByKey(
				$options->serverKey, 
				$recordsetKey, 
				$contents, 
				$options->ttl);
				
			$subText = sprintf(' [ using server key %s ]',$options->serverKey);
		}
		else
		{
			$success = $this->cacheLibrary->set(
				$recordsetKey, 
				$contents, 
				$options->ttl); 
		}
			
		return $this->logWriteCacheEvent(
			$recordsetKey,
			$options->ttl,
			$success,
			$subText);
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

		$stats = $this->cacheLibrary->getStats();
		if (!$stats)
			return array();

		return $stats;
	}

}
