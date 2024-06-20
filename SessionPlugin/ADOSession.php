<?php
/**
* Core session management functionality for the Sessions package
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\SessionPlugin;
use ADOdb\LoggingPlugin;

class ADOSession implements \SessionHandlerInterface{

	/*
	* Does the driver need special largeObject handling
	*/
	protected string $largeObject = 'blob';

	/*
	* Session management has a logging object of its own
	*/
	protected ?object $loggingObject = null;


	protected ?string $sessionHasExpired = null;

	/*
	* The database connection. Must be passed to class
	*/
	protected ?object $connection = null;

	/*
	* The table in use
	*/
	protected ?string $tableName = null;

	protected ?string $selfName  = null;

	/*
	* The maximum number of retries to update the database
	* before we give up
	*/
	protected int $collisionRetries = 10;

	/**
	* Holds the expiration in seconds
	*/
	protected int $lifetime = 0;

	protected string $encryptionKey = 'CRYPTED ADODB SESSIONS ROCK!';

	/*
	* Whether we should optimaize the table (if supported)
	*/
	protected bool $optimizeTable = false;
	/*
	* The SQL statement that optimizes the table
	*/
	protected ?string $optimizeSql = null;

	/*
	* Holds CRC data on the record
	*/
	protected ?string $recordCRC = null;

	/*
	* Filters, such as compression or encryption, applied
	* to database read/write operations
	*/
	protected array $readWriteFilters = array();

	/*
	* Configuration for the session
	*/
	protected ?object $sessionDefinition = null;

	/*
	* Standalone debugging for sessions module
	*/
	protected int $debug = 0;

	/*
	* Defines the crypto method. Default none

	const CRYPTO_NONE 	= 0;
	const CRYPTO_MD5  	= 1;
	const CRYPTO_MCRYPT = 2;
	const CRYPTO_SHA1   = 3;
	const CRYPTO_SECRET = 4;
	*/

	protected $cryptoPluginClasses = array(
		'',
		'MD5Crypt',
		'MCrypt',
		'SHA1Crypt',
		'HordeSecret'
		);

	protected $compressionPluginClasses = array(
		'',
		'BZIP2Compress',
		'GZIPCompress'
		);

	/*
	* Loads a non-default serializarion method. Best value is
	* php_serialize which is better than the older ones because you
	* dont need a custom unserializer. This is the default
	* WDDX needs support added
	*/
	protected $serializarionMethods = array(
		'',
		'php',
		'php_binary',
		'php_serialize',
		'wddx'
		);

	/*
	* The empty value inserted prior to updating a large object
	* null is suitable for most systems
	*/
	protected string $lobValue = 'null';

	public ?object $sessionObject = null;

	public ?string $binary = null;
	/**
	* Constructor, loads session parameters
	*
	* @param object $connection A valid ADOdb database connection
	* @param object $sessionDefinition Defines the session
	*
	*/
	public function __construct(
			?object $connection=null,
			?object $sessionDefinition=null)	{


		if (!$sessionDefinition)
			return;

		$selfName = get_class($this);


		$this->sessionDefinition = $sessionDefinition;

		$this->debug 			 = $sessionDefinition->debug;

		$this->connection 		 = $connection;

		$this->connection->setFetchMode(ADODB_FETCH_NUM);



		if (is_object($sessionDefinition->loggingObject))
			$this->loggingObject = $sessionDefinition->loggingObject;
		else
			$this->loggingObject = new \ADOdb\LoggingPlugin\ADOlogger;

		if (!is_object($connection))
		{
			$message = 'Invalid ADOdb connection passed to session startup';
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return;
		}

		if ($this->debug){
			$message = '=========== SESSION STARTUP ===========';
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);

			if ($this->connection->databaseType == 'pdo')
				$dbDriver = 'PDO/' . $this->connection->dsnType;
			else
				$dbDriver = $this->connection->databaseType;

			$message = 'Database driver is ' . $dbDriver;
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}

		if (!$connection->isConnected())
		{
			$message = sprintf('Invalid Database connection for %s, abandoning', $dbDriver);
			if ($this->debug)
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return;
		}

		$this->tableName = $sessionDefinition->tableName;

		if ($this->debug)
		{
			$message = sprintf('Database table is %s',$this->tableName);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}

		if ($sessionDefinition->serializationMethod !== null)
		{
			$serHandler = (int)$sessionDefinition->serializationMethod;
			if ($serHandler > 0 && $serHandler < 5)
				ini_set('session.serialize_handler',$this->serializarionMethods[$serHandler]);

			if ($this->debug)
			{
				$message = sprintf('Session serialization method set to "%s"',$this->serializarionMethods[$serHandler]);
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
		}

		$this->optimizeTable = $sessionDefinition->optimizeTable;


		/*
		* Load filters from the sessionDefinition
		*/
		if ($sessionDefinition->cryptoMethod > 0)
		{
			/*
			* PHP must have the correct crypto method available
			*/
			if ($sessionDefinition->cryptoMethod)
			{
				$plugin = new \ADOdb\SessionPlugin\plugins\ADOCrypt($connection,$sessionDefinition->cryptoMethod);
				$this->readWriteFilters[] = $plugin;

				if ($plugin->isCryptEnabled() && $this->debug)
				{
					$message = sprintf('Loading crypto plugin %s',$sessionDefinition->cryptoMethod);
					$this->loggingObject->log($this->loggingObject::DEBUG,$message);
				}
				else if (!$plugin->isCryptEnabled())
				{
					$message = sprintf('Crypto plugin %s could not enabled',$sessionDefinition->cryptoMethod);
					$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
				}
			}
		}

		if ($sessionDefinition->compressionMethod > 0)
		{
			/*
			* Compress the data per the scheme requested
			* Note that compression does not work if the session data column isnt
			* a blob fields
			*/
			if (array_key_exists($sessionDefinition->compressionMethod,$this->compressionPluginClasses))
			{
				$plugin 	 = $this->compressionPluginClasses[$sessionDefinition->compressionMethod];
				$compressionClass = '\\ADOdb\\SessionPlugin\\plugins\\' . $plugin;
				$this->readWriteFilters[] = new $compressionClass($connection);

				if ($this->debug)
				{
					$message = sprintf('Loading compression plugin %s',$plugin);
					$this->loggingObject->log($this->loggingObject::DEBUG,$message);
				}
			}
		}

		session_set_save_handler($this);


	}
	/**
	* The entry point for the session management system
	*
	* @param objest a sessionDefinition object
	*
	* @return object
	*/
	final public function startSession(
			object $connection=null,
			?object $sessionDefinition=null) : ?object
	{

		if (!$connection)
			$connection = $this->connection;
		
		if (!$sessionDefinition)
			$sessionDefinition = $this->sessionDefinition;
		/*
		* If no session definition is passed, build a default set
		*/
		if (!$sessionDefinition)
			$sessionDefinition = new \ADOdb\SessionPlugin\ADOSessionDefinitions;

		/*
		* Load the session, against an existing connection.
		*/

		$driver = str_replace('/','\\',$connection->databaseType);
		if ($driver == 'pdo')
			$driver .= '\\' . $connection->dsnType;

		$sessionClass = sprintf('\\ADOdb\\SessionPlugin\\drivers\\%s\\ADOSession',
		$driver);

		$this->sessionObject = new $sessionClass($connection,$sessionDefinition);

		return $this->sessionObject;

	}

	/**
	* Just a stub for the session implementation
	*
	* @param string $savePath
	* @param string $sessionName
	*
	* @return bool always true
	*/
	final public function open($savePath, $sessionName) : bool {

		/*
		* Because we have already opened the database connection
		* just return success
		*/
		return true;
	}

	/**
	* Just a stub for the session implementation
	*
	* @return bool always true
	*/
	final public function close() : bool {

		/*
		* We do no shutdowns just return true;
		*/
		return true;
	}

	/**
	* Manual routine to regenerate the session id
	*
	* @return bool success
	*/
	final public function adodb_session_regenerate_id() : bool {


		$old_id = session_id();
		session_regenerate_id();

		$new_id = session_id();

		$this->connection->param(false);
		$p1 = $this->connection->param('p1');
		$p2 = $this->connection->param('p2');

		$bind = array('p1'=>$new_id,'p2'=>$old_id);

		$sql = sprintf('UPDATE %s SET sesskey=%s WHERE sesskey=%s',
				$this->tableName,$p1,$p2);

		$ok = false;
		$tries = 0;
		while (!$ok && $tries < $this->collisionRetries)
		{
			$ok = $this->connection->execute($sql,$bind);
			$tries++;

		}

		if ($ok && $this->debug)
		{
			$message = sprintf('Regeneration of key %s succeeded, now %s',$old_id,$new_id);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		else if (!$ok)
		{
			$message = sprintf('Regeneration of key %s failed',$old_id);
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
		}

		return $ok ? true : false;
	}


	/**
	* Overrides the previously set lifetime
	*
	* @param int 	$lifetime
	*
	* @return int
	*/
	final public function lifetime(int $lifetime = null) : int
	{

		if (!is_null($lifetime)) {
			$oldLifetime = $this->lifetime;
			$this->lifetime = (int) $lifetime;
			if ($this->debug)
			{
				$message = sprintf('Change lifetime from %s to %s',$oldLifetime,$lifetime);
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
		}

		if (!$this->lifetime) {
			$this->lifetime = ini_get('session.gc_maxlifetime');
			if ($this->lifetime <= 1) {
				$this->lifetime = 1440;
			}

			if ($this->debug)
			{
				$message = sprintf('Set initial lifetime to %s',$this->lifetime);
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
		}

		return $this->lifetime;
	}

	/**
	* Adds a filter (compression/encryption) to the list
	*
	* @param string $filter
	*
	* @return mixed null|string[]
	*/
	protected function filter(?string $filter = null) : ?array {

		if (!is_null($filter)) {
			if (!is_array($filter)) {
				$filter = array($filter);
			}
			$this->readWriteFilters = $filter;
		}

		return $this->readWriteFilters;
	}

	/**
	* Sets or gets the encryption key
	*
	* @param string $encryption key
	*
	* @return mixed
	*
	*/
	public function encryptionKey(?string $encryption_key = null) : string {

		if (!is_null($encryption_key)) {
			$this->encryptionKey = $encryption_key;
		}

		return $this->encryptionKey;
	}

	/** 
	* Creates a new encryption key for crypted sessions
	* crypt the used key, ADODB_Session::encryptionKey() as key and
	* session_id() as salt
	*
	* @return string the new encryption key
	*/
	public function getEncryptedSessionId() : string 
	{
			return crypt($this->encryptionKey(), session_id());
	}

	/**
	 * If set, sets a notification callback for when a session has expired
	 *
	 * @param array|null $expiryNotificationCallback
	 * @return array|null
	 */
	public function setNotificationForExpiryCallback(?array $expiryNotificationCallback = null) : ?array
	{

		if (!is_null($expiryNotificationCallback)) {
			$this->sessionHasExpired = $expiryNotificationCallback;
		}

		return $this->sessionHasExpired;
	}


	/**
	* Slurp in the session variables and return the serialized string
	* cannot type hint impleted class
	*
	* @param string 	$key
	*
	* @return string|false
	*/
	public function read(string $key) : string|false{


		if ($this->debug)
		{
			$message = 'Reading Session Data For key ' . $key;
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}

		$filter	= $this->filter();

		$this->connection->param(false);
		$p0 = $this->connection->param('p0');
		$bind = array('p0'=>$key);

		$sql = sprintf("SELECT %s FROM %s WHERE sesskey = %s AND expiry >= %s",
					$this->sessionDefinition->readFields,
					$this->tableName,
					$this->processSessionKey($p0),
					$this->connection->sysTimeStamp);

		$rs = $this->connection->execute($sql, $bind);

		if ($rs) {
			if ($rs->EOF) {
				if ($this->debug)
				{
					$message = 'No session data found for key ' . $key;
					$this->loggingObject->log($this->loggingObject::DEBUG,$message);
				}
				$v = '';
			} else {
				if ($this->debug)
				{
					$message = 'Unpacking session data for for key ' . $key;
					$this->loggingObject->log($this->loggingObject::DEBUG,$message);
				}
				$v = reset($rs->fields);
				$filter = array_reverse($filter);
				foreach ($filter as $f) {
					if (is_object($f)) {
						$v = $f->read($v, $this->getEncryptedSessionId());
					}
				}
				$v = rawurldecode($v);
			}

			$rs->close();

			$this->recordCRC = strlen($v) . crc32($v);
			return $v;
		}

		if ($this->debug)
		{
			$message = 'No session data found for key ' . $key;
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		
		return '';
	}

	/**
	* Write the serialized data to a database.
	*
	* If the data has not been modified since the last read(), we do not write.
	*/
	public function write($key, $oval): bool
	{

		if ($this->sessionDefinition->readOnly)
			return false;

		$lifetime		= $this->lifetime();

		$sysTimeStamp = $this->connection->sysTimeStamp;

		$expiry = $this->connection->offsetDate($lifetime/(24*3600),$sysTimeStamp);

		$crc	= $this->recordCRC;
		$table  = $this->tableName;
		$binary = $this->binary;

		$expiryNotificationCallback	= $this->setNotificationForExpiryCallback();
		$filter         = $this->filter();

		$clob			= $this->largeObject;
		/*
		* We only update expiry date if there is no change to the session text
		*
		*/
		if ($crc !== '00' && $crc !== false && $crc == (strlen($oval) . crc32($oval)))
		{
			if ($this->debug) {
				$message = 'Only updating date - crc32 not changed';
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}

			$expirevar = '';
			if ($expiryNotificationCallback) {
				$var = reset($expiryNotificationCallback);
				global $$var;
				if (isset($$var)) {
					$expirevar = $$var;
				}
			}
			$this->connection->param(false);
			$p0 = $this->connection->param('p0');
			$p1 = $this->connection->param('p1');

			$bind = array(
				'p0'=>$expirevar,
				'p1'=>$key
			);

			$sql = sprintf("
			UPDATE $table
			SET expiry = $expiry ,expireref=$p0, modified = $sysTimeStamp
					 WHERE sesskey=%s
					 AND expiry >= $sysTimeStamp",
			$this->processSessionKey($p1)
			);

			$rs = $this->connection->execute($sql,$bind);
			return true;
		}

		if ($this->debug)
		{
			$message = 'Rewriting Session Data For key ' . $key;
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		
		$val = rawurlencode($oval);
		
		foreach ($filter as $f) {
			if (is_object($f)) {
				$val = $f->write($val, $this->getEncryptedSessionId());
			}
		}

		$expireref = 0;
		if ($expiryNotificationCallback) 
		{
			$var = reset($expiryNotificationCallback);
			global $$var;
			if (isset($$var)) {
				$expireref = $$var;
			}
		}
		
		if (!$clob) 
		{

			/*
			* no special lob handling for example in MySQL
			*/
			$this->connection->param(false);
			
			$p0 	= $this->connection->param('p0');
			$bind 	= array('p0'=>$key);

			$sql = sprintf("SELECT COUNT(*) AS cnt
					  FROM $table
					 WHERE sesskey = %s",
					 $this->processSessionKey($p0)
					);

			$rs = $this->connection->execute($sql,$bind);
			if ($rs)
				$rs->Close();

			$this->connection->param(false);
			$p0 = $this->connection->param('p0');
			$p1 = $this->connection->param('p1');
			$p2 = $this->connection->param('p2');

			$bind = array('p0'=>$val,
						  'p1'=>$expireref,
						  'p2'=>$key);

			if ($rs && reset($rs->fields) > 0)
			{
				$sql = sprintf("UPDATE $table 
								   SET expiry=$expiry, sessdata=$p0, expireref=$p1,modified=$sysTimeStamp 
								 WHERE sesskey = %s",
								 $this->processSessionKey($p2)
				);

			} else {

				$sql = sprintf("INSERT INTO $table 
							(expiry, sessdata, expireref, sesskey, created, modified) 
							VALUES ($expiry, $p0,$p1,%s, $sysTimeStamp, $sysTimeStamp)",
							$this->processSessionKey($p2)
							);
			}

			$rs = $this->connection->Execute($sql,$bind);
  
		} else {

			/*
			* Special handling of Large Objects required
			*/
			$lob_value = $this->getLobValue($clob);

			$this->connection->startTrans();
			/*
			* Reset
			*/
			$this->connection->param(false);
			$p0 = $this->connection->param('p0');

			$bind = array('p0'=>$key);

			$sql = sprintf("SELECT COUNT(*) AS cnt
					 		  FROM $table
						     WHERE sesskey=%s",
							 $this->processSessionKey($p0)
							);

			$rs = $this->connection->execute($sql,$bind);

			$this->connection->param(false);
			$p0 = $this->connection->param('p0');
			$p1 = $this->connection->param('p1');

			$bind = array('p0'=>$expireref,'p1'=>$key);

			if ($rs && reset($rs->fields) > 0) {
				$sql = sprintf("UPDATE $table 
								   SET expiry=$expiry, sessdata=$lob_value, expireref=$p0,modified=$sysTimeStamp
						         WHERE sesskey = %s",
								 $this->processSessionKey($p1)
								);

			} else {

				$sql = sprintf("INSERT INTO $table 
							    (expiry, sessdata, expireref, sesskey, created, modified)
								VALUES ($expiry, $lob_value, $p0, %s, $sysTimeStamp, $sysTimeStamp)",
								$this->processSessionKey($p1)
								);
			}
			$rs = $this->connection->execute($sql,$bind);

			if ($this->debug)
				$this->loggingObject->log($this->loggingObject::DEBUG,'Calling BLOB update method');

			
			//$qkey = $this->connection->qstr($key);
			$params = array('sesskey'=>$key);
			$rs2 = $this->connection->updateBlob($table, 'sessdata', $val, $params, strtoupper($clob));

			if ($this->debug)
				$this->loggingObject->log($this->loggingObject::DEBUG,'Committing BLOB');

			$this->connection->completeTrans();

		}

		if (!$rs) {
			$message = 'Session Replace: ' . $this->connection->errorMsg();
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;
		}
		return $rs ? true : false;
	}

	/*
	* Session destruction - Part of sessionHandlerInterface
	*
	* @param string $key
	*
	* @return bool
	*/
	final public function destroy($key) : bool
	{

		if ($this->debug)
		{
			$message = 'Destroying Session For key ' . $key;
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}

		$expiryNotificationCallback	= $this->setNotificationForExpiryCallback();

		$qkey = $this->connection->quote($key);
		$table  = $this->tableName;

		if ($expiryNotificationCallback) {
			reset($expiryNotificationCallback);

			$callbackFunction = next($expiryNotificationCallback);

			$this->connection->setFetchMode($this->connection::ADODB_FETCH_NUM);
			$this->connection->param(false);
			$p1 = $this->connection->param('p1');
			$bind = array('p1'=>$key);

			$sql = sprintf("SELECT expireref, sesskey
					  FROM $table
					 WHERE sesskey=%s",
					 $this->processSessionKey($p1)
					);

			$rs = $this->connection->execute($sql,$bind);

			$this->connection->setFetchMode($this->connection->coreFetchMode);
			if (!$rs) {
				return false;
			}
			if (!$rs->EOF) {
				$ref = $rs->fields[0];
				$key = $rs->fields[1];
				$callbackFunction($ref, $key);
			}
			$rs->close();
		}

		$this->connection->param(false);

		$p0 = $this->connection->param('p0');
		$bind = array('p0'=>$key);
		
		$sql = sprintf(
			   "DELETE FROM $table WHERE sesskey=%s",
				 $this->processSessionKey($p0)
				);

		$rs = $this->connection->execute($sql,$bind);
		if ($rs) {
			$rs->close();
			if ($this->debug){
				$message = 'SESSION: Successfully destroyed and cleaned up';
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
		}

		return $rs ? true : false;
	}

	/**
	* Garbage Collection - Part of sessionHandlerInterface
	* @param int $maxlifetime
	* @return bool 
	*/
	function gc(int $maxlifetime) : bool
	{

		$expiryNotificationCallback	= $this->setNotificationForExpiryCallback();
		$optimize		= $this->optimizeTable;

		if ($this->debug) {
			$COMMITNUM = 2;
		} else {
			$COMMITNUM = 20;
		}

		$sysTimeStamp = $this->connection->sysTimeStamp;

		$time = $this->connection->offsetDate(-$maxlifetime/24/3600,$sysTimeStamp);

		$table = $this->tableName;

		if ($expiryNotificationCallback) {
			reset($expiryNotificationCallback);
			$callbackFunction = next($expiryNotificationCallback);
		} else {
			$callbackFunction = false;
		}

		$this->connection->SetFetchMode($this->connection::ADODB_FETCH_NUM);
		$sql = "SELECT expireref, sesskey
			      FROM $table
				 WHERE expiry < $time
	               ORDER BY 2"; # add order by to prevent deadlock
		$rs = $this->connection->selectLimit($sql,1000);

		$this->connection->setFetchMode($this->connection->coreFetchMode);

		if ($rs) {
			$this->connection->beginTrans();

			$keys = array();
			$ccnt = 0;

			while (!$rs->EOF) {

				$ref = $rs->fields[0];
				$key = $rs->fields[1];
				if ($callbackFunction)
					$callbackFunction($ref, $key);

				$this->connection->param(false);
				$p0 = $this->connection->param('p0');
				$bind = array($p0=>$key);

				$sql = sprintf("DELETE FROM $table WHERE sesskey=%s",
						 		$this->processSessionKey($p0)
							  );

				$del = $this->connection->execute($sql,$bind);

				$rs->MoveNext();
				$ccnt += 1;

				if ($ccnt % $COMMITNUM == 0) {
					if ($this->debug) {
						$message = 'Garbage Collecton complete';
						$this->loggingObject->log($this->loggingObject::DEBUG,$message);
					}
					$this->connection->commitTrans();
					$this->connection->beginTrans();
				}
			}
			$rs->close();

			$this->connection->commitTrans();
		}

		if ($optimize)
		{
			/*
			* Only MySQL/Postgres Support optimization
			*/
			$sql = $this->getOptimizationSql();

			if ($sql) {
				if ($this->debug)
				{
					$message = 'Optimizing Session Table';
					$this->loggingObject->log($this->loggingObject::DEBUG,$message);
				}
				$this->connection->execute($sql);
			}
		}


		return true;
	}

	/*
	* Returns the db specific optimization sql
	*
	* @return ?string
	*/
	protected function getOptimizationSql(): ?string {
		return null;
	}

	/**
	* Returns the specially defined value for the empty LOB
	*
	* @param string $param1
	*
	* @return string
	*/
	protected function getLobValue(?string $param1=null) : string {

		return $this->lobValue;

	}

	/**
	 * Returns a required preprocessed session key value for the given value.
	 *
	 * @param string $value
	 * @return string
	 */
	protected function processSessionKey(string $value): string
	{
		return $value;
	}
}
