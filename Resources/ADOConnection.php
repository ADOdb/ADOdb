<?php

namespace ADOdb\Resources;

use ADOdb\Resources\ADOFieldObject;
/**
 * Connection object. For connecting to databases, and executing queries.
 */
abstract class ADOConnection {
	//
	// PUBLIC VARS
	//
	var $dataProvider = 'native';
	var $databaseType = '';		/// RDBMS currently in use, eg. odbc, mysql, mssql

	/**
	 * @var string Current database name.
	 *
	 * This used to be stored in the $databaseName property, which was marked
	 * as deprecated in 4.66 and removed in 5.22.5.
	 */
	public $database = '';

	/**
	 * @var string If the driver is PDO, then the dsnType is e.g. sqlsrv, otherwise empty
	 */
	public $dsnType = '';

	var $host = '';				/// The hostname of the database server
	var $port = '';				/// The port of the database server
	var $user = '';				/// The username which is used to connect to the database server.
	var $password = '';			/// Password for the username. For security, we no longer store it.

	/**
	 * Debug Mode.
	 *
	 * Enables printing of SQL queries execution and additional debugging
	 * information. Can be enabled/disabled at any time after the database
	 * Connection has been initialized {@see ADONewConnection()}.
	 *
	 * Possible values are:
	 * - False: Disabled
	 * - True:  Standard mode, prints executed SQL statements and error
	 *          information including a Backtrace if the query failed.
	 * - -1:    Same as standard mode, but without line separators.
	 * - 99:    Prints a Backtrace after every query execution, even if
	 *          it was successful.
	 * - -99:   Debug information is only printed if query execution failed.
	 *
	 * @see https://adodb.org/dokuwiki/doku.php?id=v5:userguide:debug
	 *
	 * @var bool|int
	 */
	public $debug = false;

	/**
	 * A placeholder for a metafunctions object, created at
	 * first use of class
	 *
	 * @var object|null
	 */
	public ?object $metaObject = null;

	/**
	 * A placeholder for a data dictionary object, created at
	 * first use of newDataDictionary and available as needed.
	 *
	 * @var object|null
	 */
	public ?object $dictionaryObject = null;

	var $maxblobsize = 262144;	/// maximum size of blobs or large text fields (262144 = 256K)-- some db's die otherwise like foxpro
	var $concat_operator = '+'; /// default concat operator -- change to || for Oracle/Interbase
	var $substr = 'substr';		/// substring operator
	var $length = 'length';		/// string length ofperator
	var $random = 'rand()';		/// random function
	var $upperCase = 'upper';		/// uppercase function
	var $fmtDate = "'Y-m-d'";	/// used by DBDate() as the default date format used by the database
	var $fmtTimeStamp = "'Y-m-d, h:i:s A'"; /// used by DBTimeStamp as the default timestamp fmt.
	var $true = '1';			/// string that represents TRUE for a database
	var $false = '0';			/// string that represents FALSE for a database
	var $replaceQuote = "\\'";	/// string to use to replace quotes
	var $nameQuote = '"';		/// string to use to quote identifiers and names
	var $leftBracket = '[';		/// left square bracked for t-sql styled column names
	var $rightBracket = ']';	/// right square bracked for t-sql styled column names
	var $charSet=false;			/// character set to use - only for interbase, postgres and oci8



	/**
	 * SQL statement to get the last IDENTITY value inserted into an IDENTITY
	 * column in the same scope.
	 * @see https://learn.microsoft.com/en-us/sql/t-sql/functions/scope-identity-transact-sql
	 * @var string
	 */
	var $identitySQL;

	/** @var string SQL statement to create a Sequence . */
	var $_genSeqSQL;

	/** @var string SQL statement to drop a Sequence. */
	var $_dropSeqSQL;

	/** @var string SQL statement to generate a Sequence ID. */
	var $_genIDSQL;

	var $uniqueOrderBy = false; /// All order by columns have to be unique
	var $emptyDate = '&nbsp;';
	var $emptyTimeStamp = '&nbsp;';
	var $lastInsID = false;
	//--
	var $hasInsertID = false;		/// supports autoincrement ID?
	var $hasAffectedRows = false;	/// supports affected rows for update/delete?
	var $hasTop = false;			/// support mssql/access SELECT TOP 10 * FROM TABLE
	var $hasLimit = false;			/// support pgsql/mysql SELECT * FROM TABLE LIMIT 10
	var $readOnly = false;			/// this is a readonly database - used by phpLens
	var $hasMoveFirst = false;		/// has ability to run MoveFirst(), scrolling backwards
	var $hasGenID = false;			/// can generate sequences using GenID();
	var $hasTransactions = true;	/// has transactions
	//--
	var $genID = 0;					/// sequence id used by GenID();

	/** @var bool|callable Error function to call */
	var $raiseErrorFn = false;

	var $isoDates = false;			/// accepts dates in ISO format
	var $cacheSecs = 3600;			/// cache for 1 hour

	/*****************************************
	* memcached server options
	******************************************/

	/**
	 * Use memCache library instead of caching in files.
	 * @var bool $memCache
	 */
	public $memCache = false;

	/**
	 * The memcache server(s) to connect to. Can be defined as:
	 * - a single host name/ip address
	 * - a list of hosts/ip addresses
	 * - an array of server connection data (weighted server groups).
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:userguide:memcached
	 * @var string|array $memCacheHost
	 */
	public $memCacheHost;

	/**
	 * Default port number.
	 * The default port can be overridden if memcache server connection data
	 * is provided as an array {@see $memCacheHost}.
	 * @var int $memCachePort
	 */
	public $memCachePort = 11211;

	/**
	 * Enable compression of stored items.
	 * @var bool $memCacheCompress
	 */
	public $memCacheCompress = false;

	/**
	 * An array of memcached options.
	 * Only used with memcached; memcache ignores this setting.
	 * @link https://www.php.net/manual/en/memcached.constants.php
	 * @var array $memCacheOptions
	 */
	public $memCacheOptions = array();

	var $sysDate = false; /// name of function that returns the current date
	var $sysTimeStamp = false; /// name of function that returns the current timestamp
	var $sysUTimeStamp = false; // name of function that returns the current timestamp accurate to the microsecond or nearest fraction
	var $arrayClass = 'ADORecordSet_array'; /// name of class used to generate array recordsets, which are pre-downloaded recordsets

	var $noNullStrings = false; /// oracle specific stuff - if true ensures that '' is converted to ' '
	var $numCacheHits = 0;
	var $numCacheMisses = 0;
	var $pageExecuteCountRows = true;
	var $uniqueSort = false; /// indicates that all fields in order by must be unique
	var $leftOuter = false; /// operator to use for left outer join in WHERE clause
	var $rightOuter = false; /// operator to use for right outer join in WHERE clause
	var $ansiOuter = false; /// whether ansi outer join syntax supported
	var $autoRollback = false; // autoRollback on PConnect().
	var $poorAffectedRows = false; // affectedRows not working or unreliable

	/** @var bool|callable Execute function to call */
	var $fnExecute = false;

	/** @var bool|callable Cache execution function to call */
	var $fnCacheExecute = false;

	var $blobEncodeType = false; // false=not required, 'I'=encode to integer, 'C'=encode to char
	var $rsPrefix = "ADORecordSet_";

	var $autoCommit = true;		/// do not modify this yourself - actually private
	var $transOff = 0;			/// temporarily disable transactions
	var $transCnt = 0;			/// count of nested transactions

	var $fetchMode=false;

	var $null2null = 'null'; // in autoexecute/getinsertsql/getupdatesql, this value will be converted to a null
	var $bulkBind = false; // enable 2D Execute array

	/** @var string SQL statement executed by some drivers after successful connection. */
	public $connectStmt = '';

	//
	// PRIVATE VARS
	//
	var $_oldRaiseFn =  false;
	var $_transOK = null;
	/** @var resource Identifier for the native database connection */
	var $_connectionID = false;

	/**
	 * Stores the last returned error message.
	 * @see ADOConnection::errorMsg()
	 * @var string|false
	 */
	var $_errorMsg = false;

	/**
	 * Stores the last returned error code.
	 * Not guaranteed to be used. Only some drivers actually populate it.
	 * @var int|false
	 */
	var $_errorCode = false;

	var $_queryID = false;		/// This variable keeps the last created result link identifier

	var $_isPersistentConnection = false;	/// A boolean variable to state whether its a persistent connection or normal connection.	*/
	var $_bindInputArray = false; /// set to true if ADOConnection.Execute() permits binding of array parameters.

	/**
	 * Eval string used to filter data.
	 * Only used in the deprecated Text driver.
	 * @see https://adodb.org/dokuwiki/doku.php?id=v5:database:text#workaround
	 * @var string
	 */
	var $evalAll = false;

	var $_affected = false;
	var $_logsql = false;
	var $_transmode = ''; // transaction mode

	/**
	 * Additional parameters that may be passed to drivers in the connect string.
	 *
	 * Data is stored as an array of arrays and not a simple associative array,
	 * because some drivers (e.g. mysql) allow multiple parameters with the same
	 * key to be set.
	 * @link https://github.com/ADOdb/ADOdb/issues/187
	 *
	 * @see setConnectionParameter()
	 *
	 * @var array $connectionParameters Set of ParameterName => Value pairs
	 */
	protected $connectionParameters = array();

	/*
	 * A simple associative array of user-defined custom actual/meta types
	 */
	public $customActualTypes = array();

	/*
	 * An array of user-defined custom meta/actual types.
	 * $this->customMetaTypes[$meta] = array(
	 *     'actual'=>'',
	 *     'dictionary'=>'',
	 *     'handler'=>'',
	 *     'callback'=>''
	 * );
	 */
	public $customMetaTypes = array();

	/** @var ADORecordSet Recordset used to retrieve MetaType information */
	var $_metars;

	/** @var string a specified locale. */
	var $locale;

	/**
	 * Setting true forces {@see metaColumns()} to read the db for 
	 * each access of a table instead of using cached version. 
	 * Currently only works on mssqlnative
	 * 
	 * @var bool
	 */
	public bool $cachedSchemaFlush = false;

	/**
	 * The class name that provides meta functions
	 * to the the driver. PDO drivers use the same
	 * providers as the native connection for the
	 * same database
	 *
	 * @var string
	 */
	protected string $metaFunctionProvider = '';

	/**
	 * The class name that provides dictionary functions
	 * to the the driver. PDO drivers use the same
	 * providers as the native connection for the
	 * same database
	 *
	 * @var string
	 */
	protected string $dataDictionaryProvider = '';
	

    /**
	 * Instantiate a new Connection class for a specific database driver.
	 *
	 * @param string $db Database Connection object to create. If undefined,
	 *	use the last database driver that was loaded by ADOLoadCode().
	 *
	 * @return ADOConnection|false The freshly created instance of the Connection class
	 *                             or false in case of error.
	 */
	public function __construct(string $db='') {

		global $ADODB_NEWCONNECTION, $ADODB_LASTDB;

		if (!defined('ADODB_ASSOC_CASE')) {
			define('ADODB_ASSOC_CASE', ADODB_ASSOC_CASE_NATIVE);
		}

		/*
		* Are there special characters in the dsn password
		* that disrupt parse_url
		*/
		$needsSpecialCharacterHandling = false;

		$errorfn = (defined('ADODB_ERROR_HANDLER')) ? ADODB_ERROR_HANDLER : false;
		if (($at = strpos($db,'://')) !== FALSE) {
			$origdsn = $db;
			$fakedsn = 'fake'.substr($origdsn,$at);
			if (($at2 = strpos($origdsn,'@/')) !== FALSE) {
				// special handling of oracle, which might not have host
				$fakedsn = str_replace('@/','@adodb-fakehost/',$fakedsn);
			}

			if ((strpos($origdsn, 'sqlite')) !== FALSE && stripos($origdsn, '%2F') === FALSE) {
				// special handling for SQLite, it only might have the path to the database file.
				// If you try to connect to a SQLite database using a dsn
				// like 'sqlite:///path/to/database', the 'parse_url' php function
				// will throw you an exception with a message such as "unable to parse url"
				list($scheme, $path) = explode('://', $origdsn);
				$dsna['scheme'] = $scheme;
				if ($qmark = strpos($path,'?')) {
					$dsn['query'] = substr($path,$qmark+1);
					$path = substr($path,0,$qmark);
				}
				$dsna['path'] = '/' . urlencode($path);
			} else {
				/*
				* Stop # character breaking parse_url
				*/
				$cFakedsn = str_replace('#','\035',$fakedsn);
				if (strcmp($fakedsn,$cFakedsn) != 0)
				{
					/*
					* There is a # in the string
					*/
					$needsSpecialCharacterHandling = true;

					/*
					* This allows us to successfully parse the url
					*/
					$fakedsn = $cFakedsn;

				}

				$dsna = parse_url($fakedsn);
			}

			if (!$dsna) {
				return false;
			}
			$dsna['scheme'] = substr($origdsn,0,$at);
			if ($at2 !== FALSE) {
				$dsna['host'] = '';
			}

			if (strncmp($origdsn,'pdo',3) == 0) {
				$sch = explode('_',$dsna['scheme']);
				if (sizeof($sch)>1) {
					$dsna['host'] = isset($dsna['host']) ? rawurldecode($dsna['host']) : '';
					if ($sch[1] == 'sqlite') {
						$dsna['host'] = rawurlencode($sch[1].':'.rawurldecode($dsna['host']));
					} else {
						$dsna['host'] = rawurlencode($sch[1].':host='.rawurldecode($dsna['host']));
					}
					$dsna['scheme'] = 'pdo';
				}
			}

			$db = @$dsna['scheme'];
			if (!$db) {
				return false;
			}

			$dsna['host'] = isset($dsna['host']) ? rawurldecode($dsna['host']) : '';
			$dsna['user'] = isset($dsna['user']) ? rawurldecode($dsna['user']) : '';
			$dsna['pass'] = isset($dsna['pass']) ? rawurldecode($dsna['pass']) : '';
			$dsna['path'] = isset($dsna['path']) ? rawurldecode(substr($dsna['path'],1)) : ''; # strip off initial /

			if ($needsSpecialCharacterHandling)
			{
				/*
				* Revert back to the original string
				*/
				$dsna = str_replace('\035','#',$dsna);
			}

			if (isset($dsna['query'])) {
				$opt1 = explode('&',$dsna['query']);
				foreach($opt1 as $k => $v) {
					$arr = explode('=',$v);
					$opt[$arr[0]] = isset($arr[1]) ? rawurldecode($arr[1]) : 1;
				}
			} else {
				$opt = array();
			}

		}

		$obj = $this;
		# constructor should not fail
		if ($obj) {
			if ($errorfn) {
				$obj->raiseErrorFn = $errorfn;
			}
			if (isset($dsna)) {
				if (isset($dsna['port'])) {
					$obj->port = $dsna['port'];
				}
				foreach($opt as $k => $v) {
					switch(strtolower($k)) {
					case 'new':
										$nconnect = true; $persist = true; break;
					case 'persist':
					case 'persistent':	$persist = $v; break;
					case 'debug':		$obj->debug = (int) $v; break;
					#ibase
					case 'role':		$obj->role = $v; break;
					case 'dialect':	$obj->dialect = (int) $v; break;
					case 'charset':		$obj->charset = $v; $obj->charSet=$v; break;
					case 'buffers':		$obj->buffers = $v; break;
					case 'fetchmode':   $obj->SetFetchMode($v); break;
					#ado
					case 'charpage':	$obj->charPage = $v; break;
					#mysql, mysqli
					case 'clientflags': $obj->clientFlags = $v; break;
					#mysql, mysqli, postgres
					case 'port': $obj->port = $v; break;
					#mysqli
					case 'socket': $obj->socket = $v; break;
					#oci8
					case 'nls_date_format': $obj->NLS_DATE_FORMAT = $v; break;
					case 'cachesecs': $obj->cacheSecs = $v; break;
					case 'memcache':
						$varr = explode(':',$v);
						$vlen = sizeof($varr);
						if ($vlen == 0) {
							break;
						}
						$obj->memCache = true;
						$obj->memCacheHost = explode(',',$varr[0]);
						if ($vlen == 1) {
							break;
						}
						$obj->memCachePort = $varr[1];
						if ($vlen == 2) {
							break;
						}
						$obj->memCacheCompress = $varr[2] ?  true : false;
						break;
					}
				}

				if (empty($persist)) {
					$ok = $obj->Connect($dsna['host'], $dsna['user'], $dsna['pass'], $dsna['path']);
				} else if (empty($nconnect)) {
					$ok = $obj->PConnect($dsna['host'], $dsna['user'], $dsna['pass'], $dsna['path']);
				} else {
					$ok = $obj->NConnect($dsna['host'], $dsna['user'], $dsna['pass'], $dsna['path']);
				}

				if (!$ok) {
					return false;
				}
			}
		}

		return $obj;
	}

