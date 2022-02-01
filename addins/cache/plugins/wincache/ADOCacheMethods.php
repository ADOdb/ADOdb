<?php
/**
* Methods associated with caching recordsets using wincache 
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\cache\plugins\wincache;

final class ADOCacheMethods extends \ADOdb\addins\cache\ADOCacheMethods
{
	
	
	/*
	* Service flag. Do not modify value
	*/
	public string $service = 'wincache';
	
	public string $serviceName = 'WinCache';
	
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
				$success,
				'Loaded the WinCache Libary',
				'The Wincache PHP extension was not found or is disabled'
				);
					
		/*
		* Global flag
		*/
		$this->_connected = true;
					
		/*
		* A fake connection object to signify success
		*/
		$this->cacheLibrary = new \stdClass; 
		
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

		wincache_ucache_clear ();
		
		$this->logFlushAllEvent(true);
		
	}
	
	/**
	* Flush an individual query from the wincache cache
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

		$success = wincache_ucache_delete($filename);

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
		
		$rs = wincache_ucache_get($filename,$success);
		
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
		
		$success = wincache_ucache_set  ( $filename , $contents ,$options->ttl );
		
		return $this->logWriteCacheEvent($filename,$options->ttl,$success);

	}
	
	/**
	* Returns an array of info about the cache
	*
	* @return array
	*/
	final public function cacheInfo() : array
	{

		$info = array(
			print_r(wincache_ucache_info(),true),
			print_r(wincache_ucache_meminfo(),true),
			print_r(wincache_fcache_meminfo(),true),
			print_r(wincache_fcache_fileinfo(),true)
			);
		return $info;
	}
}
