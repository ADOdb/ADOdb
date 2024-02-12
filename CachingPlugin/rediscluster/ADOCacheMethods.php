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
namespace ADOdb\CachingPlugin\rediscluster;

use ADOdb\CachingPlugin\ADOCacheObject;
use ADOdb\CachingPlugin\rediscluster\ADOCacheDefinitions;

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
	protected string $flushallCacheFunction = 'flushDb';

		
	/**
	* Connect to one of the available 
	* 
	* @return bool
	*/
	final protected function connect() : bool 
	{

		/*
		* Check the hosts array for sanity
		*/
		if (!is_array($this->cacheDefinitions->clusterHosts))
		{
			$this->writeLoggingPair(
				false,
				'The redis cluster hosts list is not an array of host:port pairs',
				'The redis cluster hosts list is not an array of host:port pairs'
			);
			return false;
		}
		
		if (count($this->cacheDefinitions->clusterHosts) < 2)
		{
			$this->writeLoggingPair(
				false,
				'The redis cluster array must have at least two entries of host:port pairs',
				'The redis cluster array must have at least two entries of host:port pairs',
			);
			return false;
		}

		/*
		* If we cannot connect to the cluster, dont keep trying to connect
		*/
		$this->retryConnection = false;

		/*
		* Cluster does not use connect
		*/
		$library = new \RedisCluster(
			null,
			$this->cacheDefinitions->clusterHosts,
			$this->cacheDefinitions->retryInterval,
			$this->cacheDefinitions->readTimeout,
			$this->cacheDefinitions->persistentConnection,
			$this->cacheDefinitions->redisPassword
		);

		$this->writeLoggingPair(
			$library,
			'Successfully attached to the Redis Cluster',
			'Failed to attach to the Redis cluster'
			);

		if (!$library)
			return false;
		
		/*
		* Now auth the connection using either an array or a function
		*/
		$useAuth     = false;
		$authSuccess = true;

		if ($this->cacheDefinitions->authCallback)
		{
			/*
			* 
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
		
		$useAuth = false;
		if ($useAuth == true)
		{
			$this->writeLoggingPair(
				$authSuccess,
				'Authorized account',
				'Failed to authorize account');
		}
		if (!$authSuccess)
			return false;
		
		* Select the database
		*/
		}
		
		$redisDb = $this->cacheDefinitions->redisDatabase;
		if ($redisDb > 0)
		{
			$success = $library->select($redisDb);
			$this->writeLoggingPair(
				$success,
				sprintf('Switched to database %s',$redisDb),
				sprintf('Failed to switch to database %s',$redisDb)
				);

			if (!$success)
				return false;
		}
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
	final public function flushIndividualSet(?string $recordsetKey=null,?ADOCacheObject $options=null ) : void 
	{	
					
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