	/**
	 * Default Constructor.
	 * We define it even though it does not actually do anything. This avoids
	 * getting a PHP Fatal error:  Cannot call constructor if a subclass tries
	 * to call its parent constructor.
	 */


	public function getDataDictionaryProvider() : string
	{
		return $this->dataDictionaryProvider;
	}

	/**
	 * Adds a parameter to the connection string.
	 *
	 * Parameters must be added before the connection is established;
	 * they are then passed on to the connect statement, which will.
	 * process them if the driver supports this feature.
	 *
	 * Example usage:
	 * - mssqlnative: setConnectionParameter('CharacterSet','UTF-8');
	 * - mysqli: setConnectionParameter(MYSQLI_SET_CHARSET_NAME,'utf8mb4');
	 *
	 * If used in a portable environment, parameters set in this manner should
	 * be predicated on the database provider, as unexpected results may occur
	 * if applied to the wrong database.
	 *
	 * @param string $parameter The name of the parameter to set
	 * @param string $value     The value of the parameter
	 *
	 * @return bool True if success, false otherwise (e.g. parameter is not valid)
	 */
	public function setConnectionParameter($parameter, $value) {
		$this->connectionParameters[] = array($parameter=>$value);
		return true;
	}

	/**
	 * ADOdb version.
	 *
	 * @return string
	 */
	static function Version() {
		global $ADODB_vers;

		// Semantic Version number matching regex
		$regex = '^[vV]?(\d+\.\d+\.\d+'         // Version number (X.Y.Z) with optional 'V'
			. '(?:-(?:'                         // Optional preprod version: a '-'
			. 'dev|'                            // followed by 'dev'
			. '(?:(?:alpha|beta|rc)(?:\.\d+))'  // or a preprod suffix and version number
			. '))?)(?:\s|$)';                   // Whitespace or end of string

		if (!preg_match("/$regex/", $ADODB_vers, $matches)) {
			// This should normally not happen... Return whatever is between the start
			// of the string and the first whitespace (or the end of the string).
			self::outp("Invalid version number: '$ADODB_vers'", 'Version');
			$regex = '^[vV]?(.*?)(?:\s|$)';
			preg_match("/$regex/", $ADODB_vers, $matches);
		}
		return $matches[1];
	}

	/**
	 * Set a custom meta type with a corresponding actual
	 *
	 * @param	string	$metaType	The Custom ADOdb metatype
	 * @param	string	$dictionaryType	The database dictionary type
	 * @param	string	$actualType	The database actual type
	 * @param	bool	$handleAsType handle like an existing Metatype
	 * @param	mixed	$callBack A pre-processing function
	 *
	 * @return bool success if the actual exists
	 */
	final public function setCustomMetaType(
		$metaType,
		$dictionaryType,
		$actualType,
		$handleAsType=false,
		$callback=false){

		$this->customMetaTypes[strtoupper($metaType)] = array(
			'actual'=>$actualType,
			'dictionary'=>strtoupper($dictionaryType),
			'handler'=>$handleAsType,
			'callback'=>$callback
			);

		/*
		* Create a reverse lookup for the actualType
		*/
		$this->customActualTypes[$actualType] = $metaType;

		return true;
	}

	/**
	 * Get a list of custom meta types.
	 *
	 * @return string[]
	 */
	final public function getCustomMetaTypes()
	{
		return $this->customMetaTypes;
	}


	/**
	 * Get server version info.
	 *
	 * @return string[] Array with 2 string elements: version and description
	 */
	function ServerInfo() {
		return array('description' => '', 'version' => '');
	}

	/**
	 * Return true if connected to the database.
	 *
	 * @return bool
	 */
	function IsConnected() {
		return !empty($this->_connectionID);
	}

	/**
	 * Find version string.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	function _findvers($str) {
		if (preg_match('/([0-9]+\.([0-9\.])+)/',$str, $arr)) {
			return $arr[1];
		} else {
			return '';
		}
	}

	/**
	 * All error messages go through this bottleneck function.
	 *
	 * You can define your own handler by defining the function name in ADODB_OUTP.
	 *
	 * @param string $msg     Message to print
	 * @param bool   $newline True to add a newline after printing $msg
	 */
	static function outp($msg,$newline=true) {
		global $ADODB_FLUSH,$ADODB_OUTP;

		if (defined('ADODB_OUTP')) {
			$fn = ADODB_OUTP;
			$fn($msg,$newline);
			return;
		} else if (isset($ADODB_OUTP)) {
			call_user_func($ADODB_OUTP,$msg,$newline);
			return;
		}

		if (isset($_SERVER['HTTP_USER_AGENT'])) {
			echo $msg . ($newline ? '<br>' :'');
		} else {
			echo strip_tags($msg) . ($newline ? PHP_EOL : '');
		}

		if (!empty($ADODB_FLUSH) && ob_get_length() !== false) {
			flush(); //  do not flush if output buffering enabled - useless - thx to Jesse Mullan
		}
	}

	/**
	 * Return the database server's current date and time.
	 * @return int|false
	 */
	function Time() {
		$rs = $this->_Execute("select $this->sysTimeStamp");
		if ($rs && !$rs->EOF) {
			return $this->UnixTimeStamp(reset($rs->fields));
		}

		return false;
	}

	/**
	 * Parses the hostname to extract the port.
	 * Overwrites $this->host and $this->port, only if a port is specified.
	 * The Hostname can be fully or partially qualified,
	 * ie: "db.mydomain.com:5432" or "ldaps://ldap.mydomain.com:636"
	 * Any specified scheme such as ldap:// or ldaps:// is maintained.
	 */
	protected function parseHostNameAndPort() {
		$parsed_url = parse_url($this->host);
		if (is_array($parsed_url) && isset($parsed_url['host']) && isset($parsed_url['port'])) {
			if ( isset($parsed_url['scheme']) ) {
				// If scheme is specified (ie: ldap:// or ldaps://, make sure we retain that.
				$this->host = $parsed_url['scheme'] . "://" . $parsed_url['host'];
			} else {
				$this->host = $parsed_url['host'];
			}
			$this->port = $parsed_url['port'];
		}
	}

	/**
	 * Low-level, driver-specific method to connect to the database.
	 *
	 * @param string $argHostname     Host to connect to
	 * @param string $argUsername     Userid to login
	 * @param string $argPassword     Associated password
	 * @param string $argDatabaseName Database name
	 *
	 * @return bool
	 * @internal
	 * @TODO propagate *protected* visibility to child classes
	 */
	abstract protected function _connect($argHostname, $argUsername, $argPassword, $argDatabaseName);

	/**
	 * Connect to database.
	 *
	 * @param string $argHostname     Host to connect to
	 * @param string $argUsername     Userid to login
	 * @param string $argPassword     Associated password
	 * @param string $argDatabaseName Database name
	 * @param bool   $forceNew        Force new connection
	 *
	 * @return bool
	 */
	function Connect($argHostname = "", $argUsername = "", $argPassword = "", $argDatabaseName = "", $forceNew = false) {
		if ($argHostname != "") {
			$this->host = $argHostname;
		}
		// Overwrites $this->host and $this->port if a port is specified.
		$this->parseHostNameAndPort();

		if ($argUsername != "") {
			$this->user = $argUsername;
		}
		if ($argPassword != "") {
			$this->password = 'not stored'; // not stored for security reasons
		}
		if ($argDatabaseName != "") {
			$this->database = $argDatabaseName;
		}

		$this->_isPersistentConnection = false;

		$metaClassFile = sprintf(
			'%s/Resources/%s/MetaFunctions.php',
			ADODB_DIR,
			$this->metaFunctionProvider
		);

		$metaClass = sprintf(
			'ADOdb\Resources\%s\MetaFunctions',
			$this->metaFunctionProvider
		);

		if ($forceNew) {
			if ($rez=$this->_nconnect($this->host, $this->user, $argPassword, $this->database)) {
				
				require_once $metaClassFile;
				$this->metaObject = new $metaClass;

				return true;
			}
		} else {
			if ($rez=$this->_connect($this->host, $this->user, $argPassword, $this->database)) {
				
				require_once $metaClassFile;
				$this->metaObject = new $metaClass;

				return true;
			}
		}
		if (isset($rez)) {
			$err = $this->ErrorMsg();
			$errno = $this->ErrorNo();
			if (empty($err)) {
				$err = "Connection error to server '$argHostname' with user '$argUsername'";
			}
		} else {
			$err = "Missing extension for ".$this->dataProvider;
			$errno = 0;
		}
		if ($fn = $this->raiseErrorFn) {
			$fn($this->databaseType, 'CONNECT', $errno, $err, $this->host, $this->database, $this);
		}

		$this->_connectionID = false;
		if ($this->debug) {
			ADOConnection::outp( $this->host.': '.$err);
		}
		return false;
	}

	/**
	 * Low-level method to force a new connection to the database.
	 *
	 * Unless the child Driver class overrides it, this method is the same as
	 * {@see _connect()}.
	 *
	 * @param string $argHostname     Host to connect to
	 * @param string $argUsername     Userid to login
	 * @param string $argPassword     Associated password
	 * @param string $argDatabaseName Database name
	 *
	 * @return bool
	 * @internal
	 * @TODO propagate *protected* visibility to child classes
	 */
	protected function _nconnect($argHostname, $argUsername, $argPassword, $argDatabaseName) {
		return $this->_connect($argHostname, $argUsername, $argPassword, $argDatabaseName);
	}

	/**
	 * Always force a new connection to the database.
	 *
	 * This is only supported by some drivers.
	 *
	 * @param string $argHostname     Host to connect to
	 * @param string $argUsername     Userid to login
	 * @param string $argPassword     Associated password
	 * @param string $argDatabaseName Database name
	 *
	 * @return bool
	 */
	function NConnect($argHostname = "", $argUsername = "", $argPassword = "", $argDatabaseName = "") {
		return $this->Connect($argHostname, $argUsername, $argPassword, $argDatabaseName, true);
	}

	/**
	 * Low-level method to establish a persistent connection to the database.
	 *
	 * Unless the child Driver class overrides it, this method is the same as
	 * {@see _connect()}.
	 *
	 * @param string $argHostname     Host to connect to
	 * @param string $argUsername     Userid to login
	 * @param string $argPassword     Associated password
	 * @param string $argDatabaseName Database name
	 *
	 * @return bool
	 * @internal
	 * @TODO propagate *protected* visibility to child classes
	 */
	protected function _pconnect($argHostname, $argUsername, $argPassword, $argDatabaseName) {
		return $this->_connect($argHostname, $argUsername, $argPassword, $argDatabaseName);
	}

