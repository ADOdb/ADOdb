<?php
/**
* Methods associated with caching recordsets using redis 
*
* This file is part of the ADOdb package.
*
* @copyright 2021-2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\CachingPlugin\redis;

use ADOdb\CachingPlugin\ADOCacheObject;
use ADOdb\CachingPlugin\redis\ADOCacheDefinitions;

final class ADOCacheMethods extends \ADOdb\CachingPlugin\ADOCacheMethods
{
	
	/*
	* Service flag. Do not modify value
	*/
	public string $service = 'redis';
	
	public string $serviceName = 'Redis';
	
	/*
	* The name of the function that will be used to flush the cache
	*/
	protected string $flushAllCacheFunction = 'flushDb';

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
		if (!extension_loaded('redis'))
		{
			$this->writeLoggingPair(
				false,
				'The Redis PHP extension was not found or is disabled',
				'The Redis PHP extension was not found or is disabled'
				);
			return false;
		}

		/*
		* If the lazy connector is set, then we will use that
		*/
		if (is_array($this->cacheDefinitions->lazyConnector) && count($this->cacheDefinitions->lazyConnector) > 0)
		{
			/*
			* If we cannot connect and we are using a lazy connector,
			* dont keep trying to connect
			*/
			$this->retryConnection = false;

			$library = new \Redis($this->cacheDefinitions->lazyConnector);
			
			$this->writeLoggingPair(
				$library,
				'Connected to Redis using lazy connector',
				'Failed lazy connection attempt for Redis'
			);
			if ($library)
			{
				$this->cachingIsAvailable = false;
				$this->cacheLibrary = &$library; 
				return true;
			}
			return false;
		}	

		$library = new \Redis();
		
		$this->writeLoggingPair(
			$library,
			'Loaded the Redis Libary',
			'The Redis PHP extension was not found or is disabled'
			);
	
		if (!$library)
			return false;

		if ($this->cacheDefinitions->persistentConnection)
		{
		    $success = $library->pconnect(
				$this->cacheDefinitions->host,
				$this->cacheDefinitions->port,
				$this->cacheDefinitions->persistentConnection,
				$this->cacheDefinitions->retryInterval,
				$this->cacheDefinitions->readTimeout
			);			
		
		    $this->writeLoggingPair(
			    $success,
			    sprintf('Attached persitent connection to Redis Server at %s',$this->cacheDefinitions->host),
			    sprintf('Failed to add persistent connection to Redis server at %s',$this->cacheDefinitions->host)
			);
		}
		else
		{
			/*
			* Attempt to connect to the redis server
			*/
			try {
				$success = @$library->connect(
					$this->cacheDefinitions->host,
					$this->cacheDefinitions->port,
					$this->cacheDefinitions->retryInterval,
					$this->cacheDefinitions->readTimeout
				);			
			}
			catch (\RedisException $e)
			{
				$success = false;
			}
				
			$this->writeLoggingPair(
				$success,
				sprintf('Attached to Redis Server at %s',$this->cacheDefinitions->host),
				sprintf('Failed to connect to Redis server at %s',$this->cacheDefinitions->host)
			);
		}
		
		if (!$success)
			return false;
		
		/*
		* Now auth the connection using either an array or a function
		*/
		$useAuth     = false;
		$authSuccess = true;
		if ($this->cacheDefinitions->authCallback)
		{
			if ($this->debug)
			{
				$this->writeLoggingPair(
					is_callable($this->cacheDefinitions->authCallback),
					sprintf('The function %s is callable',$this->cacheDefinitions->authCallback),
					sprintf('The function %s is not callable',$this->cacheDefinitions->authCallback)
				);
			}
			$useAuth = true;
			$authCallback = $this->cacheDefinitions->authCallback;
			$authSuccess = $this->library->auth($authCallback());
		}
		else if (is_array($this->cacheDefinitions->authCredentials))
		{
			$useAuth = true;
			$authCredentials = $this->cacheDefinitions->authCredentials;
			$authSuccess = $this->library->auth($authCredentials);
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
		
		$success = $library->select($this->cacheDefinitions->redisDatabase);
		$this->writeLoggingPair(
			$success,
			sprintf('Switched to database %s',$this->cacheDefinitions->redisDatabase),
			sprintf('Failed to switch to database %s',$this->cacheDefinitions->redisDatabase)
			);

		if (!$success)
			return false;
		
		/**
		* Now do the client options. If they fail, we will continue anyway
		*/
		if (count ($this->cacheDefinitions->clientOptions) > 0)
		{
			foreach ($this->cacheDefinitions->clientOptions as $cOption=>$cValue)
			{
				$success = $this->library->setOption($cOption,$cValue);
				$this->writeLoggingPair(
					$success,
					sprintf('Added Client Option %s value %s',$cOption,$cValue),
					sprintf('Failed to add client option %s to %s',$cOption,$cValue)
				);
			}
				
		}

		$this->cachingIsAvailable = true;

		/*
		* The Redis connection object
		*/
		$this->cacheLibrary = &$library; 
		
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
					
		if (!$this->checkConnectionStatus())
			return;

		if (!$recordsetKey)
			$recordsetKey = $this->lastRecordsetKey;

		if (!$recordsetKey)
			return;

		if ($this->cacheDefinitions->asynchronousConnection)
			/*
			* Delete is done offline
			*/
			$success = $this->cacheLibrary->unlink($recordsetKey);
		else
			$success = $this->cacheLibrary->del($recordsetKey);

		$this->logflushCacheEvent($recordsetKey,$success);
		
	}

}
