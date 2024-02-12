<?php
/**
* Methods associated with caching recordsets using the local filesystem
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\CachingPlugin\filesystem;

use ADOdb\CachingPlugin\ADOCacheObject;
use ADOdb\CachingPlugin\filesystem\ADOCacheDefinitions;

final class ADOCacheMethods extends \ADOdb\CachingPlugin\ADOCacheMethods
{
	
	/*
	* The location of the root of the filesystem
	*/
	protected ?string $cacheDirectory = '';
	
	/*
	* Tells the ADOdb class that this is not a mem based cache service
	*/
	public bool $createdir = true;
	
	protected bool $notSafeMode = true;
	
	/*
	* Overrides the directory permissions from the definitions file
	*/
	protected string $cacheDirectoryPermissions = '0771';
	
	/*
	* Block size for reading cache data
	*/
	protected int $cacheReadSize = 128000;
	
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
				
		global $ADODB_CACHE_DIR;
		
		$this->setDefaultEnvironment($connection,$cacheDefinitions);
		
		
		/*
		* Sets the required special variables
		*/		
		
		$this->cacheDirectory = $this->cacheDefinitions->cacheDirectory;
		
		$ADODB_CACHE_DIR = $this->cacheDirectory;
		
		if ($this->cacheDirectory && $this->debug)
		{
			$message = sprintf('FILESYSTEM: The local cache directory is %s',$this->cacheDirectory);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		
		if ($this->cacheDefinitions->cacheDirectoryPermissions)
			$this->cacheDirectoryPermissions = $this->cacheDefinitions->cacheDirectoryPermissions;
		

		/*
		* Make sure the root of the cache directory exists
		*/
		$dir = $this->createdir();

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
		* Is the cache directory loaded
		*/
		if ($this->cacheDirectory) {
			if ($this->debug)
			{
				$message = 'FILESYSTEM: Loaded the File System Libary';
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
		} else {
			$message = 'FILESYSTEM: $cacheDirectory Not Set!';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;
		}

		/*
		* Global flag
		*/
		$this->cachingIsAvailable = true;

		/*
		* Because this is a home-written library, we attach ourselves as the end-point
		*/
		
		$this->cacheLibrary = $this;
		
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
		
		$success = @unlink($recordsetKey);
		
		$this->logflushCacheEvent($recordsetKey,$success);

	}
	
	/**
	* Erases all the cache files and all the subdirectory
	* entries in the entire cache system
	*
	* @return void
	*/
	final public function flushall() : void
	{
		if (!$this->checkConnectionStatus())
			return;

		$rez = $this->_dirFlush($this->cacheDirectory);
						
		$this->logFlushAllEvent($rez);
	}

	/**
	* Tries to return a recordset from the cache
	*
	* @param string $recordsetKey the on disk file name of the request
	* @param string $arrayClass
	* @param ADOCacheObject $options
	*
	* @return recordset
	*/
	final public function readcache(
				string $recordsetKey,
				string $arrayClass,
				?ADOCacheObject $options=null) :array {
			
		$err = '';
		$serializedItem = true;

		if (!is_object($options))
			$options = $this->defaultCacheObject;

		$message = sprintf('FILESYSTEM: Reading key %s for cached data',$recordsetKey);
		$this->loggingObject->log($this->loggingObject::DEBUG,$message);
				
		/*
		* Returns the contents of the file as a string
		*/
		list($jObject,$err) = $this->readDataFromFileSystem($recordsetKey,0,$arrayClass);
				
		if (!$jObject) {
			$message = sprintf('FILESYSTEM: Failed to retrieve key %s for reading, %s',$recordsetKey,$err);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			return array(false,$err);
		}
		
		//return array($rs,$err);
		list ($cObject, $err) = $this->unpackCachedRecordset(
			$recordsetKey, 
			$jObject,
			$options->ttl,
			'',
			$serializedItem);
		
		return array($cObject,$err);
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

		if (!is_object($options))
			$options = $this->defaultCacheObject;
			
		$success =  $this->adodb_write_file($recordsetKey, $contents,$options->debug);

		return $this->logWriteCacheEvent($recordsetKey,$options->ttl,$success);

	}
	
	/**
	* Save a file $recordsetKey and its $contents (normally for caching) with file locking
	* Returns true if ok, false if fopen/fwrite error, 0 if rename error (eg. file is locked)
	*/
	private function adodb_write_file(
				string $recordsetKey, 
				string $contents,
				bool $debug=false) : bool
	{
	
				
		if (!($fd = @fopen($recordsetKey, 'a'))) 
			return false;
		
		if (flock($fd, LOCK_EX) && ftruncate($fd, 0)) 
		{
			if (fwrite( $fd, $contents )) 
			{
				$ok = true;
				$message = sprintf('FILESYSTEM: LOCK_EX succeeded for %s',$recordsetKey);
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
			else 
				$ok = false;
			
			fclose($fd);
			@chmod($recordsetKey,0644);
		}
		else 
		{
			fclose($fd);
			$message = sprintf('FILESYSTEM: Failed acquiring lock for %s',$recordsetKey);
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			$ok = false;
		}
		return $ok;
	}

	/**
	* Open CSV file and convert it into Data.
	*
	* @param string	url  	file/ftp/http url
	* @param int 	timeout	dispose if recordset has been alive for $timeout secs
	* @param string rsclass	class name of recordset to return
	*
	* @return array(recordset, string)
	*/
	private function readDataFromFileSystem(
			string $url, 
			?int $timeout=0, 
			?string $rsclass='ADORecordSet_array') {
		
		$false = null;
		$err   = false;
		
		$fp = @fopen($url,'rb');
		
		if (!$fp) {
			
			$err = $url.' file/URL not found';
			return null;
		}
		
		@flock($fp, LOCK_SH);
		$arr = array();
		$ttl = 0;

		/*
		* Now we have put a flock on the file, we can read it
		* straight into memory
		*/
		$fileContents = file_get_contents($url);

		$fileObj = json_decode($fileContents);

		if (!is_object($fileObj)) {
			$err = "Unable to decode file contents";
			fclose($fp);
			return array(null,$err);
		}

		fclose($fp);

		if ($timeout > 0) {
			$err = " Illegal Timeout $timeout ";
			return array(null,$err);
		}

		if ($fileObj->operation == -1) {

			/*
			* Re-make an empty recordset
			*/
			$rs = new $rsclass($val=true);
			$rs->fields = array();
			$rs->timeCreated = $fileObj->timeCreated;
			$rs->EOF = true;
			$rs->_numOfFields = 0;
			$rs->sql = $fileObj->sql;
			$rs->affectedrows = $fileObj->affectedrows;
			$rs->insertid = $fileObj->insertid;
			
			$fileObj->recordSet = serialize($rs);
			$fileContents = json_encode($fileObj);
			return array($fileContents,'');
		}

		# Under high volume loads, we want only 1 thread/process to _write_file
		# so that we don't have 50 processes queueing to write the same data.
		# We use probabilistic timeout, ahead of time.
		#
		# -4 sec before timeout, give processes 1/32 chance of timing out
		# -2 sec before timeout, give processes 1/16 chance of timing out
		# -1 sec after timeout give processes 1/4 chance of timing out
		# +0 sec after timeout, give processes 100% chance of timing out
		
		
		if($timeout >0){
			$tdiff = (integer)( $fileObj->timeCreated + $timeout - time());
			if ($tdiff <= 2) {
				switch($tdiff) {
				case 4:
				case 3:
					if ((rand() & 31) == 0) {
						fclose($fp);
						$err = "Timeout 3";
						return array(null,$err);
					}
					break;
				case 2:
					if ((rand() & 15) == 0) {
						fclose($fp);
						$err = "Timeout 2";
						return array(null,$err);
					}
					break;
				case 1:
					if ((rand() & 3) == 0) {
						fclose($fp);
						$err = "Timeout 1";
						return array(null,$err);
					}
					break;
				default:
					fclose($fp);
					$err = "Timeout 0";
					return array(null,$err);
				} // switch

			} // if check flush cache
		}// (timeout>0)
			
		if ($fileObj->operation == '1') 
		{
			/*
			* We know everything is good, so we can just return the contents
			* of the file. The common decoder in the parent will handle the rest
			*/
			return array($fileContents,$err);
			
		}

		return array(null,'Unable to obtain file contents');
	
	
	
	}

	/**
	 * generates md5 key for caching.
	 * Filename is generated based on:
	 * 
	 * @param string $sql the sql statement
	 *
	 * @return string
	 */
	final public function generateCacheName(string $sql) : string {

		$hash = parent::generateCacheName($sql);

		$hashDir = $this->createdir($hash);
		return sprintf('%s/adodb_%s.cache', $hashDir, $hash);
				
	}
		
	/**
	* Private function to recursively erase all of the files and 
	* subdirectories in a directory.
	*
	* @param string $dir the directory to empty
	* @param bool $killCurrentLevel Remove the current directory entry
	*
	* @return bool success
	*/
	private function _dirFlush(
			string $dir, 
			bool $killCurrentLevel = false) : bool {
				
		if(!$dh = @opendir($dir))
		{			
			$message = sprintf('FILESYSTEM: flushall could not open directory %s', $dir);
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);	
			return false;
		}
		while (($obj = readdir($dh))) {
			if($obj=='.' || $obj=='..') 
				continue;
			
			$f = $dir.'/'.$obj;

			
			if (is_dir($f)) {
				/*
				* Recurse to the subdirectory
				*/
				$this->_dirFlush($f, true);
			}
			else
				@unlink($f);
		}
		/*
		* After we have removed all the files, delete
		* the current subdirectory entry
		*/
		if ($killCurrentLevel === true) {
			@rmdir($dir);
		}
		return true;
	}
	
	/**
	* create temp directories
	*
	* @ param string $hash
	*
	* @ return string
	*/
	private function createdir(?string $hash=null): string {
		
		if ($hash)
			$dir = $this->getDirName($hash);
		else 
			$dir = $this->cacheDirectory;

		if ($this->notSafeMode && !file_exists($dir)) {
			$oldu = umask(0);
			if (!@mkdir($dir, $this->cacheDirectoryPermissions)) {
				if(!is_dir($dir)) 
				{
					$message = sprintf('FILESYSTEM: Could not create directory %s', $dir);
					$this->loggingObject->log($this->loggingObject::CRITICAL,$message);	
					return null;
				}
			}
			umask($oldu);
		}

		return $dir;
	}
	
	/**
	* Returns the current cache directory
	*
	* @param string $hash
	*
	* @return string
	*/
	private function getDirName(string $hash) : string {
		
				
		//if (!isset($this->notSafeMode)) {
		//	$this->notSafeMode = !ini_get('safe_mode');
		//}

		return ($this->notSafeMode) ? $this->cacheDirectory .'/'.substr($hash,0,2) : $this->cacheDirectory;
	}
	
	/**
	* Returns an array of info about the cache, of which
	* there is none but you could invent something
	*
	* @return array
	*/
	final public function cacheInfo() : array
	{

		return array();
	}

}
