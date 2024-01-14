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
namespace ADOdb\addins\cache\plugins\memcache;

final class ADOCacheMethods extends \ADOdb\addins\cache\ADOCacheMethods
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
		
		$this->controllerItems  	= $cacheDefinitions->memcacheControllerItems;
			
		$this->compress 			= $cacheDefinitions->memcacheCompress;
		
		$this->compressionThreshold = $cacheDefinitions->memcacheCompressionThreshold;
		
		
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
		
		foreach($this->controllerItems as $cIndex=>$controller)
		{
			
			if (!is_array($controller))
			{
				$message = sprintf('MEMCACHE: Entry %s in the memcacheControllerItems array is invalid', $cIndex + 1);
				$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
				return false;
			}
			
			if (!array_key_exists('host',$controller))
			{
				$message = sprintf('MEMCACHE: Entry %s in the memcacheControllerItems array must contain a host entry', $cIndex + 1);
				$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
				return false;
			}
			
			$serverPush = array_merge($this->controllerItem,$controller);
	
	
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
				sprintf("MEMCACHE: Added entry %s: server %s on port %s to available connection pool",  		
						$cIndex,
						$serverPush['host'],
						$serverPush['port']);
						
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
		$this->_connected = true;
				
		/*
		* The memcache connection object
		*/
		$this->cacheLibrary = $memcache;
		
		return true;
	}
	
	/**
	* Flushes all entries from wincache
	*
	* @return void
	*/
	final public function flushall() : void
	{
				
		if (!$this->checkConnectionStatus())
			return;

		$success = $this->cacheLibrary->flush();
		
		$this->logFlushAllEvent($success);
		
	}
	
	/**
	* Flush an individual query from the memcache cache
	*
	* @param string $filename The md5 of the query
	* @param bool $debug ignored because because of global
	* @param object $additional options unused
	*
	* @return void
	*/
	final public function flushcache(
		string $filename,
		bool $debug=false,
		object $options=null ) : void {	
				
		if (!$this->checkConnectionStatus())
			return;

		$success = $this->cacheLibrary->delete($filename);

		$this->logFlushCacheEvent($filename,$success);
		
	}
	
	/**
	* Tries to return a recordset from the cache
	*
	* @param string $filename the md5 code of the request
	* @param string $err      The error string by reference
	* @param int $secs2cache
	* @param string $arrayClass
	* @param object $options
	*
	* @return recordset
	*/
	final public function readcache(
				string $filename,
				string &$err,
				int $secs2cache,
				string $arrayClass,
				?object $options=null) :?object{
				
		if (!$this->checkConnectionStatus())
			return null;
			
		/*
		* Standardize the parameters
		*/
		$options = $this->unpackCacheObject($options,$secs2cache);
		
		$rs = $this->cacheLibrary->get($filename);
		
		list ($rs, $err) = $this->unpackCachedRecordset($filename, $rs,$options->ttl);
		
		return $rs;
	}		
	
	/**
	* Builds a cached data set
	*
	* @param string $filename
	* @param string $contents
	* @param bool   $debug     Ignored
	* @param int    $secs2cache
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
		/*
		* Windows connection module, allows compression
		*/
		$success = $this->cacheLibrary->set($filename, $contents, $this->compress ? MEMCACHE_COMPRESSED : 0, $options->ttl);
		
		return $this->logWriteCacheEvent($filename,$options->ttl,$success);

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