	/**
	 * Establish persistent connection to database.
	 *
	 * @param string $argHostname     Host to connect to
	 * @param string $argUsername     Userid to login
	 * @param string $argPassword     Associated password
	 * @param string $argDatabaseName Database name
	 *
	 * @return bool
	 */
	function PConnect($argHostname = "", $argUsername = "", $argPassword = "", $argDatabaseName = "") {

		if (defined('ADODB_NEVER_PERSIST')) {
			return $this->Connect($argHostname,$argUsername,$argPassword,$argDatabaseName);
		}

		if ($argHostname != "") {
			$this->host = $argHostname;
		}
		// Overwrites $this->host and $this->port if a port is specified.
		$this->parseHostNameAndPort();

		if ($argUsername != "") {
			$this->user = $argUsername;
		}
		if ($argPassword != "") {
			$this->password = 'not stored';
		}
		if ($argDatabaseName != "") {
			$this->database = $argDatabaseName;
		}

		$this->_isPersistentConnection = true;

		if ($rez = $this->_pconnect($this->host, $this->user, $argPassword, $this->database)) {
			return true;
		}
		if (isset($rez)) {
			$err = $this->ErrorMsg();
			if (empty($err)) {
				$err = "Connection error to server '$argHostname' with user '$argUsername'";
			}
			$ret = false;
		} else {
			$err = "Missing extension for ".$this->dataProvider;
			$ret = false;
		}
		if ($fn = $this->raiseErrorFn) {
			$fn($this->databaseType,'PCONNECT',$this->ErrorNo(),$err,$this->host,$this->database,$this);
		}

		$this->_connectionID = false;
		if ($this->debug) {
			ADOConnection::outp( $this->host.': '.$err);
		}
		return $ret;
	}

	/**
	 * Throw an exception if the handler is defined or prints the message if not.
	 * @param string $msg Message
	 * @param string $src the name of the calling function (in uppercase)
	 * @param string $sql Optional offending SQL statement
	 */
	function outp_throw($msg, $src='WARN', $sql='') {
		if (defined('ADODB_ERROR_HANDLER') &&  ADODB_ERROR_HANDLER == 'adodb_throw') {
			adodb_throw($this->databaseType,$src,-9999,$msg,$sql,false,$this);
			return;
		}
		ADOConnection::outp($msg);
	}

	/**
	 * Create cache class.
	 *
	 * Code is backwards-compatible with old memcache implementation.
	 */
	function _CreateCache() {
		global $ADODB_CACHE, $ADODB_CACHE_CLASS;

		if ($this->memCache) {
			global $ADODB_INCLUDED_MEMCACHE;

			if (empty($ADODB_INCLUDED_MEMCACHE)) {
				include_once(ADODB_DIR.'/adodb-memcache.lib.inc.php');
			}
			$ADODB_CACHE = new ADODB_Cache_MemCache($this);
		} else {
			$ADODB_CACHE = new $ADODB_CACHE_CLASS($this);
		}
	}

	/**
	 * Format date column in sql string.
	 *
	 * See https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:sqldate
	 * for documentation on supported formats.
	 *
	 * @param string $fmt Format string
	 * @param string $col Date column; use system date if not specified.
	 *
	 * @return string
	 */
	function SQLDate($fmt, $col = '') {
		if (!$col) {
			$col = $this->sysDate;
		}
		return $col; // child class implement
	}

	/**
	 * Prepare an SQL statement and return the statement resource.
	 *
	 * For databases that do not support prepared statements, we return the
	 * provided SQL statement as-is, to ensure compatibility:
	 *
	 *   $stmt = $db->prepare("insert into table (id, name) values (?,?)");
	 *   $db->execute($stmt, array(1,'Jill')) or die('insert failed');
	 *   $db->execute($stmt, array(2,'Joe')) or die('insert failed');
	 *
	 * @param string $sql SQL to send to database
	 *
	 * @return mixed|false The prepared statement, or the original sql if the
	 *                     database does not support prepare.
	 */
	function Prepare($sql) {
		return $sql;
	}

	/**
	 * Releases a previously prepared statement.
	 *
	 * @param mixed $stmt Statement resource, as returned by {@see prepare()}
	 *
	 * @return bool
	 */
	function releaseStatement(&$stmt) {
		return true;
	}

	/**
	 * Prepare a Stored Procedure and return the statement resource.
	 *
	 * Some databases, eg. mssql require a different function for preparing
	 * stored procedures. So we cannot use Prepare().
	 *
	 * For databases that do not support this, we return the $sql.
	 *
	 * @param string $sql   SQL to send to database
	 * @param bool   $param
	 *
	 * @return mixed|false The prepared statement, or the original sql if the
	 *                     database does not support prepare.
	 */
	function PrepareSP($sql,$param=true) {
		return $this->Prepare($sql,$param);
	}

	/**
	 * PEAR DB Compat - alias for qStr.
	 * @param $s
	 * @return string
	 */
	function Quote($s) {
		return $this->qstr($s);
	}

	/**
	 * Quotes a string so that all strings are escaped.
	 * Wrapper for qstr with magic_quotes = false.
	 *
	 * @param string &$s
	 */
	function q(&$s) {
		//if (!empty($this->qNull && $s == 'null') {
		//	return $s;
		//}
		$s = $this->qstr($s);
	}

	/**
	 * PEAR DB Compat - do not use internally.
	 * @return int
	 */
	function ErrorNative() {
		return $this->ErrorNo();
	}


	/**
	 * PEAR DB Compat - do not use internally.
	 * @param string $seq_name
	 * @return int
	 */
	function nextId($seq_name) {
		return $this->GenID($seq_name);
	}

	/**
	 * Lock a row.
	 * Will escalate and lock the table if row locking is not supported.
	 * Will normally free the lock at the end of the transaction.
	 *
	 * @param string $table name of table to lock
	 * @param string $where where clause to use, eg: "WHERE row=12". If left empty, will escalate to table lock
	 * @param string $col
	 *
	 * @return bool
	 */
	function RowLock($table,$where,$col='1 as adodbignore') {
		return false;
	}

	/**
	 * @param string $table
	 * @return true
	 */
	function CommitLock($table) {
		return $this->CommitTrans();
	}

	/**
	 * @param string $table
	 * @return true
	 */
	function RollbackLock($table) {
		return $this->RollbackTrans();
	}

	/**
	 * PEAR DB Compat - do not use internally.
	 *
	 * The fetch modes for NUMERIC and ASSOC for PEAR DB and ADODB are identical
	 * for easy porting :-)
	 *
	 * @param int $mode The fetchmode ADODB_FETCH_ASSOC or ADODB_FETCH_NUM
	 *
	 * @return int Previous fetch mode
	 */
	function SetFetchMode($mode) {
		$old = $this->fetchMode;
		$this->fetchMode = $mode;

		if ($old === false) {
			global $ADODB_FETCH_MODE;
			return $ADODB_FETCH_MODE;
		}
		return $old;
	}

	/**
	 * PEAR DB Compat - do not use internally.
	 *
	 * @param string     $sql
	 * @param array|bool $inputarr
	 *
	 * @return ADORecordSet|bool
	 */
	function Query($sql, $inputarr=false) {
		$rs = $this->Execute($sql, $inputarr);
		if (!$rs && defined('ADODB_PEAR')) {
			return ADODB_PEAR_Error();
		}
		return $rs;
	}

	/**
	 * PEAR DB Compat - do not use internally
	 */
	function LimitQuery($sql, $offset, $count, $params=false) {
		$rs = $this->SelectLimit($sql, $count, $offset, $params);
		if (!$rs && defined('ADODB_PEAR')) {
			return ADODB_PEAR_Error();
		}
		return $rs;
	}


	/**
	 * PEAR DB Compat - do not use internally
	 */
	function Disconnect() {
		return $this->Close();
	}

	/**
	 * Returns a placeholder for query parameters.
	 *
	 * e.g. $DB->Param('a') will return
	 * - '?' for most databases
	 * - ':a' for Oracle
	 * - '$1', '$2', etc. for PostgreSQL
	 *
	 * @param mixed $name parameter's name.
	 *                    For databases that require positioned params (e.g. PostgreSQL),
	 *                    a "falsy" value can be used to force resetting the placeholder
	 *                    count; using boolean 'false' will reset it without actually
	 *                    returning a placeholder. ADOdb will also automatically reset
	 *                    the count when executing a query.
	 * @param string $type (unused)
	 * @return string query parameter placeholder
	 */
	function Param($name,$type='C') {
		return '?';
	}

	/**
	 * Self-documenting version of Parameter().
	 *
	 * @param $stmt
	 * @param &$var
	 * @param $name
	 * @param int $maxLen
	 * @param bool $type
	 *
	 * @return bool
	 */
	function InParameter(&$stmt, &$var, $name, $maxLen=4000, $type=false) {
		return $this->Parameter($stmt,$var,$name,false,$maxLen,$type);
	}

	/**
	 * Self-documenting version of Parameter().
	 *
	 * @param $stmt
	 * @param $var
	 * @param $name
	 * @param int $maxLen
	 * @param bool $type
	 *
	 * @return bool
	 */
	function OutParameter(&$stmt,&$var,$name,$maxLen=4000,$type=false) {
		return $this->Parameter($stmt,$var,$name,true,$maxLen,$type);

	}

	/**
	 *
	 * Usage in oracle
	 *   $stmt = $db->Prepare('select * from table where id =:myid and group=:group');
	 *   $db->Parameter($stmt,$id,'myid');
	 *   $db->Parameter($stmt,$group,'group',64);
	 *   $db->Execute();
	 *
	 * @param mixed &$stmt Statement returned by Prepare() or PrepareSP().
	 * @param mixed &$var PHP variable to bind to
	 * @param string $name Name of stored procedure variable name to bind to.
	 * @param int|bool $isOutput Indicates direction of parameter 0/false=IN  1=OUT  2= IN/OUT. This is ignored in oci8.
	 * @param int $maxLen Holds an maximum length of the variable.
	 * @param mixed $type The data type of $var. Legal values depend on driver.
	 *
	 * @return bool
	 */
	function Parameter(&$stmt,&$var,$name,$isOutput=false,$maxLen=4000,$type=false) {
		return false;
	}


	function IgnoreErrors($saveErrs=false) {
		if (!$saveErrs) {
			$saveErrs = array($this->raiseErrorFn,$this->_transOK);
			$this->raiseErrorFn = false;
			return $saveErrs;
		} else {
			$this->raiseErrorFn = $saveErrs[0];
			$this->_transOK = $saveErrs[1];
		}
	}

	/**
	 * Improved method of initiating a transaction. Used together with CompleteTrans().
	 * Advantages include:
     *
	 * a. StartTrans/CompleteTrans is nestable, unlike BeginTrans/CommitTrans/RollbackTrans.
	 *    Only the outermost block is treated as a transaction.<br>
	 * b. CompleteTrans auto-detects SQL errors, and will rollback on errors, commit otherwise.<br>
	 * c. All BeginTrans/CommitTrans/RollbackTrans inside a StartTrans/CompleteTrans block
	 *    are disabled, making it backward compatible.
	 */
	function StartTrans($errfn = 'ADODB_TransMonitor') {
		if ($this->transOff > 0) {
			$this->transOff += 1;
			return true;
		}

		$this->_oldRaiseFn = $this->raiseErrorFn;
		$this->raiseErrorFn = $errfn;
		$this->_transOK = true;

		if ($this->debug && $this->transCnt > 0) {
			ADOConnection::outp("Bad Transaction: StartTrans called within BeginTrans");
		}
		$ok = $this->BeginTrans();
		$this->transOff = 1;
		return $ok;
	}


	/**
	 * Complete a transaction.
	 *
	 * Used together with StartTrans() to end a transaction. Monitors connection
	 * for sql errors, and will commit or rollback as appropriate.
	 *
	 * @param bool autoComplete if true, monitor sql errors and commit and
	 *                          rollback as appropriate, and if set to false
	 *                          force rollback even if no SQL error detected.
	 * @returns true on commit, false on rollback.
	 */
	function CompleteTrans($autoComplete = true) {
		if ($this->transOff > 1) {
			$this->transOff -= 1;
			return true;
		}
		$this->raiseErrorFn = $this->_oldRaiseFn;

		$this->transOff = 0;
		if ($this->_transOK && $autoComplete) {
			if (!$this->CommitTrans()) {
				$this->_transOK = false;
				if ($this->debug) {
					ADOConnection::outp("Smart Commit failed");
				}
			} else {
				if ($this->debug) {
					ADOConnection::outp("Smart Commit occurred");
				}
			}
		} else {
			$this->_transOK = false;
			$this->RollbackTrans();
			if ($this->debug) {
				ADOConnection::outp("Smart Rollback occurred");
			}
		}

		return $this->_transOK;
	}

	/**
	 * At the end of a StartTrans/CompleteTrans block, perform a rollback.
	 */
	function FailTrans() {
		if ($this->debug)
			if ($this->transOff == 0) {
				ADOConnection::outp("FailTrans outside StartTrans/CompleteTrans");
			} else {
				ADOConnection::outp("FailTrans was called");
				adodb_backtrace();
			}
		$this->_transOK = false;
	}

	/**
	 * Check if transaction has failed, only for Smart Transactions.
	 */
	function HasFailedTrans() {
		if ($this->transOff > 0) {
			return $this->_transOK == false;
		}
		return false;
	}

