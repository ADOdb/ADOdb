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

use ADOdb\CachingPlugin\ADOCacheRecordset;
use ADOdb\LoggingPlugin\ADOLogger;

abstract class ADOCacheMethods
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
	public bool $cachingIsAvailable = false;

	/*
	* If we could not connect, keep trying
	*/
	public bool $retryConnection = true;
	
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
	* The name of the function that will be used to read the cache
	*/
	protected string $readCacheFunction = 'get';
	/*
	* The name of the function that will be used to write the cache
	*/
	protected string $writeCacheFunction = 'set';

	/*
	* The name of the function that will be used to flush the cache
	*/
	protected string $flushAllCacheFunction = 'flush';

	/*
	* The name of the function that will be used to flush an 
	* individual set from the cache
	*/
	protected string $flushIndividualSetFunction = 'delete';

	/*
	* The name of the function that will be used to return 
	* informatton from the cache
	*/
	protected string $cacheInfoFunction = 'info';

	/*
	* The last generated cache key
	*/
	protected string $lastRecordsetKey = '';


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
		
	}


	/**
	* Constructor, passed a mandatory previously 
	* established db connection
	*
	* @param object connection
	*/
	public function xx__construct(
				object $connection, 
				object $cacheDefinitions){
		
			
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
	* @param obj	$cacheDefinitions The connection overrides
	*
	* @return void
	*/
	final protected function setDefaultEnvironment(
			object &$connection, 
			?object $cacheDefinitions=null) : void {

		global $ADODB_LOGGING_OBJECT;
				
		$this->connection    	= $connection;

		if (!is_object($cacheDefinitions))
		{
			/*
			* No cache definitions passed, create a default
			* The source comes from the type of caching
			*/
			$cacheDefinitions = new ADOCacheDefinitions;
	
		}

		if (!$cacheDefinitions->loggingObject)
		{
			/*
			* No logging object passed, use the global
			*/
			$cacheDefinitions->loggingObject = $ADODB_LOGGING_OBJECT;
		}

		$this->cacheDefinitions = $cacheDefinitions;
	
		$this->loggingObject 	= $cacheDefinitions->loggingObject;
		
		$this->databaseType 	= $connection->databaseType;
		$this->database     	= $connection->database;
		
		$this->debug 			= $cacheDefinitions->debug;

		/*
		* Initialize the default cache object which will give
		* us a default TTL etc	
		*/
		$this->defaultCacheObject = new ADOCacheObject;

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

		$connection->setCachingPlugin($this);
		
	}

	/**
	* Connect to one of the available sources
	* 
	* @return bool
	*/
	abstract protected function connect(): bool;
	
	
	/**
	* Writes a cached data set
	*
	* @param string $recordsetKey
	* @param string $contents
	* @param ADOCacheObject    $options
	*
	* @return bool
	*/
	public function writecache(
		string $recordsetKey, 
		string $contents, 
		?ADOCacheObject $options=null) : bool {
		
		if (!$this->checkConnectionStatus())
			return false;
			
		if (!is_object($options))
			$options = new ADOCacheObject();

		$success = $this->cacheLibrary->{$this->writeCacheFunction}( $recordsetKey , $contents ,$options->ttl );
		
		return $this->logWriteCacheEvent(
			$recordsetKey,
			$options->ttl,
			$success);

	}
		
	/**
	* Tries to return a recordset from the cache
	*
	* @param string $recordsetKey the md5 code of the request
	* @param string $arrayClass     e
	* @param ADOCacheObject    $options
	*
	* @return array(ADORecordset,string)
	*/
	public function readcache(
		string $recordsetKey,
		string $arrayClass,
		?ADOCacheObject $options=null) : array 
	{
				

		
		if (!$this->checkConnectionStatus())
			return array(null,'No connection to cache');
		
		/*
		* If the function succeeds, it returns a json encoded ADOCacheRecordset object
		* of which the recordset is one part
		*/
		$jObject = $this->cacheLibrary->{$this->readCacheFunction}($recordsetKey);
		if (!$jObject) 
		{
			/*
			* No ADOCacheRecordset found
			*/
			$this->numCacheMisses++;
			if ($this->debug && $this->loggingObject)
			{
				$message = sprintf('%s: Item with key %s not found in the cache',
						strtoupper($this->cacheDefinitions->serviceName),
						$recordsetKey);
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}

			return array(null,'No recordset found in the cache');
		}

		//print_r($jObject); exit;

		/*
		* Convert the json encoded ADOCacheRecordset object into an ADORecordset object
		*/
		list ($recordSet, $err) = $this->unpackCachedRecordset(
			$recordsetKey, 
			$jObject,
			$options->ttl
		);
		
		return array($recordSet,$err);
		
	}
	/**
	* Flushes all entries
	*
	* @return void
	*/
	public function flushall() : void
	{
				
		if (!$this->checkConnectionStatus())
			return;

		$success = $this->cacheLibrary->{$this->flushAllCacheFunction}();
		
		$this->logFlushAllEvent($success);
		
	}
	
	/**
	* Flush an individual query from the apcu cache
	*
	* @param string $recordsetKey The md5 of the query
	* @param ADOCacheObject $additional options unused
	*
	* @return void
	*/
	public function flushIndividualSet(?string $recordsetKey=null,?ADOCacheObject $options=null ) : void {	
					
		if (!$this->checkConnectionStatus())
			return;

		if (!$recordsetKey)
			$recordsetKey = $this->lastRecordsetKey;

		if (!$recordsetKey)
			return;

		$success = $this->cacheLibrary->{$this->flushIndividualSetFunction}($recordsetKey);

		$this->logflushCacheEvent($recordsetKey,$success);
		
	}

	/**************************************************************************
	* Public methods
	**************************************************************************/
	
	/**
	* Alias for flushCacheByQuery
	*
	* @param ?string $sql,
	* @param ?array $inputarr
	* @param ?ADOCacheObject $options 
	* 
	*/
	final public function cacheFlush(
		?string $sql=null,
		?array $inputarr=null,
		?ADOCacheObject $options=null) : void {

		$this->flushCacheByQuery($sql,$inputarr,$options);
	}

	/**
	* Flush cached recordsets that match a particular $sql statement.
	* If $sql == false, then we purge all files in the cache.
	*
	* @param ?string $sql,
	* @param ?array $inputarr
	* @param ?ADOCacheObject $options 
	* 
	*/
	final public function flushCacheByQuery(
				?string $sql=null,
				?array $inputarr=null,
				?ADOCacheObject $options=null) : void {

		if (!$sql) 
		{
			/*
			* Empty the entire cache
			*/
			$this->flushall();
			return;
		}

		$f = $this->generateCacheName($sql.serialize($inputarr),false);
		
		$this->flushCache($f,$options);
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
	public function generateCacheName(string $sql) : string {

		global $ADODB_FETCH_MODE;

		$mode = $this->connection->fetchMode;

		if ($this->connection->fetchMode === false) {
			
			$mode = $ADODB_FETCH_MODE;
		} else {
			$mode = $this->fetchMode;
		}
		
		$this->lastRecordsetKey = md5($sql.$this->databaseType.$this->database.$this->user.$mode);

		return $this->lastRecordsetKey;
		
	}
	
	/**
 	 * convert a recordset into a format that can be sent to the cache
	 *
	 * @param rs	the recordset
	 *
	 * @return	the CSV formatted data
	 */
	final public function packRecordSetForCaching(
				object &$rs,
				$conn=false,
				$sql='') : array 	{
			
		$cObject = new ADOCacheRecordset;

		$cObject->sql = $sql;
		$max = ($rs) ? $rs->FieldCount() : 0;

		if ($sql) $sql = urlencode($sql);
		// metadata setup


		if ($max <= 0 || $rs->dataProvider == 'empty') { // is insert/update/delete
			
			$cObject->operation = -1;
			$cObject->timeCreated = time();
						
			if (is_object($conn)) {
				$sql .= ','.$conn->Affected_Rows();
				$sql .= ','.$conn->Insert_ID();
				
				$cObject->affectedRows = $conn->Affected_Rows();
				$cObject->insertID     = $conn->Insert_ID();
			} else
				$sql .= ',,';

			$text = "====-1,0,$sql\n";
			return array($text,json_encode($cObject));
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
				$rs->MoveNext();
			}
		}
		for($i=0; $i < $max; $i++) {
			$o = $rs->FetchField($i);
			$flds[] = $o;
		}

		$savefetch = isset($rs->adodbFetchMode) ? $rs->adodbFetchMode : $rs->fetchMode;
		
		$class = '\\ADORecordSet_array_' . $this->connection->databaseType;
		
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

		/*
		* Sets the environment for the recordset
		* We must serialize the recordset to be able to
		* retrieve it as a \ADORecordSet_array_ class
		* later instead of a \stdClass class
		*/
		$cObject->operation = 1;
		$cObject->recordSet = serialize($rs2);
		$cObject->timeCreated = $rs->timeCreated;
		$cObject->className = $class;
		$jcObject = json_encode($cObject);
		
		return array($line.serialize($rs2),$jcObject);
	}
	
	/**
	* Checks that the library is loaded and tries to connect if not
	*
	* @return bool connected
	*/
	final protected function checkConnectionStatus() : bool
	{
		if (!$this->cachingIsAvailable && $this->retryConnection)
		{
			$this->connect();
		}
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
	* @param string $recordsetKey
	* @param string  $jObject	The json encoded ADOCacheRecordset
	* @param int     $secs2cache
	* @param string  $text
	*
	* @return mixed
	*/
	final protected function unpackCachedRecordset(
			string $recordsetKey,
			string $jObject,
			int $secs2cache,
			?string $text='',
			bool $unpacked=false) : ?array {
		
		if ($this->debug && $this->loggingObject && !$jObject) 
		{
			$message = sprintf('%s: Item with key %s, ttl %s does not exist in the cache %s', 
							strtoupper($this->cacheDefinitions->serviceName),
							$recordsetKey,
							$secs2cache,
							$text);
			$this->loggingObject->log($this->loggingObject::NOTICE,$message);
			return null;
		} 
		else if ($this->debug && $this->loggingObject)
		{
			$message = sprintf('%s: Item with key %s, ttl %s retrieved from the cache %s', 
					strtoupper($this->cacheDefinitions->serviceName),
					$recordsetKey,
					$secs2cache,
					$text);
			
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}

		$err = '';

		$cObject = json_decode($jObject);

		if (!$cObject) {
			$message = sprintf('%s: Unable to decode $cObject in unpackCachedRecordset',
						strtoupper($this->cacheDefinitions->serviceName));

			if ($this->loggingObject)
				$this->loggingObject->log($this->loggingObject::CRITICAL,$message);

			$err = 'Unable to decode $cObject in unpackCachedRecordset';
			return array(null,$err);
		}
		
		$rs = unserialize($cObject->recordSet);
		
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
					$recordsetKey,
					$secs2cache,
					$tdiff);
			
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		return array($rs,$err);
	}
	
	/**
	* Writes a logging message for the writecache method
	*
	* @param string $recordsetKey
	* @param int $secs2cache
    * @param bool $success
	* @param string $text
	*	
	* @return bool
	*/
	final protected function logWriteCacheEvent(
			string $recordsetKey,
			int $secs2cache,
			bool $success,
			?string $text='') : bool {
			
		$this->writeLoggingPair(
			$success,
			sprintf('Successfully saved contents on key %s with a TTL of %s seconds %s',$recordsetKey,$secs2cache,$text),
			sprintf('Failed to save contents of key %s with a TTL of %s seconds %s',$recordsetKey,$secs2cache,$text)
				);
		return true;
	}
	
	/**
	* Writes a logging message for the flushCache method
	*
	* @param string $recordsetKey
    * @param bool $success
	*	
	* @return bool
	*/
	final protected function logflushCacheEvent(
			string $recordsetKey,
			bool $success,
			?string $extraText = '') : bool {
		
		if (!$this->loggingObject)
			return true;

		$this->writeLoggingPair(
			$success,
			sprintf('Entry with key %s flushed from cache %s',$recordsetKey, $extraText),
			sprintf('Failed to remove entry with key %s from cache %s',$recordsetKey, $extraText),$this->loggingObject::NOTICE
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
	* Prints a set of info about the cache
	*
	* @return array
	*/
	public function cacheInfo()
	{
		
		if (!$this->checkConnectionStatus())
			return array();
		
		
		if ($this->debug)
		{
			$message = sprintf('%s: A Request to serve the service info was made',
								strtoupper($this->cacheDefinitions->serviceName)
								);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		
		return $this->cacheLibrary->{$this->cacheInfoFunction}();
	}
	
	/**
	* Writes logging messages based on successful execution or failure
	*
	* @param	string	$success	An evaluation to true/false
	* @param	string	$successMessage	 A message if true
	* @param	string	$failMessage	 A message if false
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
			/*
			* The process executed successfully, only log if debug is on
			*/
			$message = sprintf('%s: %s',
							   strtoupper($this->cacheDefinitions->serviceName),
							   $successMessage);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		else if (!$success && $failMessage)
		{
			/*
			* The process failed to execute successfully
			*/ 
			if (!$failLevel)
				$failLevel = $this->loggingObject::CRITICAL;
			
			$message = sprintf('%s: %s',
							   strtoupper($this->cacheDefinitions->serviceName),
							   $failMessage);
			$this->loggingObject->log($failLevel,$message);
		}
	}
}
