<?php
/**
* Methods associated with caching recordsets using redis 
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\cache\plugins\redis;

final class ADOCacheMethods extends \ADOdb\addins\cache\ADOCacheMethods
{
	
	
	/*
	* Service flag. Do not modify value
	*/
	public string $service = 'redis';
	
	public string $serviceName = 'Redis';
	
	
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
		* do we have Redis installed
		*/
		$library = new \Redis();
		
		$this->writeLoggingPair(
			$library,
			'Loaded the Redis Libary',
			'The Redis PHP extension was not found or is disabled'
			);
			
		/*
		* Global flag
		*/
		$redisHost = $this->cacheDefinitions->redisControllerItem['host'];
		$redisPort = $this->cacheDefinitions->redisControllerItem['port'];
		$persistent	   = $this->cacheDefinitions->redisControllerItem['persistentId'];
		$retryInterval = $this->cacheDefinitions->redisControllerItem['retryInterval'];
		$readTimeout   = $this->cacheDefinitions->redisControllerItem['readTimeout'];
		
		if ($this->cacheDefinitions->redisPersistent)
		{
		$success = $library->pconnect($redisHost,$redisPort,$persistentId,$retryInterval,$readTimeout);			
		
		$this->writeLoggingPair(
			$success,
			sprintf('Attached persitent connection to Redis Server at %s',$redisHost),
			sprintf('Failed to add persistent connection to Redis server at %s',$redisHost)
			);
		}
		else
		{
			$success = $library->connect($redisHost,$redisPort,null,$retryInterval,$readTimeout);			
			
			$this->writeLoggingPair(
				$success,
				sprintf('Attached to Redis Server at %s',$redisHost),
				sprintf('Failed to connect to Redis server at %s',$redisHost)
				);
		}
		
		if (!$success)
			return false;
		
		/*
		* Now auth the connection using either an array or a function
		*/
		$useAuth     = false;
		$authSuccess = true;
		if ($this->cacheDefinitions->redisAuthFunction)
		{
			$useAuth = true;
			$redisAuthFunction = $this->cacheDefinitions->redisAuthFunction;
			$authSuccess = $this->library->auth($redisAuthFunction());
		}
		else if (is_array($this->cacheDefinitions->redisAuth))
		{
			$useAuth = true;
			$redisAuth = $this->cacheDefinitions->redisAuth;
			$authSuccess = $this->library->auth($redisAuth);
		}	
		if ($useAuth)
			$this->writeLoggingPair(
				$authSuccess,
				'Authorized account',
				'Failed to authorize account');

		if (!$authSuccess)
			return false;
		
		/*
		* Select the database
		*/
		$redisDb = $this->cacheDefinitions->redisDatabase;
		$success = $library->select($redisDb);
		$this->writeLoggingPair(
			$success,
			sprintf('Switched to database %s',$redisDb),
			sprintf('Failed to switch to database %s',$redisDb)
			);

		if (!$success)
			return false;
		
		/**
		* Now do the client options. If they fail, we will continue anyway
		*/
		if (count ($this->cacheDefinitions->redisClientOptions) > 0)
		{
			foreach ($this->cacheDefinitions->redisClientOptions as $cOption=>$cValue)
			{
				$success = $this->library->setOption($cOption,$cValue);
				$this->writeLoggingPair(
					$success,
					sprintf('Added Client Option %s value %s',$cOption,$cValue),
					sprintf('Failed to add client option %s to %s',$cOption,$cValue)
				);
			}
				
		}

		$this->_connected = true;

		/*
		* The Redis connection object
		*/
		$this->cacheLibrary = &$library; 
		
		return true;
	}
	
	
	/**
	* Flushes all entries from current active database
	*
	* @return void
	*/
	final public function flushall() : void
	{
				
		if (!$this->checkConnectionStatus())
			return;

		$this->cacheLibrary->flushDb();
		
		$this->logFlushAllEvent(true);
		
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

		if ($this->cacheDefinitions->redisAsynchronous)
			/*
			* Delete is done offline
			*/
			$success = $this->cacheLibrary->unlink($filename);
		else
			$success = $this->cacheLibrary->del($filename);

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
		
		$success = $this->cacheLibrary->set ( 
			$filename , 
			$contents ,
			$options->ttl );
		
		return $this->logWriteCacheEvent(
			$filename,
			$options->ttl,
			$success);

	}
	
	/**
	* Returns an array of info about the cache
	*
	* @return array
	*/
	final public function cacheInfo() : array
	{

		return $this->cacheLibrary->info();
	}
}