	/**
	 * Execute SQL
	 *
	 * @param string     $sql      SQL statement to execute, or possibly an array
	 *                             holding prepared statement ($sql[0] will hold sql text)
	 * @param array|bool $inputarr holds the input data to bind to.
	 *                             Null elements will be set to null.
	 *
	 * @return ADORecordSet|false
	 */
	public function Execute($sql, $inputarr = false) {
		if ($this->fnExecute) {
			$fn = $this->fnExecute;
			$ret = $fn($this,$sql,$inputarr);
			if (isset($ret)) {
				return $ret;
			}
		}
		if ($inputarr !== false) {
			if (!is_array($inputarr)) {
				$inputarr = array($inputarr);
			}

			$element0 = reset($inputarr);
			# is_object check because oci8 descriptors can be passed in
			$array_2d = $this->bulkBind && is_array($element0) && !is_object(reset($element0));

			//remove extra memory copy of input -mikefedyk
			unset($element0);

			if (!is_array($sql) && !$this->_bindInputArray) {
				// @TODO this would consider a '?' within a string as a parameter...
				$sqlarr = explode('?',$sql);
				$nparams = sizeof($sqlarr)-1;

				if (!$array_2d) {
					// When not Bind Bulk - convert to array of arguments list
					$inputarr = array($inputarr);
				} else {
					// Bulk bind - Make sure all list of params have the same number of elements
					$countElements = array_map('count', $inputarr);
					if (1 != count(array_unique($countElements))) {
						$this->outp_throw(
							"[bulk execute] Input array has different number of params  [" . print_r($countElements, true) .  "].",
							'Execute'
						);
						return false;
					}
					unset($countElements);
				}
				// Make sure the number of parameters provided in the input
				// array matches what the query expects
				$element0 = reset($inputarr);
				if ($nparams != count($element0)) {
					$this->outp_throw(
						"Input array has " . count($element0) .
						" params, does not match query: '" . htmlspecialchars($sql) . "'",
						'Execute'
					);
					return false;
				}

				// clean memory
				unset($element0);

				foreach($inputarr as $arr) {
					$sql = ''; $i = 0;
					foreach ($arr as $v) {
						$sql .= $sqlarr[$i];
						// from Ron Baldwin <ron.baldwin#sourceprose.com>
						// Only quote string types
						$typ = gettype($v);
						if ($typ == 'string') {
							//New memory copy of input created here -mikefedyk
							$sql .= $this->qstr($v);
						} else if ($typ == 'double') {
							$sql .= str_replace(',','.',$v); // locales fix so 1.1 does not get converted to 1,1
						} else if ($typ == 'boolean') {
							$sql .= $v ? $this->true : $this->false;
						} else if ($typ == 'object') {
							if (method_exists($v, '__toString')) {
								$sql .= $this->qstr($v->__toString());
							} else {
								$sql .= $this->qstr((string) $v);
							}
						} else if ($v === null) {
							$sql .= 'NULL';
						} else {
							$sql .= $v;
						}
						$i += 1;

						if ($i == $nparams) {
							break;
						}
					} // while
					if (isset($sqlarr[$i])) {
						$sql .= $sqlarr[$i];
						if ($i+1 != sizeof($sqlarr)) {
							$this->outp_throw( "Input Array does not match ?: ".htmlspecialchars($sql),'Execute');
						}
					} else if ($i != sizeof($sqlarr)) {
						$this->outp_throw( "Input array does not match ?: ".htmlspecialchars($sql),'Execute');
					}

					$ret = $this->_Execute($sql);
					if (!$ret) {
						return $ret;
					}
				}
			} else {
				if ($array_2d) {
					if (is_string($sql)) {
						$stmt = $this->Prepare($sql);
					} else {
						$stmt = $sql;
					}

					foreach($inputarr as $arr) {
						$ret = $this->_Execute($stmt,$arr);
						if (!$ret) {
							return $ret;
						}
					}
				} else {
					$ret = $this->_Execute($sql,$inputarr);
				}
			}
		} else {
			$ret = $this->_Execute($sql,false);
		}

		return $ret;
	}

	function _Execute($sql,$inputarr=false) {
		// ExecuteCursor() may send non-string queries (such as arrays),
		// so we need to ignore those.
		if( is_string($sql) ) {
			// Strips keyword used to help generate SELECT COUNT(*) queries
			// from SQL if it exists.
			// TODO: obsoleted by #715 - kept for backwards-compatibility
			$sql = str_replace( '_ADODB_COUNT', '', $sql );
		}

		if ($this->debug) {
			$eh = new \ADOdb\Resources\ErrorHandling;

			$this->_queryID = $eh->_adodb_debug_execute($this, $sql,$inputarr);
		} else {
			$this->_queryID = @$this->_query($sql,$inputarr);
		}

		// ************************
		// OK, query executed
		// ************************

		// error handling if query fails
		if ($this->_queryID === false) {
			$fn = $this->raiseErrorFn;
			if ($fn) {
				$fn($this->databaseType,'EXECUTE',$this->ErrorNo(),$this->ErrorMsg(),$sql,$inputarr,$this);
			}
			return false;
		}

		// return simplified recordset for inserts/updates/deletes with lower overhead
		if ($this->_queryID === true) {
			$rsclass = $this->rsPrefix.'empty';
			$rs = (class_exists($rsclass)) ? new $rsclass():  new \ADOdb\Resources\ADORecordSetEmpty();

			return $rs;
		}

		if ($this->dataProvider == 'pdo' && $this->databaseType != 'pdo') {
			// PDO uses a slightly different naming convention for the
			// recordset class if the database type is changed, so we must
			// treat it specifically. The mysql driver leaves the
			// databaseType as pdo
			$rsclass = $this->rsPrefix . 'pdo_' . $this->databaseType;
		} else {
			$rsclass = $this->rsPrefix . $this->databaseType;
		}

		$metaClassFile = sprintf(
			'%s/Resources/%s/ADORecordSet.php',
			ADODB_DIR,
			$this->metaFunctionProvider
		);

		$rsclass = sprintf(
			'ADOdb\Resources\%s\ADORecordSet',
			$this->metaFunctionProvider
		);

		// return real recordset from select statement
		$rs = new $rsclass($this->_queryID,$this->fetchMode);
		$rs->connection = $this; // Pablo suggestion
		$rs->Init();
		if (is_array($sql)) {
			$rs->sql = $sql[0];
		} else {
			$rs->sql = $sql;
		}
		if ($rs->_numOfRows <= 0) {
			global $ADODB_COUNTRECS;
			if ($ADODB_COUNTRECS) {
				if (!$rs->EOF) {
					$rs = $this->_rs2rs($rs,-1,-1,!is_array($sql));
					$rs->_queryID = $this->_queryID;
				} else
					$rs->_numOfRows = 0;
			}
		}
		return $rs;
	}

	/**
	 * Execute a query.
	 *
	 * @param string|array $sql        Query to execute.
	 * @param array        $inputarr   An optional array of parameters.
	 *
	 * @return mixed|bool Query identifier or true if execution successful, false if failed.
	 */
	function _query($sql, $inputarr = false) {
		return false;
	}

	function CreateSequence($seqname='adodbseq',$startID=1) {
		if (empty($this->_genSeqSQL)) {
			return false;
		}
		return $this->Execute(sprintf($this->_genSeqSQL,$seqname,$startID));
	}

	function DropSequence($seqname='adodbseq') {
		if (empty($this->_dropSeqSQL)) {
			return false;
		}
		return $this->Execute(sprintf($this->_dropSeqSQL,$seqname));
	}

	/**
	 * Generates a sequence id and stores it in $this->genID.
	 *
	 * GenID is only available if $this->hasGenID = true;
	 *
	 * @param string $seqname Name of sequence to use
	 * @param int    $startID If sequence does not exist, start at this ID
	 *
	 * @return int Sequence id, 0 if not supported
	 */
	function GenID($seqname='adodbseq',$startID=1) {
		if (!$this->hasGenID) {
			return 0; // formerly returns false pre 1.60
		}

		$getnext = sprintf($this->_genIDSQL,$seqname);

		$holdtransOK = $this->_transOK;

		$save_handler = $this->raiseErrorFn;
		$this->raiseErrorFn = '';
		@($rs = $this->Execute($getnext));
		$this->raiseErrorFn = $save_handler;

		if (!$rs) {
			$this->_transOK = $holdtransOK; //if the status was ok before reset
			$createseq = $this->Execute(sprintf($this->_genSeqSQL,$seqname,$startID));
			$rs = $this->Execute($getnext);
		}
		if ($rs && !$rs->EOF) {
			$this->genID = reset($rs->fields);
		} else {
			$this->genID = 0; // false
		}

		if ($rs) {
			$rs->Close();
		}

		return (int)$this->genID;
	}

	/**
	 * Returns the last inserted ID.
	 *
	 * Not all databases support this feature. Some do not require to specify
	 * table or column name (e.g. MySQL).
	 *
	 * @param string $table  Table name, default ''
	 * @param string $column Column name, default ''
	 *
	 * @return int The last inserted ID.
	 */
	function Insert_ID($table='',$column='') {
		if ($this->_logsql && $this->lastInsID) {
			return $this->lastInsID;
		}
		if ($this->hasInsertID) {
			return $this->_insertID($table,$column);
		}
		if ($this->debug) {
			ADOConnection::outp( '<p>Insert_ID error</p>');
			adodb_backtrace();
		}
		return false;
	}

	/**
	 * Enable or disable the Last Insert Id functionality.
	 *
	 * If the Driver supports it, this function allows setting {@see $hasInsertID}.
	 *
	 * @param bool $enable False to disable
	 */
	public function enableLastInsertID($enable = true) {}

	/**
	 * Return the id of the last row that has been inserted in a table.
	 *
	 * @param string $table
	 * @param string $column
	 *
	 * @return int|false
	 */
	protected function _insertID($table = '', $column = '')
	{
		return false;
	}

	/**
	 * Portable Insert ID. Pablo Roca <pabloroca#mvps.org>
	 *
	 * @param string $table
	 * @param string $id

	 * @return mixed The last inserted ID. All databases support this, but be
	 *               aware of possible problems in multiuser environments.
	 *               Heavily test this before deploying.
	 */
	function PO_Insert_ID($table="", $id="") {
		if ($this->hasInsertID){
			return $this->Insert_ID($table,$id);
		} else {
			return $this->GetOne("SELECT MAX($id) FROM $table");
		}
	}

	/**
	 * @return int|false Number of rows affected by UPDATE/DELETE
	 */
	function Affected_Rows() {
		if ($this->hasAffectedRows) {
			if ($this->fnExecute === 'adodb_log_sql') {
				if ($this->_logsql && $this->_affected !== false) {
					return $this->_affected;
				}
			}
			$val = $this->_affectedrows();
			return ($val < 0) ? false : $val;
		}

		if ($this->debug) {
			ADOConnection::outp( '<p>Affected_Rows error</p>',false);
		}
		return false;
	}


	/**
	 * @return string the last error message
	 */
	function ErrorMsg() {
		if ($this->_errorMsg) {
			return '!! '.strtoupper($this->dataProvider.' '.$this->databaseType).': '.$this->_errorMsg;
		} else {
			return '';
		}
	}


	/**
	 * @return int the last error number. Normally 0 means no error.
	 */
	function ErrorNo() {
		return ($this->_errorMsg) ? -1 : 0;
	}

	function MetaError($err=false) {
		include_once(ADODB_DIR."/adodb-error.inc.php");
		if ($err === false) {
			$err = $this->ErrorNo();
		}
		return adodb_error($this->dataProvider,$this->databaseType,$err);
	}

	function MetaErrorMsg($errno) {
		include_once(ADODB_DIR."/adodb-error.inc.php");
		return adodb_errormsg($errno);
	}

	/**
	 * @returns an array with the primary key columns in it.
	 */
	function MetaPrimaryKeys($table, $owner=false) {
	// owner not used in base class - see oci8
		$p = array();
		$objs = $this->MetaColumns($table);
		print "\n=========\n";
		print_r($objs);
		if ($objs) {
			foreach($objs as $v) {
				if (!empty($v->primary_key)) {
					$p[] = $v->name;
				}
			}
		}
		if (sizeof($p)) {
			return $p;
		}
		if (function_exists('ADODB_VIEW_PRIMARYKEYS')) {
			return ADODB_VIEW_PRIMARYKEYS($this->databaseType, $this->database, $table, $owner);
		}
		return false;
	}

	/**
	 * Returns a list of Foreign Keys associated with a specific table.
	 *
	 * If there are no foreign keys then the function returns false.
	 *
	 * @param string $table       The name of the table to get the foreign keys for.
	 * @param string $owner       Table owner/schema.
	 * @param bool   $upper       If true, only matches the table with the uppercase name.
	 * @param bool   $associative Returns the result in associative mode;
	 *                            if ADODB_FETCH_MODE is already associative, then
	 *                            this parameter is discarded.
	 *
	 * @return string[]|false An array where keys are tables, and values are foreign keys;
	 *                        false if no foreign keys could be found.
	 */
	function metaForeignKeys($table, $owner = '', $upper = false, $associative = false) {
		return false;
	}

	/**
	 * Choose a database to connect to. Many databases do not support this.
	 *
	 * @param string $dbName the name of the database to select
	 * @return bool
	 */
	function SelectDB($dbName) {return false;}


