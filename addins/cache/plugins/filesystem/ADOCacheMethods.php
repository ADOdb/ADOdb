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
namespace ADOdb\addins\cache\plugins\filesystem;

final class ADOCacheMethods extends \ADOdb\addins\cache\ADOCacheMethods
{
	
	/*
	* The location of the root of the filesystem
	*/
	protected ?string $cacheDirectory = '';
	
	/*
	* Tells the ADOdb class that this is not a mem based cache service
	*/
	public bool $createdir = true;
	
	
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
	* @param obj $connection   A Valid ADOdb Connection
	* @param obj $cacheDefinitions An ADOdbCacheDefinitions Class
	*
	* @return obj 
	*/
	final public function __construct(
			object $connection, 
			object $cacheDefinitions){
				
		global $ADODB_CACHE_DIR;
		
		$this->setDefaultEnvironment($connection,$cacheDefinitions);
		
		/*
		* Sets the custom items from this plugins\memcache
		*/		
		
		$this->cacheDirectory = $cacheDefinitions->cacheDirectory;
		
		$ADODB_CACHE_DIR = $this->cacheDirectory;
		
		if ($this->cacheDirectory && $this->debug)
		{
			$message = sprintf('FILESYSTEM: The local cache directory is %s',$this->cacheDirectory);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		
		if ($cacheDefinitions->cacheDirectoryPermissions)
			$this->cacheDirectoryPermissions = $cacheDefinitions->cacheDirectoryPermissions;
		

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
				$message = 'FILESYSTEM: Loaded the File System Library';
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
		$this->_connected = true;
		
		$this->cacheLibrary = $this;
		
		return true;
	}
	
	
	/**
	* Flush an individual query from the fs cache
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
		
		$success = @unlink($filename);
		
		$this->logFlushCacheEvent($filename,$success);
			
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
	* @param string $filename the md5 code of the request
	* @param string $err      The error by reference
	* @param int $secs2cache
	* @param string[] $options
	*
	* @return recordset
	*/
	final public function readcache(
				string $fileName,
				string &$err,
				int $secs2cache,
				string $arrayClass,
				?object $options=null) :?object {
			
		$err = '';
		$serializedItem = true;
		
		/*
		* Standardize the parameters
		*/
		$options = $this->unpackCacheObject($options,$secs2cache);
		
		/*
		* $err is passed by reference. Would be better to return a list
		* The value  return is a true serialized recordset.
		*/
		$rs = $this->csv2rs($fileName,$err,$options->ttl,$arrayClass);
		
		if (!$rs)
		{
			$serializedItem = false;
			$rs = '';
		}
		
		list ($rs, $err) = $this->unpackCachedRecordset(
			$fileName, 
			$rs,
			$options->ttl,
			'',
			$serializedItem);
		
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

		
		$success =  $this->adodb_write_file($filename, $contents,$debug);
		
		return $this->logWriteCacheEvent($filename,$options->ttl,$success);

	}
	
	/**
	* Save a file $filename and its $contents (normally for caching) with file locking
	* Returns true if ok, false if fopen/fwrite error, 0 if rename error (eg. file is locked)
	*/
	private function adodb_write_file(
				string $filename, 
				string $contents,
				bool $debug=false) : bool
	{
		
		
		# http://www.php.net/bugs.php?id=9203 Bug that flock fails on Windows
		# So to simulate locking, we assume that rename is an atomic operation.
		# First we delete $filename, then we create a $tempfile write to it and
		# rename to the desired $filename. If the rename works, then we successfully
		# modified the file exclusively.
		# What a stupid need - having to simulate locking.
		# Risks:
		# 1. $tempfile name is not unique -- very very low
		# 2. unlink($filename) fails -- ok, rename will fail
		# 3. adodb reads stale file because unlink fails -- ok, $rs timeout occurs
		# 4. another process creates $filename between unlink() and rename() -- ok, rename() fails and  cache updated
		if (strncmp(PHP_OS,'WIN',3) === 0) 
		{
			// skip the decimal place
			$mtime = substr(str_replace(' ','_',microtime()),2);
			// getmypid() actually returns 0 on Win98 - never mind!
			$tmpname = $filename.uniqid($mtime).getmypid();
			if (!($fd = @fopen($tmpname,'w'))) 
				return false;
			
			if (fwrite($fd,$contents)) 
				$ok = true;
			else 
				$ok = false;
			fclose($fd);

			if ($ok) {
				@chmod($tmpname,0644);
				// the tricky moment
				@unlink($filename);
				if (!@rename($tmpname,$filename)) {
					@unlink($tmpname);
					$ok = 0;
				}
				if (!$ok)
				{
					$message = sprintf('FILESYSTEM: Rename %s failed',$tmpname);
					$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
				} 
				else if ($this->debug)
				{
					$message = sprintf('FILESYSTEM: Rename %s succeeded',$tmpname);
					$this->loggingObject->log($this->loggingObject::DEBUG,$message);
				}
			}
			return $ok;
		}
		/*
		* Other *nix systems
		*/
		if (!($fd = @fopen($filename, 'a'))) 
			return false;
		
		if (flock($fd, LOCK_EX) && ftruncate($fd, 0)) 
		{
			if (fwrite( $fd, $contents )) 
				$ok = true;
			else 
				$ok = false;
			
			fclose($fd);
			@chmod($filename,0644);
		}
		else 
		{
			fclose($fd);
			$message = sprintf('FILESYSTEM: Failed acquiring lock for %s',$filename);
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			$ok = false;
		}
		return $ok;
	}

