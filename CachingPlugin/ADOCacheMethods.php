<?php
/**
* Methods associated with caching recordsets
*
* This file is part of the ADOdb package.
*
* @copyright 2020-2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\CachingPlugin;
use ADOdb\LoggingPlugin\ADOLogger;
//use ADOdb\CachingPlugin\ADOCacheObject;
class ADOCacheMethods
{

	/*
	* An established connection to the database
	*/
	public object $connection;

	/*
	* The loaded definitions file
	*/
	public object $cacheDefinitions;
	
	/*
	* Different debugging to the connection
	*/
	public bool $debug = false;
	
	/*
	* A shortcut to a defined object
	*/
	public ?object $loggingObject = null;
	
	/*	
	* An integer index into the libraries
	*/
	public const MCLIB   = 1;
	public const MCLIBD  = 2;
	public const FILESYS = 3;
	public const APCU    = 4;
	public const REDIS   = 5;
	public const WINCACHE= 6;
	public const YAC	  = 7;
	
	protected array $libraryDescription = array(
		self::MCLIB=>'MEMCACHE',
		self::MCLIBD=>'MEMCACHED',
		self::FILESYS=>'FILESYSTEM',
		self::APCU=>'APCU',
		self::REDIS=>'REDIS',
		self::WINCACHE=>'WINCACHE',
		self::YAC=>'YAC'
		);
	
	/*
	* An indicator of which library we are using
	*/
	protected int $libraryFlag = 0;

	/*
	* $library will be populated with the proper library on connect
	* and is used later when there are differences in specific calls
	* between memcache and memcached
	*/
	protected $library = null;

	/*
	* Has a connection been established
	*/
	public bool $_connected = false;
	
	/*
	* Holds the instance of the library we will use
	*/
	public ?object $cacheLibrary = null;
	
	protected string $databaseType;
	protected string $database;
	protected string $user = 'adodb';

	protected int $numCacheHits = 0;
	protected int $numCacheMisses = 0;
	
	/*
	* A default cache options holder
	*/
	protected ?object $defaultCacheObject;

	/*
	* By default, caching service is not file based
	*/
	public bool $createdir = false;
	
	/**
	* Constructor, passed a mandatory previously 
	* established db connection
	*
	* @param object connection
	*/
	public function __construct(
				object $connection, 
				object $cacheDefinitions){
		
		
		$this->connection    	= $connection;
		$this->cacheDefinitions = $cacheDefinitions;
		
		$this->loggingObject = $cacheDefinitions->loggingObject;

		$this->defaultCacheObject = new ADOCacheObject;
			
		$this->databaseType = $connection->databaseType;
		$this->database     = $connection->database;
		
		
	
		/*
		* Startup the client connection
		*/
		
		/*
		* We do this just to bring the ADORecordSetArray class array into the
		* class space. We're not going to use it. We get an incomplete class
		* error reading a cached set if we don't. This is a special usage of
		* the class without a query id
		*/
		
		$rsClass = '\\ADORecordSet_array_' . $this->connection->databaseType;
		//$rsClass = '\\ADORecordSet_array_' . $this->connection->dataProvider;
		$classTemplate = new $rsClass(null,$this->connection);

		$cacheConnection = '\\ADOdb\\CachingPlugin\\' . $cacheDefinitions->serviceName . '\\ADOCacheMethods';
		
		$this->cachingObject = new $cacheConnection($connection,$cacheDefinitions);
		
		/*
		* We do this just to bring the ADORecordSetArray class array into the
		* class space. We're not going to use it. We get an incomplete class
		* error reading a cached set if we don't. This is a special usage of
		* the class without a query id
		*/
		$rsClass = '\\ADORecordSet_array_' . $this->connection->databaseType;
		$this->classTemplate = new $rsClass(null,$this->connection);

		
	}
	
	/**
	* Sets the default connection environment required by all plugins
	*
	* @param obj	$connection The ADOdb connection
	* @param obj	$cacheDefinitions The connection settings
	*
	* @return void
	*/
	final protected function setDefaultEnvironment(
			object $connection, 
			object $cacheDefinitions) : void {
				
		$this->connection    	= $connection;
		$this->cacheDefinitions = $cacheDefinitions;
	
		$this->loggingObject 	= $cacheDefinitions->loggingObject;
		
		$this->databaseType 	= $connection->databaseType;
		$this->database     	= $connection->database;
		
		$this->debug 			= $cacheDefinitions->debug;

		
		if ($this->loggingObject)
		{
			/*
			* Not the same as the driver debug, but there must be a
			* logging object available
			*/
			$this->debug = $cacheDefinitions->debug;
				
		}
		
		if ($this->debug){
			$message = '=========== CACHE SERVICE STARTUP ===========';
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			if ($this->connection->databaseType == 'pdo')
				$dbDriver = 'PDO/' . $this->connection->dsnType;
			else
				$dbDriver = $this->connection->databaseType;
				
			$message = 'Database driver is ' . $dbDriver;
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			
			$message = 'Caching method is ' . $cacheDefinitions->serviceName;
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}

		
		$this->writeLoggingPair(
			true,
			'The Caching Service is now active',
			'The Caching Service failed to activate'
			);
		
	}

	/**
	* Connect to one of the available sources
	* 
	* @return bool
	*/
	protected function connect(): bool {
		return true;
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
	public function writecache(
			string $filename, 
			string $contents, 
			bool $debug,
			int $secs2cache,
			?object $options=null) : bool {}
				
		
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
	public function readcache(
				string $filename,
				string &$err,
				int $secs2cache,
				string $arrayClass,
				?object $options=null) :?object{}
			
	

	/**
	* Flushes all entries from library
	*
	* @return void
	*/
	public function flushall() : void{}
	

	/**
	* Flush an individual query from memcache
	*
	* @param string $filename The md5 of the query
	* @param bool $debug option ignored as $this->debug prevails
	* @param obj  $options available driver options
	*
	* @return void
	*/
	public function flushcache(
					string $filename,
					bool $debug=false,
					?object $options=null) : void{}
					
		

	/**************************************************************************
	* Public methods
	**************************************************************************/
		
	/**
	* Flush cached recordsets that match a particular $sql statement.
	* If $sql == false, then we purge all files in the cache.
	*
	* @param ?string $sql,
	* @param ?array $inputarr
	* @param ?object $options 
	* 
	*/
	final public function cacheFlush(
				?string $sql=null,
				?array $inputarr=null,
				?object $options=null) : void {

		if (!$sql) 
		{
			/*
			* Empty the entire cache
			*/
			$this->flushall();
			return;
		}

		$f = $this->generateCacheName($sql.serialize($inputarr),false);
		
		$this->flushcache($f,$options);
	}
	
	/**
	 * generates md5 key for caching.
	 * Filename is generated based on:
	 *
	 *  - sql statement
	 *  - database type (oci8, ibase, ifx, etc)
	 *  - database name
	 *  - userid
	 *  - setFetchMode
	 *
	 * @param string $sql the sql statement
	 *
	 * @return string
	 */
	final protected function generateCacheName(string $sql) : string {

		$mode = $this->connection->fetchMode;
		
		return md5($sql.$this->databaseType.$this->database.$this->user.$mode);
		
	}
	
	/**
	* Unpacks the various inbound cache parameters and
	* returns a standardized format
	*
	* @param obj $obj
	* @param int $ttl
	*
	* @return obj
	*/
	final protected function unpackCacheObject(
		?object $obj, 
		int $ttl=0) : object {
		
		if (!is_object($obj))
		{
			/* 
			* Create a new default object
			*/
			$obj = new ADOCacheObject;
			if ($ttl)
				$obj->ttl = $ttl;
		}
		else
		{		
			
			if ($ttl)
				$obj->ttl = $ttl;
		}
		
		/*
		* Now the object is in standard format
		*/
		return $obj;
	}
	
	
	/**
 	 * convert a recordset into special format
	 *
	 * @param rs	the recordset
	 *
	 * @return	the CSV formatted data
	 */
	final public function _rs2serialize(
				object &$rs,
				$conn=false,
				$sql='') : string 	{
					
		$max = ($rs) ? $rs->FieldCount() : 0;

		if ($sql) $sql = urlencode($sql);
		// metadata setup

		if ($max <= 0 || $rs->dataProvider == 'empty') { // is insert/update/delete
			if (is_object($conn)) {
				$sql .= ','.$conn->Affected_Rows();
				$sql .= ','.$conn->Insert_ID();
			} else
				$sql .= ',,';

			$text = "====-1,0,$sql\n";
			return $text;
		}
		$tt = $rs->timeCreated;
		$tt = $tt ? $tt : time();

		## changed format from ====0 to ====1
		$line = "====1,$tt,$sql\n";

		if ($rs->databaseType == 'array') {
			$rows = $rs->_array;
		} else {
			$rows = array();
			while (!$rs->EOF) {
				$rows[] = $rs->fields;
				//print_r($rs->fields);
				$rs->MoveNext();
			}
		}
		for($i=0; $i < $max; $i++) {
			$o = $rs->FetchField($i);
			$flds[] = $o;
		}

		$savefetch = isset($rs->adodbFetchMode) ? $rs->adodbFetchMode : $rs->fetchMode;
		
		$class = '\\ADORecordSet_array_' . $this->connection->databaseType;
		//$class = '\\ADORecordSet_array_' . $this->connection->dataProvider;
		
		$rs2 = new $class($rs,$this->connection);
		
		$rs2->timeCreated = $rs->timeCreated; # memcache fix
		
		$rs2->sql = $rs->sql;
		$rs2->oldProvider = $rs->dataProvider;
		$rs2->initArrayFields($rows,$flds);
		$rs2->fetchMode = $savefetch;
		
		/*
		* Too much recursion
		*/
		unset($rs2->_queryID->connection);
		
		//print_r($rs2); 
		return $line.serialize($rs2);
	}
	
	/**
	* Checks that the library is loaded and tries to connect
	*
	* @return bool connected
	*/
	final protected function checkConnectionStatus() : bool
	{
		if (!$this->_connected)
			$this->connect();
		
		if (!$this->cacheLibrary) 
			/*
			* No object available
			*/
			return false;
			
		return true;
	}
	
	/**
	* Takes an inbound cached/serialized string and 
	* creates an ADOdb recordset from it. if possible
	*
	* @param string $filename
	* @param string  $recordsets
	* @param int     $secs2cache
	* @param string  $text
	*
	* @return mixed
	*/
	final protected function unpackCachedRecordset(
			string $filename,
			string $recordSet,
			int $secs2cache,
			?string $text='',
			bool $unpacked=false) : ?array {
		
		if ($this->debug && $this->loggingObject && !$recordSet) 
		{
			$message = sprintf('%s: Item with key %s, ttl %s does not exist in the cache %s', 
							strtoupper($this->cacheDefinitions->serviceName),
							$filename,
							$secs2cache,
							$text);
			$this->loggingObject->log($this->loggingObject::NOTICE,$message);
			return null;
		} 
		else if ($this->debug && $this->loggingObject)
		{
			$message = sprintf('%s: Item with key %s, ttl %s retrieved from the cache %s', 
					strtoupper($this->cacheDefinitions->serviceName),
					$filename,
					$secs2cache,
					$text);
			
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}

		$err = '';

		if (!$unpacked)
		{
			// hack, should actually use _csv2rs
			$rs = explode("\n", $recordSet);
			
			unset($rs[0]);
			$rs = join("\n", $rs);
			
			$rs = unserialize($rs);
		}
		else
			$rs = unserialize($recordSet);
		
		if (! is_object($rs)) {
			$message = sprintf('%s: Unable to unserialize $rs in unpackCachedRecordset',
						strtoupper($this->cacheDefinitions->serviceName));

			if ($this->loggingObject)
				$this->loggingObject->log($this->loggingObject::CRITICAL,$message);

			$err = 'Unable to unserialize $rs in unpackCachedRecordset';
			return array(null,$err);
		}
		
		if ($rs->timeCreated == 0) 
		{
			if ($this->debug && $this->loggingObject)
			{
				$message = sprintf('%s: Warning - Timestamp is zero in unserialize $rs',
							strtoupper($this->cacheDefinitions->serviceName));
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
			return array($rs,$err); // apparently have been reports that timeCreated was set to 0 somewhere
		}
		
		/*
		* Get remaining life of cached object
		*/
		$tdiff = intval($rs->timeCreated+$secs2cache - time());
		if ($tdiff <= 2) 
		{
			switch($tdiff)
			{
				case 2:
					if ((rand() & 15) == 0) {
						$message = sprintf('%s: Timeout 2',
								strtoupper($this->cacheDefinitions->serviceName));

						if ($this->loggingObject)
							$this->loggingObject->log($this->loggingObject::CRITICAL,$message);

						$err = "Timeout 2";
						return array(null,$err);
					}
					break;
				case 1:
					if ((rand() & 3) == 0) {
						$message = sprintf('%s: Timeout 1',strtoupper($this->cacheDefinitions->serviceName));
						if ($this->loggingObject)
							$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
						$err = "Timeout 1";
						return array(null,$err);
					}
					break;
				default:
					$message = sprintf('%s: Timeout 0',strtoupper($this->cacheDefinitions->serviceName));
					if ($this->loggingObject)
						$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
					$err = "Timeout 0";
					return array(null,$err);
			}
		}
		if ($this->debug && $this->loggingObject)
		{
			$message = sprintf('%s: Successfully unserialized $rs',strtoupper($this->cacheDefinitions->serviceName));
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			
			$message = sprintf('%s: Item with key %s, ttl %s has a remaining life of %s seconds', 
					strtoupper($this->cacheDefinitions->serviceName),
					$filename,
					$secs2cache,
					$tdiff);
			
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		return array($rs,$err);
	}
	
	/**
	* Writes a logging message for the writecache method
	*
	* @param string $filename
	* @param int $secs2cache
    * @param bool $success
	* @param string $text
	*	
	* @return bool
	*/
	final protected function logWriteCacheEvent(
			string $filename,
			int $secs2cache,
			bool $success,
			?string $text='') : bool {
			
		$this->writeLoggingPair(
			$success,
			sprintf('Successfully saved contents on key %s with a TTL of %s seconds %s',$filename,$secs2cache,$text),
			sprintf('Failed to save contents of key %s with a TTL of %s seconds %s',$filename,$secs2cache,$text)
				);
		return true;
	}
	
	/**
	* Writes a logging message for the flushcache method
	*
	* @param string $filename
    * @param bool $success
	*	
	* @return bool
	*/
	final protected function logFlushCacheEvent(
			string $filename,
			bool $success,
			?string $extraText = '') : bool {
		
		if (!$this->loggingObject)
			return true;

		$this->writeLoggingPair(
			$success,
			sprintf('Entry with key %s flushed from cache %s',$filename, $extraText),
			sprintf('Failed to remove entry with key %s from cache %s',$filename, $extraText),$this->loggingObject::NOTICE
			);
		
		return true;
	}
	
	/**
	* Writes a logging message for the flushall method
	*
    * @param bool $success
	*	
	* @return bool
	*/
	final protected function logFlushAllEvent(
			bool $success) : bool {
			
		$this->writeLoggingPair( 
			$success,
			'Cache flush was successful',
			'Failed to flush cache'
			);
			
		return true;
	}
	
	/**
	* Unpacks the passed cache object and returns the 
	* appropriate overrides if available
	*
	* @param ?object the cache object
	* @param string $serverkey
	* @param int #
	
	/**
	* Prints a set of info about the cache
	*
	* @return array
	*/
	public function cacheInfo()
	{
		
		if ($this->debug)
		{
			$message = sprintf('%s: A Request to serve the service info was made',
								strtoupper($this->cacheDefinitions->serviceName)
								);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		
		return $this->cachingObject->cacheInfo();
	}
	
	/**
	* Writes logging messages based on successful execution or failure
	*
	* @param	string	$success	An evaluation to true/false
	* @param	string	$successMessage	 A message if true
	* @param	string	$successMessage	 A message if false
	* @param	int     $failLevel 
	*
	* @return void
	*/
	final protected function writeLoggingPair($success,string $successMessage, string $failMessage,int $failLevel=0) : void
	{

		if (!$this->loggingObject)
			/*
			* No logger defined
			*/
			return;
		
		if ($success && $successMessage && $this->debug)
		{
			$message = sprintf('%s: %s',
							   strtoupper($this->cacheDefinitions->serviceName),
							   $successMessage);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		else if ($failMessage)
		{
			
			if (!$failLevel)
				$failLevel = $this->loggingObject::CRITICAL;
			
			$message = sprintf('%s: %s',
							   strtoupper($this->cacheDefinitions->serviceName),
							   $failMessage);
			$this->loggingObject->log($failLevel,$message);
		}
	}
}