	/**
	 * Select a limited number of rows.
	 *
	 * Will select, getting rows from $offset (1-based), for $nrows.
	 * This simulates the MySQL "select * from table limit $offset,$nrows" , and
	 * the PostgreSQL "select * from table limit $nrows offset $offset". Note that
	 * MySQL and PostgreSQL parameter ordering is the opposite of the other.
	 * eg.
	 *  SelectLimit('select * from table',3); will return rows 1 to 3 (1-based)
	 *  SelectLimit('select * from table',3,2); will return rows 3 to 5 (1-based)
	 *
	 * Uses SELECT TOP for Microsoft databases (when $this->hasTop is set)
	 * BUG: Currently SelectLimit fails with $sql with LIMIT or TOP clause already set
	 *
	 * @param string     $sql
	 * @param int        $offset     Row to start calculations from (1-based)
	 * @param int        $nrows      Number of rows to get
	 * @param array|bool $inputarr   Array of bind variables
	 * @param int        $secs2cache Private parameter only used by jlim
	 *
	 * @return ADORecordSet The recordset ($rs->databaseType == 'array')
	 */
	function SelectLimit($sql,$nrows=-1,$offset=-1, $inputarr=false,$secs2cache=0) {
		$nrows = (int)$nrows;
		$offset = (int)$offset;

		if ($this->hasTop && $nrows > 0) {
			// suggested by Reinhard Balling. Access requires top after distinct
			// Informix requires first before distinct - F Riosa
			$ismssql = (strpos($this->databaseType,'mssql') !== false);
			if ($ismssql) {
				$isaccess = false;
			} else {
				$isaccess = (strpos($this->databaseType,'access') !== false);
			}

			if ($offset <= 0) {
				// access includes ties in result
				if ($isaccess) {
					$sql = preg_replace(
						'/(^\s*select\s+(distinctrow|distinct)?)/i',
						'\\1 '.$this->hasTop.' '.$nrows.' ',
						$sql
					);

					if ($secs2cache != 0) {
						$ret = $this->CacheExecute($secs2cache, $sql,$inputarr);
					} else {
						$ret = $this->Execute($sql,$inputarr);
					}
					return $ret; // PHP5 fix
				} else if ($ismssql){
					$sql = preg_replace(
						'/(^\s*select\s+(distinctrow|distinct)?)/i',
						'\\1 '.$this->hasTop.' '.$nrows.' ',
						$sql
					);
				} else {
					$sql = preg_replace(
						'/(^\s*select\s)/i',
						'\\1 '.$this->hasTop.' '.$nrows.' ',
						$sql
					);
				}
			} else {
				$nn = $nrows + $offset;
				if ($isaccess || $ismssql) {
					$sql = preg_replace(
						'/(^\s*select\s+(distinctrow|distinct)?)/i',
						'\\1 '.$this->hasTop.' '.$nn.' ',
						$sql
					);
				} else {
					$sql = preg_replace(
						'/(^\s*select\s)/i',
						'\\1 '.$this->hasTop.' '.$nn.' ',
						$sql
					);
				}
			}
		}

		// if $offset>0, we want to skip rows, and $ADODB_COUNTRECS is set, we buffer  rows
		// 0 to offset-1 which will be discarded anyway. So we disable $ADODB_COUNTRECS.
		global $ADODB_COUNTRECS;

		try {
			$savec = $ADODB_COUNTRECS;
			$ADODB_COUNTRECS = false;

			if ($secs2cache != 0) {
				$rs = $this->CacheExecute($secs2cache, $sql, $inputarr);
			} else {
				$rs = $this->Execute($sql, $inputarr);
			}
		} finally {
			$ADODB_COUNTRECS = $savec;
		}

		if ($rs && !$rs->EOF) {
			$rs = $this->_rs2rs($rs,$nrows,$offset);
		}
		//print_r($rs);
		return $rs;
	}

	/**
	 * Create serializable recordset. Breaks rs link to connection.
	 *
	 * @param ADORecordSet $rs the recordset to serialize
	 *
	 * @return ADORecordSet_array|bool the new recordset
	 */
	function SerializableRS(&$rs) {
		$rs2 = $this->_rs2rs($rs);
		$ignore = false;
		$rs2->connection = $ignore;

		return $rs2;
	}

	/**
	 * Convert a database recordset to an array recordset.
	 *
	 * Input recordset's cursor should be at beginning, and old $rs will be closed.
	 *
	 * @param ADORecordSet $rs     the recordset to copy
	 * @param int          $nrows  number of rows to retrieve (optional)
	 * @param int          $offset offset by number of rows (optional)
	 * @param bool         $close
	 *
	 * @return ADORecordSet_array|ADORecordSet|bool the new recordset
	 */
	function &_rs2rs(&$rs,$nrows=-1,$offset=-1,$close=true) {
		if (! $rs) {
			$ret = false;
			return $ret;
		}
		$dbtype = $rs->databaseType;
		if (!$dbtype) {
			$rs = $rs;  // required to prevent crashing in 4.2.1, but does not happen in 4.3.1 -- why ?
			return $rs;
		}
		if (($dbtype == 'array' || $dbtype == 'csv') && $nrows == -1 && $offset == -1) {
			$rs->MoveFirst();
			$rs = $rs; // required to prevent crashing in 4.2.1, but does not happen in 4.3.1-- why ?
			return $rs;
		}
		$flds = array();
		for ($i=0, $max=$rs->FieldCount(); $i < $max; $i++) {
			$flds[] = $rs->FetchField($i);
		}

		$arr = $rs->GetArrayLimit($nrows,$offset);
		//print_r($arr);
		if ($close) {
			$rs->Close();
		}

		$arrayClass = $this->arrayClass;

		$rs2 = new $arrayClass($fakeQueryId=1);
		$rs2->connection = $this;
		$rs2->sql = $rs->sql;
		$rs2->dataProvider = $this->dataProvider;
		$rs2->InitArrayFields($arr,$flds);

		$rs2->adodbFetchMode = $rs2->fetchMode = isset($rs->adodbFetchMode) ? $rs->adodbFetchMode : $rs->fetchMode;
		return $rs2;
	}

	/**
	 * Return all rows.
	 *
	 * Compat with PEAR DB.
	 *
	 * @param string     $sql      SQL statement
	 * @param array|bool $inputarr Input bind array
	 *
	 * @return array|false
	 */
	function GetAll($sql, $inputarr=false) {
		return $this->GetArray($sql,$inputarr);
	}

	/**
	 * Execute statement and return rows in an array.
	 *
	 * The function executes a statement and returns all of the returned rows in
	 * an array, or false if the statement execution fails or if only 1 column
	 * is requested in the SQL statement.
	 * If no records match the provided SQL statement, an empty array is returned.
	 *
	 * @param string     $sql         SQL statement
	 * @param array|bool $inputarr    input bind array
	 * @param bool       $force_array
	 * @param bool       $first2cols
	 *
	 * @return array|false
	 */
	public function GetAssoc($sql, $inputarr = false, $force_array = false, $first2cols = false) {
		$rs = $this->Execute($sql, $inputarr);

		if (!$rs) {
			/*
			* Execution failure
			*/
			return false;
		}
		return $rs->GetAssoc($force_array, $first2cols);
	}

	/**
	 * Search for the results of an executed query in the cache.
	 *
	 * @param int $secs2cache
	 * @param string|bool $sql         SQL statement
	 * @param array|bool  $inputarr    input bind array
	 * @param bool        $force_array
	 * @param bool        $first2cols
	 *
	 * @return false|array
	 */
	public function CacheGetAssoc($secs2cache, $sql = false, $inputarr = false,$force_array = false, $first2cols = false) {
		if (!is_numeric($secs2cache)) {
			$first2cols = $force_array;
			$force_array = $inputarr;
		}
		$rs = $this->CacheExecute($secs2cache, $sql, $inputarr);
		if (!$rs) {
			return false;
		}
		return $rs->GetAssoc($force_array, $first2cols);
	}

	/**
	 * Return first element of first row of sql statement. Recordset is disposed
	 * for you.
	 *
	 * @param string		$sql		SQL statement
	 * @param array|bool	$inputarr	input bind array
	 * @return mixed
	 */
	public function GetOne($sql, $inputarr=false) {
		global $ADODB_COUNTRECS,$ADODB_GETONE_EOF;

		try {
			$crecs = $ADODB_COUNTRECS;
			$ADODB_COUNTRECS = false;
			$rs = $this->Execute($sql, $inputarr);
		} finally {
			$ADODB_COUNTRECS = $crecs;
		}

		$ret = false;
		if ($rs) {
			if ($rs->EOF) {
				$ret = $ADODB_GETONE_EOF;
			} else {
				$ret = reset($rs->fields);
			}

			$rs->Close();
		}
		return $ret;
	}

	// $where should include 'WHERE fld=value'
	function GetMedian($table, $field,$where = '') {
		$total = $this->GetOne("select count(*) from $table $where");
		if (!$total) {
			return false;
		}

		$midrow = (int) ($total/2);
		$rs = $this->SelectLimit("select $field from $table $where order by 1",1,$midrow);
		if ($rs && !$rs->EOF) {
			return reset($rs->fields);
		}
		return false;
	}


	function CacheGetOne($secs2cache,$sql=false,$inputarr=false) {
		global $ADODB_GETONE_EOF;

		$ret = false;
		$rs = $this->CacheExecute($secs2cache,$sql,$inputarr);
		if ($rs) {
			if ($rs->EOF) {
				$ret = $ADODB_GETONE_EOF;
			} else {
				$ret = reset($rs->fields);
			}
			$rs->Close();
		}

		return $ret;
	}

	/**
	 * Executes a statement and returns each row's first column in an array.
	 *
	 * @param string     $sql      SQL statement
	 * @param array|bool $inputarr input bind array
	 * @param bool       $trim     enables space trimming of the returned value.
	 *                             This is only relevant if the returned string
	 *                             is coming from a CHAR type field.
	 *
	 * @return array|false 1D array containing each row's first column;
	 *                     false if the statement execution fails.
	 */
	function GetCol($sql, $inputarr = false, $trim = false) {
		$rs = $this->Execute($sql, $inputarr);
		if ($rs) {
			$rv = array();
			if ($trim) {
				while (!$rs->EOF) {
					$rv[] = trim(reset($rs->fields));
					$rs->MoveNext();
				}
			} else {
				while (!$rs->EOF) {
					$rv[] = reset($rs->fields);
					$rs->MoveNext();
				}
			}
			$rs->Close();
		} else {
			$rv = false;
		}
		return $rv;
	}

	function CacheGetCol($secs, $sql = false, $inputarr = false,$trim=false) {
		$rs = $this->CacheExecute($secs, $sql, $inputarr);
		if ($rs) {
			$rv = array();
			if ($trim) {
				while (!$rs->EOF) {
					$rv[] = trim(reset($rs->fields));
					$rs->MoveNext();
				}
			} else {
				while (!$rs->EOF) {
					$rv[] = reset($rs->fields);
					$rs->MoveNext();
				}
			}
			$rs->Close();
		} else
			$rv = false;

		return $rv;
	}

	/**
	 * Calculate the offset of a date for a particular database
	 * and generate appropriate SQL.
	 *
	 * Useful for calculating future/past dates and storing in a database.
	 *
	 * @param double       $dayFraction 1.5 means 1.5 days from now, 1.0/24 for 1 hour
	 * @param string|false $date        Reference date, false for system time
	 *
	 * @return string
	 */
	function OffsetDate($dayFraction,$date=false) {
		if (!$date) {
			$date = $this->sysDate;
		}
		return  '('.$date.'+'.$dayFraction.')';
	}


	/**
	 * Executes a statement and returns a the entire recordset in an array.
	 *
	 * @param string     $sql      SQL statement
	 * @param array|bool $inputarr input bind array
	 *
	 * @return array|false
	 */
	function GetArray($sql,$inputarr=false) {
		global $ADODB_COUNTRECS;

		try {
			$savec = $ADODB_COUNTRECS;
			$ADODB_COUNTRECS = false;
			$rs = $this->Execute($sql, $inputarr);
		} finally {
			$ADODB_COUNTRECS = $savec;
		}

		if (!$rs)
			if (defined('ADODB_PEAR')) {
				return ADODB_PEAR_Error();
			} else {
				return false;
			}
		$arr = $rs->GetArray();
		$rs->Close();
		return $arr;
	}

	function CacheGetAll($secs2cache,$sql=false,$inputarr=false) {
		return $this->CacheGetArray($secs2cache,$sql,$inputarr);
	}

	function CacheGetArray($secs2cache,$sql=false,$inputarr=false) {
		global $ADODB_COUNTRECS;

		try {
			$savec = $ADODB_COUNTRECS;
			$ADODB_COUNTRECS = false;
			$rs = $this->CacheExecute($secs2cache, $sql, $inputarr);
		} finally {
			$ADODB_COUNTRECS = $savec;
		}

		if (!$rs)
			if (defined('ADODB_PEAR')) {
				return ADODB_PEAR_Error();
			} else {
				return false;
			}
		$arr = $rs->GetArray();
		$rs->Close();
		return $arr;
	}

	function GetRandRow($sql, $arr= false) {
		$rezarr = $this->GetAll($sql, $arr);
		$sz = sizeof($rezarr);
		return $rezarr[abs(rand()) % $sz];
	}

	/**
	 * Return one row of sql statement. Recordset is disposed for you.
	 * Note that SelectLimit should not be called.
	 *
	 * @param string     $sql      SQL statement
	 * @param array|bool $inputarr input bind array
	 *
	 * @return array|false Array containing the first row of the query
	 */
	function GetRow($sql,$inputarr=false) {
		global $ADODB_COUNTRECS;

		try {
			$crecs = $ADODB_COUNTRECS;
			$ADODB_COUNTRECS = false;
			$rs = $this->Execute($sql, $inputarr);
		} finally {
			$ADODB_COUNTRECS = $crecs;
		}

		if ($rs) {
			if (!$rs->EOF) {
				$arr = $rs->fields;
			} else {
				$arr = array();
			}
			$rs->Close();
			return $arr;
		}

		return false;
	}

	/**
	 * @param int $secs2cache
	 * @param string|false $sql
	 * @param mixed[]|bool $inputarr
	 * @return mixed[]|bool
	 */
	function CacheGetRow($secs2cache,$sql=false,$inputarr=false) {
		$rs = $this->CacheExecute($secs2cache,$sql,$inputarr);
		if ($rs) {
			if (!$rs->EOF) {
				$arr = $rs->fields;
			} else {
				$arr = array();
			}

			$rs->Close();
			return $arr;
		}
		return false;
	}