	/**
	* Open CSV file and convert it into Data.
	*
	* @param url  		file/ftp/http url
	* @param err		returns the error message
	* @param timeout	dispose if recordset has been alive for $timeout secs
	*
	* @return		recordset, or false if error occurred. If no
	*			error occurred in sql INSERT/UPDATE/DELETE,
	*			empty recordset is returned
	*/
	private function csv2rs(
			string $url, 
			string &$err,
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

		if ($meta = fgetcsv($fp, 32000, ",")) {
			// check if error message
			if (strncmp($meta[0],'****',4) === 0) {
				$err = trim(substr($meta[0],4,1024));
				fclose($fp);
				return null;
			}
			// check for meta data
			// $meta[0] is -1 means return an empty recordset
			// $meta[1] contains a time

			if (strncmp($meta[0], '====',4) === 0) {

				if ($meta[0] == "====-1") {
					if (sizeof($meta) < 5) {
						$err = "Corrupt first line for format -1";
						fclose($fp);
						return null;
					}
					fclose($fp);

					if ($timeout > 0) {
						$err = " Illegal Timeout $timeout ";
						return null;
					}

					$rs = new $rsclass($val=true);
					$rs->fields = array();
					$rs->timeCreated = $meta[1];
					$rs->EOF = true;
					$rs->_numOfFields = 0;
					$rs->sql = urldecode($meta[2]);
					$rs->affectedrows = (integer)$meta[3];
					$rs->insertid = $meta[4];
					return $rs;
				}
				
				# Under high volume loads, we want only 1 thread/process to _write_file
				# so that we don't have 50 processes queueing to write the same data.
				# We use probabilistic timeout, ahead of time.
				#
				# -4 sec before timeout, give processes 1/32 chance of timing out
				# -2 sec before timeout, give processes 1/16 chance of timing out
				# -1 sec after timeout give processes 1/4 chance of timing out
				# +0 sec after timeout, give processes 100% chance of timing out
				
				if (sizeof($meta) > 1) {
					if($timeout >0){
						$tdiff = (integer)( $meta[1]+$timeout - time());
						if ($tdiff <= 2) {
							switch($tdiff) {
							case 4:
							case 3:
								if ((rand() & 31) == 0) {
									fclose($fp);
									$err = "Timeout 3";
									return $false;
								}
								break;
							case 2:
								if ((rand() & 15) == 0) {
									fclose($fp);
									$err = "Timeout 2";
									return $false;
								}
								break;
							case 1:
								if ((rand() & 3) == 0) {
									fclose($fp);
									$err = "Timeout 1";
									return $false;
								}
								break;
							default:
								fclose($fp);
								$err = "Timeout 0";
								return $false;
							} // switch

						} // if check flush cache
					}// (timeout>0)
					$ttl = $meta[1];
				}
				//================================================
				// new cache format - use serialize extensively...
				if ($meta[0] === '====1') {
					// slurp in the data

					$text = fread($fp,$this->cacheReadSize);
					if (strlen($text)) {
						while ($txt = fread($fp,$this->cacheReadSize)) {
							$text .= $txt;
						}
					}
					fclose($fp);
					$rs = unserialize($text);
					if (is_object($rs)) 
						$rs->timeCreated = $ttl;
					else 
					{
						$err = "Unable to unserialize recordset";
					}
					return $text;
				}

				$meta = false;
				$meta = fgetcsv($fp, 32000, ",");
				if (!$meta) {
					fclose($fp);
					$err = "Unexpected EOF 1";
					return $false;
				}
			}

			// Get Column definitions
			$flds = array();
			foreach($meta as $o) {
				$o2 = explode(':',$o);
				if (sizeof($o2)!=3) {
					$arr[] = $meta;
					$flds = false;
					break;
				}
				$fld = new ADOFieldObject();
				$fld->name = urldecode($o2[0]);
				$fld->type = $o2[1];
				$fld->max_length = $o2[2];
				$flds[] = $fld;
			}
		} else {
			fclose($fp);
			$err = "Recordset had unexpected EOF 2";
			return $false;
		}

		// slurp in the data

		$text = '';
		while ($txt = fread($fp,$this->cacheReadSize)) {
			$text .= $txt;
		}

		fclose($fp);
		@$arr = unserialize($text);
		if (!is_array($arr)) {
			$message = "FILESYSTEM: Recordset had unexpected EOF (in serialized recordset)";
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return $false;
		}
		$rs = new $rsclass();
		$rs->timeCreated = $ttl;
		$rs->InitArrayFields($arr,$flds);
		return $rs;
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
	* @ param bool debug
	*
	* @ return string
	*/
	final public function createdir(string $hash, bool $debug): ?string {
		

		$dir = $this->getdirname($hash);
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
	final public function getDirName(string $hash) : string {
		
		global $ADODB_CACHE_DIR;
		
		if (!isset($this->notSafeMode)) {
			$this->notSafeMode = !ini_get('safe_mode');
		}
		return ($this->notSafeMode) ? $ADODB_CACHE_DIR.'/'.substr($hash,0,2) : $ADODB_CACHE_DIR;
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
