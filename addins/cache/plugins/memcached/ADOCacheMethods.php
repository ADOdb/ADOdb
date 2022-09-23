<?php
/**
* Methods associated with caching recordsets using the memcached server
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\cache\plugins\memcached;

final class ADOCacheMethods extends \ADOdb\addins\cache\ADOCacheMethods
{
	
	/**
	* Constructor
	*
	* @param obj $connection   A Valid ADOdb Connection
	* @param obj $cacheDefinitions An ADOdbCacheDefinitions Class
	*
	* @return obj 
	*/
	final public function __construct(object $connection, object $cacheDefinitions)
	{
		$this->setDefaultEnvironment($connection,$cacheDefinitions);
		
		/*
		* Sets the custom items from this plugins\memcache
		*/
/*
		* Sets the custom items from this plugins\memcache
		*/
		
		$this->memcacheControllers = $cacheDefinitions->memcacheControllers;
		$this->controllerItems  	= $cacheDefinitions->memcacheControllerItems;
		
		$this->memCacheOptions     = $cacheDefinitions->memCacheOptions;

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
	final public function connect() : bool 
	{
		/*
		* Is memcached loaded?
		*/
		if (class_exists('Memcached'))
			$memcache = new \MemCached;
		
		$this->writeLoggingPair(
			$memcache,
			'Loaded the Memcached Library',
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
		$this->_connected = true;
		
		/*
		* We do the Memcached options individually so 
		* that we can flag errors
		*/
		if (!is_array($this->memCacheOptions))
			$this->memCacheOptions = array();
		
		foreach($this->memCacheOptions as $k=>$v)
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
	* Flushes all entries from memcache
	*
	* @return
	*/
	final public function flushall() : void
	{
		
		if (!$this->checkConnectionStatus())
			return;

		$del = $this->cacheLibrary->flush();
		
		$this->logFlushAllEvent($del);

	}
	
	/**
	* Flush an individual query from memcache
	*
	* @param string $filename The md5 of the query
	* @param bool   $debug    Legacy ignored
	* @param object $options
	*
	* @return void
	*/
	final public function flushcache(
					string $filename,
					bool $debug=false,
					?object $options=null) : void {
					
		$text      = '';
		
		if (!$this->checkConnectionStatus())
			return;
			
		$options = $this->unpackCacheObject(
			$options,
			0);
		
		if ($options->serverKey)
		{
			$del = $this->cacheLibrary->deleteByKey(
				$options->serverKey,
				$filename);
			
			$text = sprintf(' [ using server key %s ]',$options->serverKey);
		}
		else
			$del = $this->cacheLibrary->delete($filename);
		
		$this->logFlushCacheEvent($filename,$del,$text);
		
	}
	
	/**
	* Tries to return a recordset from the cache
	*
	* @param string $filename the md5 code of the request
	* @param string $err      The error by reference
	* @param int $secs2cache
	* @param string[] $options
	*
	* @return recordset
	*/
	final public function readcache(
				string $filename,
				string &$err,
				int $secs2cache,
				string $arrayClass,
				?object $options=null) :?object {
		
		if (!$this->checkConnectionStatus())
			return null;
		
		$options = $this->unpackCacheObject(
			$options,
			$secs2cache);
			
		$text = '';
		if ($options->serverKey) 
		{
			$rs = $this->cacheLibrary->getByKey(
				$options->serverKey,
				$filename
				);
			
			$text = sprintf(' [ using server key %s ]',$options->serverKey);
		} 
		else 
		{
			$rs = $this->cacheLibrary->get($filename);
		}
			
		list ($rs, $err) = $this->unpackCachedRecordset($filename, $rs,$options->ttl,$text);
		
		return $rs;
		
	}
	
	/**
	* Builds a cached data set
	*
	* @param string $filename
	* @param string $contents
	* @param int    $secs2cache
	* @param bool   $debug     Ignored
	* @param obj    $options
	*
	* @return bool
	*/
	final public function writecache(
			string $filename, 
			string $contents, 
			bool $debug,
			int $secs2cache,
			?object $options=null) : bool {
		
		if (!$this->checkConnectionStatus())
			return false;
		
		/*
		* Standardize the parameters
		*/
		$options = $this->unpackCacheObject($options,$secs2cache);
		
		$subText = '';
		
		if ($options->serverKey)
		{
			/*
			* Set by key invoked using the free-text
			* server key
			*/
			$success = $this->cacheLibrary->setByKey(
				$options->serverKey, 
				$filename, 
				$contents, 
				$options->ttl);
				
			$subText = sprintf(' [ using server key %s ]',$options->serverKey);
		}
		else
		{
			$success = $this->cacheLibrary->set(
				$filename, 
				$contents, 
				$options->ttl); 
		}
			
		return $this->logWriteCacheEvent(
			$filename,
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

		return $this->cacheLibrary->getStats();
	}

}