	/**
	 * Insert or replace a single record (upsert).
	 *
	 * Note: this is not the same as MySQL's replace.
	 * ADOdb's Replace() uses update-insert semantics, not insert-delete-duplicates of MySQL.
	 * Also note that no table locking is done currently, so it is possible that the
	 * record be inserted twice by two programs...
	 *
	 * $this->Replace('products', array('prodname' =>"'Nails'","price" => 3.99), 'prodname');
	 *
	 * $table		table name
	 * $fieldArray	associative array of data (you must quote strings yourself).
	 * $keyCol		the primary key field name or if compound key, array of field names
	 * autoQuote		set to true to use a heuristic to quote strings. Works with nulls and numbers
	 *					but does not work with dates nor SQL functions.
	 * has_autoinc	the primary key is an auto-inc field, so skip in insert.
	 *
	 * Currently blob replace not supported
	 *
	 * returns 0 = fail, 1 = update, 2 = insert
	 */

	function Replace($table, $fieldArray, $keyCol, $autoQuote=false, $has_autoinc=false) {
		
		$adoHelpers = new \ADOdb\Resources\ADOHelpers;
		
		return $adoHelpers->_adodb_replace($this, $table, $fieldArray, $keyCol, $autoQuote, $has_autoinc);
	}


	/**
	 * Will select, getting rows from $offset (1-based), for $nrows.
	 * This simulates the MySQL "select * from table limit $offset,$nrows" , and
	 * the PostgreSQL "select * from table limit $nrows offset $offset". Note that
	 * MySQL and PostgreSQL parameter ordering is the opposite of the other.
	 * eg.
	 *  CacheSelectLimit(15,'select * from table',3); will return rows 1 to 3 (1-based)
	 *  CacheSelectLimit(15,'select * from table',3,2); will return rows 3 to 5 (1-based)
	 *
	 * BUG: Currently CacheSelectLimit fails with $sql with LIMIT or TOP clause already set
	 *
	 * @param int    $secs2cache Seconds to cache data, set to 0 to force query. This is optional
	 * @param string $sql
	 * @param int    $offset     Row to start calculations from (1-based)
	 * @param int    $nrows      Number of rows to get
	 * @param array $inputarr    Array of bind variables
	 *
	 * @return ADORecordSet The recordset ($rs->databaseType == 'array')
	 */
	function CacheSelectLimit($secs2cache,$sql,$nrows=-1,$offset=-1,$inputarr=false) {
		if (!is_numeric($secs2cache)) {
			if ($sql === false) {
				$sql = -1;
			}
			if ($offset == -1) {
				$offset = false;
			}
												// sql,	nrows, offset,inputarr
			$rs = $this->SelectLimit($secs2cache,$sql,$nrows,$offset,$this->cacheSecs);
		} else {
			if ($sql === false) {
				$this->outp_throw("Warning: \$sql missing from CacheSelectLimit()",'CacheSelectLimit');
			}
			$rs = $this->SelectLimit($sql,$nrows,$offset,$inputarr,$secs2cache);
		}
		return $rs;
	}

	/**
	 * Flush cached recordsets that match a particular $sql statement.
	 * If $sql == false, then we purge all files in the cache.
	 */
	function CacheFlush($sql=false,$inputarr=false) {
		global $ADODB_CACHE_DIR, $ADODB_CACHE;

		# Create cache if it does not exist
		if (empty($ADODB_CACHE)) {
			$this->_CreateCache();
		}

		if (!$sql) {
			$ADODB_CACHE->flushall($this->debug);
			return;
		}

		$f = $this->_gencachename($sql.serialize($inputarr),false);
		return $ADODB_CACHE->flushcache($f, $this->debug);
	}


	/**
	 * Private function to generate filename for caching.
	 * Filename is generated based on:
	 *
	 *  - sql statement
	 *  - database type (oci8, ibase, ifx, etc)
	 *  - database name
	 *  - userid
	 *  - setFetchMode (adodb 4.23)
	 *
	 * We create 256 sub-directories in the cache directory ($ADODB_CACHE_DIR).
	 * Assuming that we can have 50,000 files per directory with good performance,
	 * then we can scale to 12.8 million unique cached recordsets. Wow!
	 */
	function _gencachename($sql,$createdir) {
		global $ADODB_CACHE, $ADODB_CACHE_DIR;

		if ($this->fetchMode === false) {
			global $ADODB_FETCH_MODE;
			$mode = $ADODB_FETCH_MODE;
		} else {
			$mode = $this->fetchMode;
		}
		$m = md5($sql.$this->databaseType.$this->database.$this->user.$mode);
		if (!$ADODB_CACHE->createdir) {
			return $m;
		}
		if (!$createdir) {
			$dir = $ADODB_CACHE->getdirname($m);
		} else {
			$dir = $ADODB_CACHE->createdir($m, $this->debug);
		}

		return $dir.'/adodb_'.$m.'.cache';
	}


	/**
	 * Execute SQL, caching recordsets.
	 *
	 * @param int         $secs2cache Seconds to cache data, set to 0 to force query.
	 *                                This is an optional parameter.
	 * @param string|bool $sql        SQL statement to execute
	 * @param array|bool  $inputarr   Holds the input data to bind
	 *
	 * @return ADORecordSet RecordSet or false
	 */
	function CacheExecute($secs2cache,$sql=false,$inputarr=false) {
		global $ADODB_CACHE;

		if (empty($ADODB_CACHE)) {
			$this->_CreateCache();
		}

		if (!is_numeric($secs2cache)) {
			$inputarr = $sql;
			$sql = $secs2cache;
			$secs2cache = $this->cacheSecs;
		}

		if (is_array($sql)) {
			$sqlparam = $sql;
			$sql = $sql[0];
		} else
			$sqlparam = $sql;


		$md5file = $this->_gencachename($sql.serialize($inputarr),true);
		$err = '';

		if ($secs2cache > 0){
			$rs = $ADODB_CACHE->readcache($md5file,$err,$secs2cache,$this->arrayClass);
			$this->numCacheHits += 1;
		} else {
			$err='Timeout 1';
			$rs = false;
			$this->numCacheMisses += 1;
		}

		if (!$rs) {
			// no cached rs found
			if ($this->debug) {
				if ($this->debug !== -1) {
					ADOConnection::outp( " $md5file cache failure: $err (this is a notice and not an error)");
				}
			}

			$rs = $this->Execute($sqlparam,$inputarr);

			if ($rs) {
				$eof = $rs->EOF;
				$rs = $this->_rs2rs($rs); // read entire recordset into memory immediately
				$rs->timeCreated = time(); // used by caching
				$txt = _rs2serialize($rs,false,$sql); // serialize

				$ok = $ADODB_CACHE->writecache($md5file,$txt,$this->debug, $secs2cache);
				if (!$ok) {
					if ($ok === false) {
						$em = 'Cache write error';
						$en = -32000;

						if ($fn = $this->raiseErrorFn) {
							$fn($this->databaseType,'CacheExecute', $en, $em, $md5file,$sql,$this);
						}
					} else {
						$em = 'Cache file locked warning';
						$en = -32001;
						// do not call error handling for just a warning
					}

					if ($this->debug) {
						ADOConnection::outp( " ".$em);
					}
				}
				if ($rs->EOF && !$eof) {
					$rs->MoveFirst();
					//$rs = csv2rs($md5file,$err);
					$rs->connection = $this; // Pablo suggestion
				}

			} else if (!$this->memCache) {
				$ADODB_CACHE->flushcache($md5file);
			}
		} else {
			$this->_errorMsg = '';
			$this->_errorCode = 0;

			if ($this->fnCacheExecute) {
				$fn = $this->fnCacheExecute;
				$fn($this, $secs2cache, $sql, $inputarr);
			}
			// ok, set cached object found
			$rs->connection = $this; // Pablo suggestion
			if ($this->debug){
				if ($this->debug == 99) {
					adodb_backtrace();
				}
				$inBrowser = isset($_SERVER['HTTP_USER_AGENT']);
				$ttl = $rs->timeCreated + $secs2cache - time();
				$s = is_array($sql) ? $sql[0] : $sql;
				if ($inBrowser) {
					$s = '<i>'.htmlspecialchars($s).'</i>';
				}

				ADOConnection::outp( " $md5file reloaded, ttl=$ttl [ $s ]");
			}
		}
		return $rs;
	}


	/**
	 * Simple interface to insert and update records.
	 *
	 * Automatically generate and execute INSERT and UPDATE statements
	 * on a given table, similar to PEAR DB's autoExecute().
	 *
	 * @param string $table        Name of the table to process.
	 * @param array $fields_values Associative array of field names => values.
	 * @param string|int $mode     Execution mode: 'INSERT' (default), 'UPDATE' or
	 *                             one of the DB_AUTOQUERY_xx constants.
	 * @param string $where        SQL where clause (mandatory in UPDATE mode as a safety measure)
	 * @param bool $forceUpdate    If true, update all provided fields, even if they have not changed;
	 * 							   otherwise only modified fields are updated.
	 * @param bool $magic_quotes   This param is not used since 5.21.0.
	 *                             It remains for backwards compatibility.
	 *
	 * @return bool
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	function autoExecute($table, $fields_values, $mode = 'INSERT', $where = '', $forceUpdate = true, $magic_quotes = false) {
		switch($mode) {
			case DB_AUTOQUERY_INSERT:
			case DB_AUTOQUERY_UPDATE:
				break;
			case 'UPDATE':
				$mode = DB_AUTOQUERY_UPDATE;
				break;
			case 'INSERT':
				$mode = DB_AUTOQUERY_INSERT;
				break;
			default:
				$this->outp_throw("AutoExecute: Unknown mode=$mode", 'AutoExecute');
				return false;
		}

		if (empty($fields_values)) {
			$this->outp_throw('AutoExecute: Empty fields array', 'AutoExecute');
			return false;
		}
		if (empty($where) && $mode == DB_AUTOQUERY_UPDATE) {
			$this->outp_throw('AutoExecute: Illegal mode=UPDATE with empty WHERE clause', 'AutoExecute');
			return false;
		}

		$sql = "SELECT * FROM $table";
		if (!empty($where)) {
			$sql .= " WHERE $where";
		}

		$rs = $this->SelectLimit($sql, 1);
		if (!$rs || $mode == DB_AUTOQUERY_UPDATE && $rs->EOF) {
			// Table does not exist or udpate where clause matches no rows
			return false;
		}

		$rs->tableName = $table;
		$rs->sql = $sql;

		if ($mode == DB_AUTOQUERY_UPDATE) {
			$sql = $this->getUpdateSQL($rs, $fields_values, $forceUpdate);
		} else {
			$sql = $this->getInsertSQL($rs, $fields_values);
		}
		return $sql && $this->Execute($sql);
	}


	/**
	 * Generates an Update Query based on an existing recordset.
	 *
	 * $arrFields is an associative array of fields with the value
	 * that should be assigned.
	 *
	 * Note: This function should only be used on a recordset
	 *       that is run against a single table and sql should only
	 *       be a simple select stmt with no groupby/orderby/limit
	 * @author "Jonathan Younger" <jyounger@unilab.com>
	 *
	 * @param $rs
	 * @param $arrFields
	 * @param bool $forceUpdate
	 * @param bool $magic_quotes This param is not used since 5.21.0.
	 *                           It remains for backwards compatibility.
	 * @param null $force
	 *
	 * @return false|string
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	function GetUpdateSQL(&$rs, $arrFields, $forceUpdate=false, $magic_quotes=false, $force=null) {
		global $ADODB_INCLUDED_LIB;

		// ********************************************************
		// This is here to maintain compatibility
		// with older adodb versions. Sets force type to force nulls if $forcenulls is set.
		if (!isset($force)) {
			global $ADODB_FORCE_TYPE;
			$force = $ADODB_FORCE_TYPE;
		}
		// ********************************************************
		$adoHelpers = new \ADOdb\Resources\ADOHelpers($this);
	
		return $adoHelpers->_adodb_getupdatesql($this, $rs, $arrFields, $forceUpdate, $force);
	}

	/**
	 * Generates an Insert Query based on an existing recordset.
	 *
	 * $arrFields is an associative array of fields with the value
	 * that should be assigned.
	 *
	 * Note: This function should only be used on a recordset
	 *       that is run against a single table.
	 *
	 * @param $rs
	 * @param $arrFields
	 * @param bool $magic_quotes This param is not used since 5.21.0.
	 *                           It remains for backwards compatibility.
	 * @param null $force
	 *
	 * @return false|string
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	function GetInsertSQL(&$rs, $arrFields, $magic_quotes=false, $force=null) {
		global $ADODB_INCLUDED_LIB;
		if (!isset($force)) {
			global $ADODB_FORCE_TYPE;
			$force = $ADODB_FORCE_TYPE;
		}
		$adoHelpers = new \ADOdb\Resources\ADOHelpers($this);
		
		return $adoHelpers->_adodb_getinsertsql($this, $rs, $arrFields, $force);
	}

	/**
	 * Obtain a recordset object from a string table by querying the primary keys
	 *
	 * @param string $tableName The table to interrogate
	 * 
	 * @return object|null
	 */
	public function fetchResultByTableName(string $tableName): ?object
	{
		$result = null;

		$primaryKey = $this->metaPrimaryKeys($tableName);
		
		print "PK------------>";
		print_r($primaryKey);

		$keys = [];
		foreach ($primaryKey as $keyName) {
			$keys[] = sprintf('%s=NULL',$keyName);
		}
		$sql = sprintf(
			"SELECT * FROM %s WHERE %s",
			$tableName,
			implode(' AND ',$keys)
		);
		
		$result = $this->execute($sql);

		return $result;

	}


	/**
	 * Update a BLOB column, given a where clause.
	 *
	 * There are more sophisticated blob handling functions that we could have
	 * implemented, but all require a very complex API. Instead we have chosen
	 * something that is extremely simple to understand and use.
	 *
	 * Sample usage:
	 * - update a BLOB in field table.blob_col with value $blobValue, for a
	 *   record having primary key id=1
	 *   $conn->updateBlob('table', 'blob_col', $blobValue, 'id=1');
	 * - insert example:
	 *   $conn->execute('INSERT INTO table (id, blob_col) VALUES (1, null)');
	 *   $conn->updateBlob('table', 'blob_col', $blobValue, 'id=1');
	 *
	 * @param string $table
	 * @param string $column
	 * @param string $val      Filename containing blob data
	 * @param mixed  $where    {@see updateBlob()}
	 * @param string $blobtype supports 'BLOB' (default) and 'CLOB'
	 *
	 * @return bool success
	 */
	function updateBlob($table, $column, $val, $where, $blobtype='BLOB') {
		return $this->Execute("UPDATE $table SET $column=? WHERE $where",array($val)) != false;
	}

	/**
	 * Update a BLOB from a file.
	 *
	 * Usage example:
	 * $conn->updateBlobFile('table', 'blob_col', '/path/to/file', 'id=1');
	 *
	 * @param string $table
	 * @param string $column
	 * @param string $path     Filename containing blob data
	 * @param mixed  $where    {@see updateBlob()}
	 * @param string $blobtype supports 'BLOB' and 'CLOB'
	 *
	 * @return bool success
	 */
	function updateBlobFile($table, $column, $path, $where, $blobtype='BLOB') {
		$fd = fopen($path,'rb');
		if ($fd === false) {
			return false;
		}
		$val = fread($fd,filesize($path));
		fclose($fd);
		return $this->UpdateBlob($table,$column,$val,$where,$blobtype);
	}

	function BlobDecode($blob) {
		return $blob;
	}

	function BlobEncode($blob) {
		return $blob;
	}

	/**
	 * Retrieve the client connection's current character set.
	 *
	 * @return string|false The character set, or false if it can't be determined.
	 */
	function getCharSet() {
		return $this->charSet;
	}

	/**
	 * Sets the client-side character set.
	 *
	 * This is only supported for some databases.
	 * @see https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:setcharset
	 *
	 * @param string $charset The character set to switch to.
	 *
	 * @return bool True if the character set was changed successfully, false otherwise.
	 */
	function setCharSet($charset) {
		$this->charSet = $charset;
		return true;
	}

	/**
	 * Return string with a database specific IFNULL statement
	 *
	 * @param string $field
	 * @param string $ifNull
	 *
	 * @return void
	 */
	function IfNull( $field, $ifNull ) {
		return " CASE WHEN $field is null THEN $ifNull ELSE $field END ";
	}

	function LogSQL($enable=true) {
		include_once(ADODB_DIR.'/adodb-perf.inc.php');

		if ($enable) {
			$this->fnExecute = 'adodb_log_sql';
		} else {
			$this->fnExecute = false;
		}

		$old = $this->_logsql;
		$this->_logsql = $enable;
		if ($enable && !$old) {
			$this->_affected = false;
		}
		return $old;
	}

	/**
	 * Usage:
	 *	UpdateClob('TABLE', 'COLUMN', $var, 'ID=1', 'CLOB');
	 *
	 *	$conn->Execute('INSERT INTO clobtable (id, clobcol) VALUES (1, null)');
	 *	$conn->UpdateClob('clobtable','clobcol',$clob,'id=1');
	 */
	function UpdateClob($table,$column,$val,$where) {
		return $this->UpdateBlob($table,$column,$val,$where,'CLOB');
	}

	// not the fastest implementation - quick and dirty - jlim
	// for best performance, use the actual $rs->MetaType().
	function MetaType($t,$len=-1,$fieldobj=false) {

		if (!is_object($t) && !$fieldobj) {
			if ($this->debug) {
				ADOConnection::outp('Metatype no longer supports passing the field as a string');
			}
			return false;
		} else if (!is_object($t) && is_object($fieldobj)) {
			$t = $fieldobj;
		}

		return $this->metaObject->metaType($this, $t);

	}


	/**
	 *  Change the SQL connection locale to a specified locale.
	 *  This is used to get the date formats written depending on the client locale.
	 */
	function SetDateLocale($locale = 'En') {
		$this->locale = $locale;
		switch (strtoupper($locale))
		{
			case 'EN':
				$this->fmtDate="'Y-m-d'";
				$this->fmtTimeStamp = "'Y-m-d H:i:s'";
				break;

			case 'US':
				$this->fmtDate = "'m-d-Y'";
				$this->fmtTimeStamp = "'m-d-Y H:i:s'";
				break;

			case 'PT_BR':
			case 'NL':
			case 'FR':
			case 'RO':
			case 'IT':
				$this->fmtDate="'d-m-Y'";
				$this->fmtTimeStamp = "'d-m-Y H:i:s'";
				break;

			case 'GE':
				$this->fmtDate="'d.m.Y'";
				$this->fmtTimeStamp = "'d.m.Y H:i:s'";
				break;

			default:
				$this->fmtDate="'Y-m-d'";
				$this->fmtTimeStamp = "'Y-m-d H:i:s'";
				break;
		}
	}

	/**
	 * GetActiveRecordsClass Performs an 'ALL' query
	 *
	 * @param string $class This string represents the class of the current active record
	 * @param string $table Table used by the active record object
	 * @param string $whereOrderBy Where, order, by clauses
	 * @param array $bindarr
	 * @param array $primkeyArr
	 * @param array $extra Query extras: limit, offset...
	 * @param mixed $relations Associative array: table's foreign name, "hasMany", "belongsTo"
	 * @access public
	 * @return array|false
	 */
	function GetActiveRecordsClass(
			$class, $table,$whereOrderBy=false,$bindarr=false, $primkeyArr=false,
			$extra=array(),
			$relations=array())
	{
		global $_ADODB_ACTIVE_DBS;
		## reduce overhead of adodb.inc.php -- moved to adodb-active-record.inc.php
		## if adodb-active-recordx is loaded -- should be no issue as they will probably use Find()
		if (!isset($_ADODB_ACTIVE_DBS)) {
			include_once(ADODB_DIR.'/adodb-active-record.inc.php');
		}
		return adodb_GetActiveRecordsClass($this, $class, $table, $whereOrderBy, $bindarr, $primkeyArr, $extra, $relations);
	}

	function GetActiveRecords($table,$where=false,$bindarr=false,$primkeyArr=false) {
		return $this->GetActiveRecordsClass('ADODB_Active_Record', $table, $where, $bindarr, $primkeyArr);
	}

	/**
	 * Close Connection
	 */
	function Close() {
		$rez = $this->_close();
		$this->_queryID = false;
		$this->_connectionID = false;
		return $rez;
	}

	/**
	 * Begin a Transaction.
	 *
	 * Must be followed by CommitTrans() or RollbackTrans().
	 *
	 * @return bool true if succeeded or false if database does not support transactions
	 */
	function BeginTrans() {
		if ($this->debug) {
			ADOConnection::outp("BeginTrans: Transactions not supported for this driver");
		}
		return false;
	}

	/* set transaction mode */
	function SetTransactionMode( $transaction_mode ) {
		$transaction_mode = $this->MetaTransaction($transaction_mode, $this->dataProvider);
		$this->_transmode  = $transaction_mode;
	}
/*
https://msdn2.microsoft.com/en-US/ms173763.aspx
https://dev.mysql.com/doc/refman/5.0/en/innodb-transaction-isolation.html
https://www.postgresql.org/docs/8.1/interactive/sql-set-transaction.html
http://www.stanford.edu/dept/itss/docs/oracle/10g/server.101/b10759/statements_10005.htm
*/
	function MetaTransaction($mode,$db) {
		$mode = strtoupper($mode);
		$mode = str_replace('ISOLATION LEVEL ','',$mode);

		switch($mode) {

		case 'READ UNCOMMITTED':
			switch($db) {
			case 'oci8':
			case 'oracle':
				return 'ISOLATION LEVEL READ COMMITTED';
			default:
				return 'ISOLATION LEVEL READ UNCOMMITTED';
			}
			break;

		case 'READ COMMITTED':
				return 'ISOLATION LEVEL READ COMMITTED';
			break;

		case 'REPEATABLE READ':
			switch($db) {
			case 'oci8':
			case 'oracle':
				return 'ISOLATION LEVEL SERIALIZABLE';
			default:
				return 'ISOLATION LEVEL REPEATABLE READ';
			}
			break;

		case 'SERIALIZABLE':
				return 'ISOLATION LEVEL SERIALIZABLE';
			break;

		default:
			return $mode;
		}
	}

	/**
	 * Commits a transaction.
	 *
	 * If database does not support transactions, return true as data is
	 * always committed.
	 *
	 * @param bool $ok True to commit, false to rollback the transaction.
	 *
	 * @return bool true if successful
	 */
	function CommitTrans($ok=true) {
		return true;
	}


	/**
	 * Rolls back a transaction.
	 *
	 * If database does not support transactions, return false as rollbacks
	 * always fail.
	 *
	 * @return bool true if successful
	 */
	function RollbackTrans() {
		return false;
	}


	/**
	 * return the databases that the driver can connect to.
	 * Some databases will return an empty array.
	 *
	 * @return array|false an array of database names.
	 */
	function MetaDatabases() {
		return $this->metaObject->MetaDatabases($this);
	}

	/**
	 * List procedures or functions in an array.
	 * @param procedureNamePattern  a procedure name pattern; must match the procedure name as it is stored in the database
	 * @param catalog a catalog name; must match the catalog name as it is stored in the database;
	 * @param schemaPattern a schema name pattern;
	 *
	 * @return array of procedures on current database.
	 *
	 * Array(
	 *   [name_of_procedure] => Array(
	 *     [type] => PROCEDURE or FUNCTION
	 *     [catalog] => Catalog_name
	 *     [schema] => Schema_name
	 *     [remarks] => explanatory comment on the procedure
	 *   )
	 * )
	 */
	function MetaProcedures($procedureNamePattern = null, $catalog  = null, $schemaPattern  = null) {
		return false;
	}


	/**
	 * Returns an array of table names and/or views in the database.
	 *
	 * @param string|bool $ttype Can be either `TABLE`, `VIEW`, or false.
	 *   - If false, both views and tables are returned.
	 *   - `TABLE` (or `T`) returns only tables
	 *   - `VIEW` (or `V` returns only views
	 * @param string|bool $showSchema Prepends the schema/user to the table name,
	 *                                eg. USER.TABLE
	 * @param string|bool $mask Input mask - not supported by all drivers
	 *
	 * @return array|false Tables/Views for current database.
	 */
	function metaTables($ttype=false, $showSchema=false, $mask=false) {
		return $this->metaObject->metaTables($this, $ttype, $showSchema, $mask);
	}


	function _findschema(&$table,&$schema) {
		if (!$schema && ($at = strpos($table,'.')) !== false) {
			$schema = substr($table,0,$at);
			$table = substr($table,$at+1);
		}
	}

	/**
	 * List columns in a database as an array of ADOFieldObjects.
	 * See top of file for definition of object.
	 *
	 * @param $table	table name to query
	 * @param $normalize	makes table name case-insensitive (required by some databases)
	 * @schema is optional database schema to use - not supported by all databases.
	 *
	 * @return  array of ADOFieldObjects for current table.
	 */
	function MetaColumns($table,$normalize=true) : mixed {
		return $this->metaObject->metaColumns($this, $table, $normalize);
	}

	/**
	 * List indexes on a table as an array.
	 * @param table  table name to query
	 * @param primary true to only show primary keys. Not actually used for most databases
	 *
	 * @return array of indexes on current table. Each element represents an index, and is itself an associative array.
	 *
	 * Array(
	 *   [name_of_index] => Array(
	 *     [unique] => true or false
	 *     [columns] => Array(
	 *       [0] => firstname
	 *       [1] => lastname
	 *     )
	 *   )
	 * )
	 */
	public function MetaIndexes(string $table, bool $primary = false, ?string $owner = null) : mixed {
		return $this->metaObject->MetaIndexes($this, $table, $primary, $owner);
	}
	/**
	 * List columns names in a table as an array
	 * 
	 * @param string $table	     table name to query
	 * @param bool   $numIndexes return numeric keys
	 * @param bool   $useattnum  discarded in base class
	 *
	 * @return false|array of column names for current table.
	 */
	public function MetaColumnNames(
		string $table, 
		bool $numIndexes=false, 
		bool $useattnum=false
	) : mixed {
		
		return $this->metaObject->MetaColumnNames($this, $table, $numIndexes, $useattnum);
		
	}

	/**
	 * Concatenate strings.
	 *
	 * Different SQL databases used different methods to combine strings together.
	 * This function provides a wrapper.
	 *
	 * Usage: $db->Concat($str1,$str2);
	 *
	 * @param string $s Variable number of string parameters
	 *
	 * @return string concatenated string
	 */
	function Concat() {
		$arr = func_get_args();
		return implode($this->concat_operator, $arr);
	}


	/**
	 * Converts a date "d" to a string that the database can understand.
	 *
	 * @param mixed $d a date in Unix date time format.
	 *
	 * @return string date string in database date format
	 */
	function DBDate($d, $isfld=false) {
		if (empty($d) && $d !== 0) {
			return 'null';
		}
		if ($isfld) {
			return $d;
		}
		if (is_object($d)) {
			return $d->format($this->fmtDate);
		}

		if (is_string($d) && !is_numeric($d)) {
			if ($d === 'null') {
				return $d;
			}
			if (strncmp($d,"'",1) === 0) {
				$d = $this->_adodb_safedateq($d);
				return $d;
			}
			if ($this->isoDates) {
				return "'$d'";
			}
			$d = ADOConnection::UnixDate($d);
		}

		return date($this->fmtDate,$d);
	}

	/**
	 * Parse date string to prevent injection attack.
	 *
	 * @param string $s
	 *
	 * @return string
	 */
	function _adodb_safedate($s) {
		return str_replace(array("'", '\\'), '', $s);
	}

	/**
	 * Parse date string to prevent injection attack.
	 * Date string will have one quote at beginning e.g. '3434343'
	 *
	 * @param string $s
	 *
	 * @return string
	 */
	function _adodb_safedateq($s) {
		$len = strlen($s);
		if ($s[0] !== "'") {
			$s2 = "'".$s[0];
		} else {
			$s2 = "'";
		}
		for($i=1; $i<$len; $i++) {
			$ch = $s[$i];
			if ($ch === '\\') {
				$s2 .= "'";
				break;
			} elseif ($ch === "'") {
				$s2 .= $ch;
				break;
			}

			$s2 .= $ch;
		}

		return strlen($s2) == 0 ? 'null' : $s2;
	}


	function BindDate($d) {
		$d = $this->DBDate($d);
		if (strncmp($d,"'",1)) {
			return $d;
		}

		return substr($d,1,strlen($d)-2);
	}

	function BindTimeStamp($d) {
		$d = $this->DBTimeStamp($d);
		if (strncmp($d,"'",1)) {
			return $d;
		}

		return substr($d,1,strlen($d)-2);
	}


	/**
	 * Converts a timestamp "ts" to a string that the database can understand.
	 *
	 * @param int|object $ts A timestamp in Unix date time format.
	 *
	 * @return string $timestamp string in database timestamp format
	 */
	function DBTimeStamp($ts,$isfld=false) {
		if (empty($ts) && $ts !== 0) {
			return 'null';
		}
		if ($isfld) {
			return $ts;
		}
		if (is_object($ts)) {
			return $ts->format($this->fmtTimeStamp);
		}

		# strlen(14) allows YYYYMMDDHHMMSS format
		if (!is_string($ts) || (is_numeric($ts) && strlen($ts)<14)) {
			return date($this->fmtTimeStamp,$ts);
		}

		if ($ts === 'null') {
			return $ts;
		}
		if ($this->isoDates && strlen($ts) !== 14) {
			$ts = $this->_adodb_safedate($ts);
			return "'$ts'";
		}
		$ts = ADOConnection::UnixTimeStamp($ts);
		return date($this->fmtTimeStamp,$ts);
	}

	/**
	 * Also in ADORecordSet.
	 * @param mixed $v is a date string in YYYY-MM-DD format
	 *
	 * @return int|false Date in unix timestamp format, or 0 if before TIMESTAMP_FIRST_YEAR, or false if invalid date format
	 */
	static function UnixDate($v) {
		if (is_object($v)) {
		// odbtp support
		//( [year] => 2004 [month] => 9 [day] => 4 [hour] => 12 [minute] => 44 [second] => 8 [fraction] => 0 )
			return mktime($v->hour,$v->minute,$v->second,$v->month,$v->day, $v->year);
		}

		if (is_numeric($v) && strlen($v) !== 8) {
			return $v;
		}
		if (!preg_match( "|^([0-9]{4})[-/\.]?([0-9]{1,2})[-/\.]?([0-9]{1,2})|", $v, $rr)) {
			return false;
		}

		if ($rr[1] <= TIMESTAMP_FIRST_YEAR) {
			return 0;
		}

		// h-m-s-MM-DD-YY
		return mktime(0,0,0,$rr[2],$rr[3],$rr[1]);
	}


	/**
	 * Also in ADORecordSet.
	 * @param string|object $v is a timestamp string in YYYY-MM-DD HH-NN-SS format
	 *
	 * @return int|false Date in unix timestamp format, or 0 if before TIMESTAMP_FIRST_YEAR, or false if invalid date format
	 */
	static function UnixTimeStamp($v) {
		if (is_object($v)) {
		// odbtp support
		//( [year] => 2004 [month] => 9 [day] => 4 [hour] => 12 [minute] => 44 [second] => 8 [fraction] => 0 )
			return mktime($v->hour,$v->minute,$v->second,$v->month,$v->day, $v->year);
		}

		if (!preg_match(
			"|^([0-9]{4})[-/\.]?([0-9]{1,2})[-/\.]?([0-9]{1,2})[ ,-]*(([0-9]{1,2}):?([0-9]{1,2}):?([0-9\.]{1,4}))?|",
			($v), $rr)) return false;

		if ($rr[1] <= TIMESTAMP_FIRST_YEAR && $rr[2]<= 1) {
			return 0;
		}

		// h-m-s-MM-DD-YY
		if (!isset($rr[5])) {
			return mktime(0,0,0,$rr[2],$rr[3],$rr[1]);
		}
		return mktime($rr[5],$rr[6],$rr[7],$rr[2],$rr[3],$rr[1]);
	}

	/**
	 * Format database date based on user defined format.
	 *
	 * Also in ADORecordSet.
	 *
	 * @param mixed  $v    Date in YYYY-MM-DD format, returned by database
	 * @param string $fmt  Format to apply, using date()
	 * @param bool   $gmt
	 *
	 * @return string Formatted date
	 */
	function UserDate($v,$fmt='Y-m-d',$gmt=false) {
		$tt = $this->UnixDate($v);

		// $tt == -1 if pre TIMESTAMP_FIRST_YEAR
		if (($tt === false || $tt == -1) && $v != false) {
			return $v;
		} else if ($tt == 0) {
			return $this->emptyDate;
		} else if ($tt == -1) {
			// pre-TIMESTAMP_FIRST_YEAR
		}

		return ($gmt) ? gmdate($fmt,$tt) : date($fmt,$tt);
	}

	/**
	 * Format timestamp based on user defined format.
	 *
	 * @param mixed  $v    Date in YYYY-MM-DD hh:mm:ss format
	 * @param string $fmt  Format to apply, using date()
	 * @param bool   $gmt
	 *
	 * @return string Formatted timestamp
	 */
	function UserTimeStamp($v,$fmt='Y-m-d H:i:s',$gmt=false) {
		if (!isset($v)) {
			return $this->emptyTimeStamp;
		}
		# strlen(14) allows YYYYMMDDHHMMSS format
		if (is_numeric($v) && strlen($v)<14) {
			return ($gmt) ? gmdate($fmt,$v) : date($fmt,$v);
		}
		$tt = $this->UnixTimeStamp($v);
		// $tt == -1 if pre TIMESTAMP_FIRST_YEAR
		if (($tt === false || $tt == -1) && $v != false) {
			return $v;
		}
		if ($tt == 0) {
			return $this->emptyTimeStamp;
		}
		return ($gmt) ? gmdate($fmt,$tt) : date($fmt,$tt);
	}

	/**
	 * Alias for addQ()
	 * @param string $s
	 * @param bool [$magic_quotes]
	 * @return mixed
	 *
	 * @deprecated 5.21.0
	 * @noinspection PhpUnusedParameterInspection
	 */
	function escape($s,$magic_quotes=false) {
		return $this->addQ($s);
	}

	/**
	 * Quotes a string, without prefixing nor appending quotes.
	 *
	 * @param string $s            The string to quote
	 * @param bool   $magic_quotes This param is not used since 5.21.0.
	 *                             It remains for backwards compatibility.
	 *
	 * @return string Quoted string
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	function addQ($s, $magic_quotes=false) {
		if ($this->replaceQuote[0] == '\\') {
			$s = str_replace(
				array('\\', "\0"),
				array('\\\\', "\\\0"),
				$s
			);
		}
		return str_replace("'", $this->replaceQuote, $s);
	}

	/**
	 * Correctly quotes a string so that all strings are escaped.
	 * We prefix and append to the string single-quotes.
	 * An example is  $db->qstr("Don't bother");
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:qstr
	 *
	 * @param string $s            The string to quote
	 * @param bool   $magic_quotes This param is not used since 5.21.0.
	 *                             It remains for backwards compatibility.
	 *
	 * @return string Quoted string to be sent back to database
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	function qStr($s, $magic_quotes=false) {
		return  "'" . $this->addQ($s) . "'";
	}


	/**
	 * Execute query with pagination.
	 *
	 * Will select the supplied $page number from a recordset, divided in
	 * pages of $nrows rows each. It also saves two boolean values saying
	 * if the given page is the first and/or last one of the recordset.
	 *
	 * @param string     $sql        Query to execute
	 * @param int        $nrows      Number of rows per page
	 * @param int        $page       Page number to retrieve (1-based)
	 * @param array|bool $inputarr   Array of bind variables
	 * @param int        $secs2cache Time-to-live of the cache (in seconds), 0 to force query execution
	 *
	 * @return ADORecordSet|bool the recordset ($rs->databaseType == 'array')
	 *
	 * @author Iván Oliva
	 */
	function PageExecute($sql, $nrows, $page, $inputarr=false, $secs2cache=0) {
		
		$adoHelpers = new \ADOdb\Resources\ADOHelpers;

		if ($this->pageExecuteCountRows) {
			$rs = $adoHelpers->_adodb_pageexecute_all_rows($this, $sql, $nrows, $page, $inputarr, $secs2cache);
		} else {
			$rs = $adoHelpers->_adodb_pageexecute_no_last_page($this, $sql, $nrows, $page, $inputarr, $secs2cache);
		}
		return $rs;
	}


	/**
	 * Will select the supplied $page number from a recordset, given that it is paginated in pages of
	 * $nrows rows per page. It also saves two boolean values saying if the given page is the first
	 * and/or last one of the recordset. Added by Iván Oliva to provide recordset pagination.
	 *
	 * @param int $secs2cache	seconds to cache data, set to 0 to force query
	 * @param string $sql
	 * @param int $nrows		is the number of rows per page to get
	 * @param int $page		is the page number to get (1-based)
	 * @param mixed[]|bool $inputarr	array of bind variables
	 * @return mixed	the recordset ($rs->databaseType == 'array')
	 */
	function CachePageExecute($secs2cache, $sql, $nrows, $page,$inputarr=false) {
		/*switch($this->dataProvider) {
		case 'postgres':
		case 'mysql':
			break;
		default: $secs2cache = 0; break;
		}*/
		return $this->PageExecute($sql,$nrows,$page,$inputarr,$secs2cache);
	}

	/**
	 * Returns the maximum size of a MetaType C field. If the method
	 * is not defined in the driver returns ADODB_STRINGMAX_NOTSET
	 *
	 * @return int
	 */
	function charMax() {
		return ADODB_STRINGMAX_NOTSET;
	}

	/**
	 * Returns the maximum size of a MetaType X field. If the method
	 * is not defined in the driver returns ADODB_STRINGMAX_NOTSET
	 *
	 * @return int
	 */
	function textMax() {
		return ADODB_STRINGMAX_NOTSET;
	}

	/**
	 * Returns a substring of a varchar type field
	 *
	 * Some databases have variations of the parameters, which is why
	 * we have an ADOdb function for it
	 *
	 * @param	string	$fld	The field to sub-string
	 * @param	int		$start	The start point
	 * @param	int		$length	An optional length
	 *
	 * @return string	The SQL text
	 */
	function substr($fld,$start,$length=0) {
		$text = "{$this->substr}($fld,$start";
		if ($length > 0)
			$text .= ",$length";
		$text .= ')';
		return $text;
	}

	/*
	 * Formats the date into Month only format MM with leading zeroes
	 *
	 * @param	string		$fld	The name of the date to format
	 *
	 * @return	string				The SQL text
	 */
	function month($fld) {
		return $this->sqlDate('m',$fld);
	}

	/*
	 * Formats the date into Day only format DD with leading zeroes
	 *
	 * @param	string		$fld	The name of the date to format
	 * @return	string		The SQL text
	 */
	function day($fld) {
		return $this->sqlDate('d',$fld);
	}

	/*
	 * Formats the date into year only format YYYY
	 *
	 * @param	string		$fld The name of the date to format
	 *
	 * @return	string		The SQL text
	 */
	function year($fld) {
		return $this->sqlDate('Y',$fld);
	}

	/**
	 * Get the last error recorded by PHP and clear the message.
	 *
	 * By clearing the message, it becomes possible to detect whether a new error
	 * has occurred, even when it is the same error as before being repeated.
	 *
	 * @return mixed[]|null Array if an error has previously occurred. Null otherwise.
	 */
	protected function resetLastError() {
		$error = error_get_last();

		if (is_array($error)) {
			$error['message'] = '';
		}

		return $error;
	}

	/**
	 * Compare a previously stored error message with the last error recorded by PHP
	 * to determine whether a new error has occurred.
	 *
	 * @param mixed[]|null $old Optional. Previously stored return value of error_get_last().
	 *
	 * @return string The error message if a new error has occurred
	 *                or an empty string if no (new) errors have occurred..
	 */
	protected function getChangedErrorMsg($old = null) {
		$new = error_get_last();

		if (is_null($new)) {
			// No error has occurred yet at all.
			return '';
		}

		if (is_null($old)) {
			// First error recorded.
			return $new['message'];
		}

		$changed = false;
		foreach($new as $key => $value) {
			if ($new[$key] !== $old[$key]) {
				$changed = true;
				break;
			}
		}

		if ($changed === true) {
			return $new['message'];
		}

		return '';
	}

	/**
	 * Returns SQL to obtain the length of data in a column, including
	 * CHAR fields
	 *
	 * @param string $fieldName The field length to measure
 	 * 
	 * @return string
	 */
	public function length(string $fieldName): string
	{
		return sprintf('LENGTH(TRIM(%s))', $fieldName);
	}

	/**
	 * Perform a stack-crawl and pretty print it.
	 *
	 * @param bool  $printOrArr Pass in a boolean to indicate print, or an $exception->trace array (assumes that print is true then).
	 * @param int   $levels     Number of levels to display
	 * @param mixed $ishtml
	 *
	 * @return string
	 */
	function adodb_backtrace($printOrArr=true,$levels=9999,$ishtml=null) {
		
		$errorClass = new \ADOdb\Resources\ErrorHandling;
	
		return $errorClass->_adodb_backtrace($printOrArr,$levels,0,$ishtml);
	}

} 