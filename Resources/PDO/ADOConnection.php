<?php
/**
 *  
 */
namespace ADOdb\Resources\PDO;

use ADOdb\Resources\PDO\ADOPDOStatement;

/*
 * Class PDO
 */
class ADOConnection extends \ADOdb\Resources\ADOConnection {

	const BIND_USE_QUESTION_MARKS = 0;
	const BIND_USE_NAMED_PARAMETERS = 1;
	const BIND_USE_BOTH = 2;
	
	var $databaseType = "pdo";
	var $dataProvider = "pdo";

	var $fmtTimeStamp = "'Y-m-d, h:i:sA'";
	var $replaceQuote = "''"; // string to use to replace quotes
	var $hasAffectedRows = true;
	var $_bindInputArray = true;

	/*
	* Sequence management statements
	*/
	public $_genIDSQL 		 = '';
	public $_genSeqSQL 	 	 = 'CREATE TABLE %s (id integer)';
	public $_genSeqCountSQL  = '';
	public $_genSeq2SQL 	 = '';
	public $_dropSeqSQL 	 = 'DROP TABLE %s';

	

	var $_autocommit = true;
	var $_lastAffectedRows = 0;

	var $_errormsg = false;
	var $_errorno = false;

	var $stmt = false;

	public $_stmt;
	
	/**
	* Set which style is used to bind parameters
	*
	* BIND_USE_QUESTION_MARKS   = Use only question marks
	* BIND_USE_NAMED_PARAMETERS = Use only named parameters
	* BIND_USE_BOTH             = Use both question marks and named parameters (Default)
	* @var int $bindParameterStyle
	*/
	public int $bindParameterStyle = self::BIND_USE_BOTH;

	/**
	 * Holds the current database name
	 * @var string $databaseName
	 */
	protected string $databaseName = '';

	/**
	* Describe parameters passed directly to the PDO driver
	*
	* @example $db->pdoParameters = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
	* @var array $pdoParameters
	*/
	public array $pdoParameters = array();


	public string $dictionaryProvider = '';

	
	var $sysDate = "'?'";
	var $sysTimeStamp = "'?'";

	/**
	 * What PDO connection is being made
	 *
	 * @var string
	 */
	protected string $pdoDriver = '';

	function _init($parentDriver)
	{
		$parentDriver->_bindInputArray = true;
		#$parentDriver->_connectionID->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
	}

	function ServerInfo()
	{
		return ADOConnection::ServerInfo();
	}
	
	
	/**
	 * Connect to a database.
	 *
	 * @param string|null $argDSN 		 	The host to connect to.
	 * @param string|null $argUsername 	 	The username to connect as.
	 * @param string|null $argPassword 		The password to connect with.
	 * @param string|null $argDatabasename 	The name of the database to start in when connected.
	 * @param bool $persist (Optional) 		Whether or not to use a persistent connection.
	 *
	 * @return bool|null True if connected successfully, false if connection failed, or null if the mysqli extension
	 * isn't currently loaded.
	 */
	public function _connect($argDSN, $argUsername, $argPassword, $argDatabasename, $persist=false)
	{
		

		//$driverClassArray = explode('\\',get_class($this));
		/*
		* We have already instantiated the correct driver class using pdo\<driver> so the array looks like
		*
		Array
		(
			[0] => ADOdb
			[1] => PDO
			[2] => MySQL
		)
		* So its easy to determine that the driver we are using is mysql
		*/
				
		//$driverClass      = strtolower(array_pop($driverClassArray));

		$driverClass = $this->pdoDriver;
	
		$dsnSplit = explode(':', $argDSN ?? '', 2);  
		if (count($dsnSplit) > 1) 
		{
		
			$this->dsnType = $dsnSplit[0];
			if (strcmp($this->dsnType,$driverClass) <> 0)
			{
				$this->outp_throw(
					'If a database type is specified, it must match the previously defined driver',
					'CONNECT',
					$argDSN
				);
			}
		}
		else
		{
			/*
			* Move the driver info back to its traditional position
			*/
			$this->dsnType = $driverClass;
			$argDSN 	   = $driverClass . ':' . $argDSN;
		}

		if ($argDatabasename) {
			$this->databaseName = $argDatabasename;

			switch($this->dsnType){
				case 'sqlsrv':
					$argDSN .= ';database='.$argDatabasename;
					break;
				case 'mssql':
				case 'mysql':
				case 'oci':
				case 'pgsql':
				case 'sqlite':
				case 'firebird':
				default:
					$argDSN .= ';dbname='.$argDatabasename;
			}
		}
		elseif (!$this->databaseName)
			$this->databaseName = $this->getDatabasenameFromDsn($argDSN);

		/*
		* Configure for persistent connection if required,
		* by adding the the pdo parameter into any provided
		* ones
		*/
		if ($persist) {
			$this->pdoParameters[\PDO::ATTR_PERSISTENT] = true;
		}

		
		/*
		* Execute a connection
		*/
		
		try {
			$this->_connectionID = new \PDO($argDSN, $argUsername, $argPassword, $this->pdoParameters);
		} catch (\Exception $e) {
			$this->_connectionID = false;
			$this->_errorno = -1;
			//var_dump($e);
			$this->_errormsg = 'Connection attempt failed: '.$e->getMessage();
			return false;
		}

		if ($this->_connectionID) {
			switch(ADODB_ASSOC_CASE){
				case ADODB_ASSOC_CASE_LOWER:
					$m = \PDO::CASE_LOWER;
					break;
				case ADODB_ASSOC_CASE_UPPER:
					$m = \PDO::CASE_UPPER;
					break;
				default:
				case ADODB_ASSOC_CASE_NATIVE:
					$m = \PDO::CASE_NATURAL;
					break;
			}

			//$this->_connectionID->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_SILENT );
			$this->_connectionID->setAttribute(\PDO::ATTR_CASE,$m);

			// Now merge in any provided attributes for PDO
			foreach ($this->connectionParameters as $options) {
				foreach($options as $k=>$v) {
					if ($this->debug) {
						ADOconnection::outp('Setting attribute: ' . $k . ' to ' . $v);
					}
					$this->_connectionID->setAttribute($k,$v);
				}
			}
			return true;
		}
		
		return false;
	}

	/**
	 * Connect to a database with a persistent connection.
	 *
	 * @param string|null $argDSN 		The connection DSN
	 * @param string|null $argUsername  The username to connect as.
	 * @param string|null $argPassword  The password to connect with.
	 * @param string|null $argDatabasename The name of the database to start in when connected.
	 *
	 * @return bool|null True if connected successfully, false if connection failed, or null if the mysqli extension
	 * isn't currently loaded.
	 */
	public function _pconnect($argDSN, $argUsername, $argPassword, $argDatabasename)
	{
		return $this->_connect($argDSN, $argUsername, $argPassword, $argDatabasename, true);
	}

	/*------------------------------------------------------------------------------*/

	
	/**
	 * Self-documenting version of parameter().
	 *
	 * @param $stmt
	 * @param &$var
	 * @param $name
	 * @param int $maxLen
	 * @param bool $type
	 *
	 * @return bool
	 */
	public function inParameter(&$stmt,&$var,$name,$maxLen=4000,$type=false)
	{
		$obj = $stmt[1];
		if ($type) {
			$obj->bindParam($name, $var, $type, $maxLen);
		}
		else {
			$obj->bindParam($name, $var);
		}
	}

	/**
	 * Returns a database specific error message.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:errormsg
	 *
	 * @return string The last error message.
	 */
	public function errorMsg()
	{
		if ($this->_errormsg !== false) {
			return $this->_errormsg;
		}
		if (!empty($this->_stmt)) {
			$arr = $this->_stmt->errorInfo();
		}
		else if (!empty($this->_connectionID)) {
			$arr = $this->_connectionID->errorInfo();
		}
		else {
			return 'No Connection Established';
		}

		if ($arr) {
			if (sizeof($arr)<2) {
				return '';
			}
			if ((int)$arr[0]) {
				return $arr[2];
			}
			else {
				return '';
			}
		}
		else {
			return '-1';
		}
	}


	/**
	 * Returns the last error number from previous database operation.
	 *
	 * @return int The last error number.
	 */
	public function errorNo()
	{
		if ($this->_errorno !== false) {
			return $this->_errorno;
		}
		if (!empty($this->_stmt)) {
			$err = $this->_stmt->errorCode();
		}
		else if (!empty($this->_connectionID)) {
			$arr = $this->_connectionID->errorInfo();
			if (isset($arr[0])) {
				$err = $arr[0];
			}
			else {
				$err = -1;
			}
		} else {
			return 0;
		}

		if ($err == '00000') {
			return 0; // allows empty check
		}
		return $err;
	}

	/**
	 * @deprecated - replace with setConnectionParameter()
     * @param bool $auto_commit
     * @return void
     */
    public function setAutoCommit($auto_commit)
    {
		$this->_connectionID->setAttribute(\PDO::ATTR_AUTOCOMMIT, $auto_commit);
    }


	/**
	 * Begin a Transaction.
	 *
	 * Must be followed by CommitTrans() or RollbackTrans().
	 *
	 * @return bool true if succeeded or false if database does not support transactions
	 */
	public function beginTrans()
	{

		if (!$this->hasTransactions) {
			return false;
		}
		if ($this->transOff) {
			return true;
		}
		$this->transCnt += 1;
		$this->_autocommit = false;
		$this->setAutoCommit(false);

		return $this->_connectionID->beginTransaction();
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
	public function commitTrans($ok=true)
	{

		if (!$this->hasTransactions) {
			return false;
		}
		if ($this->transOff) {
			return true;
		}
		if (!$ok) {
			return $this->rollbackTrans();
		}
		if ($this->transCnt) {
			$this->transCnt -= 1;
		}
		$this->_autocommit = true;

		$ret = $this->_connectionID->commit();
		$this->SetAutoCommit(true);
		return $ret;
	}

	
	/**
	 * Rolls back a transaction.
	 *
	 * If database does not support transactions, return false as rollbacks
	 * always fail.
	 *
	 * @return bool true if successful
	 */
	public function rollbackTrans()
	{
		if (!$this->hasTransactions) {
			return false;
		}
		if ($this->transOff) {
			return true;
		}
		if ($this->transCnt) {
			$this->transCnt -= 1;
		}
		$this->_autocommit = true;

		$ret = $this->_connectionID->rollback();
		$this->SetAutoCommit(true);
		return $ret;
	}

	/**
	 * Prepares an SQL statement and returns a handle to use.
	 * This is not used by bound parameters anymore
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:prepare
	 * @todo update this function to handle prepared statements correctly
	 *
	 * @param string $sql The SQL to prepare.
	 *
	 * @return string The original SQL that was provided.
	 */
	public function prepare($sql)
	{
		$this->_stmt = $this->_connectionID->prepare($sql);
		if ($this->_stmt) {
			return array($sql,$this->_stmt);
		}

		return false;
	}

	/**
	 * Undocument feature that prepares an SQL statement and returns a handle to use.
	 * Only exists in the PDO driver and loads the ADOPDOStatement object
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:prepare
	 * @todo update this function to handle prepared statements correctly
	 *
	 * @param string $sql The SQL to prepare.
	 *
	 * @return string The original SQL that was provided.
	 */
	public function prepareStmt($sql)
	{
		$stmt = $this->_connectionID->prepare($sql);
		if (!$stmt) {
			return false;
		}
		$obj = new ADOPDOStatement($stmt,$this);
		return $obj;
	}

	/**
	* Return the query id.
	*
	* @param string|array $sql
	* @param array $inputarr
	*
	* @return bool|pdo_result
	*/
	public function _query($sql,$inputarr=false)
	{
		$ok = false;
		if (is_array($sql)) {
			$stmt = $sql[1];
		} else {
			$stmt = $this->_connectionID->prepare($sql);
		}

		if ($stmt) {
			
			if ($inputarr) {

				$inputarr = $this->conformToBindParameterStyle($stmt->queryString, $inputarr);
				
				/*
				* inputarr must be numeric
				*/
				$inputarr = array_values($inputarr);
				$ok = $stmt->execute($inputarr);
			}
			else {
				$ok = $stmt->execute();
			}
		}


		$this->_errormsg = false;
		$this->_errorno = false;

		if ($ok) {
			$this->_stmt = $stmt;
			return $stmt;
		}

		if ($stmt) {

			$arr = $stmt->errorinfo();
			if ((int)$arr[1]) {
				$this->_errormsg = $arr[2];
				$this->_errorno = $arr[1];
			}

		} else {
			$this->_errormsg = false;
			$this->_errorno = false;
		}
		return false;
	}
	
	
	/**
	 * Make bind parameters conform to settings.
	 *
	 * @param string $sql
	 * @param array $inputarr
	*
	* @return array
	*/
	private function conformToBindParameterStyle(string $sql, array $inputarr) : array
	{
		switch ($this->bindParameterStyle)
		{
		case self::BIND_USE_QUESTION_MARKS:
			$inputarr = array_values($inputarr);
			break;

		case self::BIND_USE_NAMED_PARAMETERS:
			break;

		default:
		case self::BIND_USE_BOTH:
			// inputarr must be numeric if SQL contains a question mark
			if ($this->containsQuestionMarkPlaceholder($sql)) {
				$inputarr = array_values($inputarr);

				if ($this->debug) {
					ADOconnection::outp('improve the performance of this query by setting the bindParameterStyle to BIND_USE_QUESTION_MARKS');
				}
			}
			break;
		}

		return $inputarr;
	}

	/**
	 * Checks for the inclusion of a question mark placeholder.
	 *
	 * @param string $sql   SQL string
	 * @return boolean      Returns true if a question mark placeholder is included
	 */
	private function containsQuestionMarkPlaceholder(string $sql) : bool
	{
		$pattern = '/(.\?(:?.|$))/';
		if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				if ($match[1] !== '`?`' && strpos($match[1], '??') === false) {
					return true;
				}
			}
		}
		return false;
	}
	

	/**
	 * Close the database connection.
	 *
	 * @return void
	 */
	public function _close()
	{
		$this->_stmt = false;
	
	}

	/**
	 * Returns how many rows were effected by the most recently executed SQL statement.
	 * Only works for INSERT, UPDATE and DELETE queries.
	 *
	 * @return int The number of rows affected.
	 */
	public function _affectedrows()
	{
		return ($this->_stmt) ? $this->_stmt->rowCount() : 0;
	}

	/**
	 * Return the AUTO_INCREMENT id of the last row that has been inserted or updated in a table.
	 *
	 * @inheritDoc
	 */
	protected function _insertID($table = '', $column = '')
	{
		return ($this->_connectionID) ? $this->_connectionID->lastInsertId() : 0;
	}

	/**
	 * Quotes a string to be sent to the database.
	 *
	 * If we have an active connection, delegates quoting to the underlying
	 * PDO object PDO::quote(). Otherwise, delegate to parent method
	 *
	 * @param string  $s           The string to quote
	 * @param bool   $magic_quotes This param is not used since 5.21.0.
	 *                             It remains for backwards compatibility.
	 *
	 * @return string Quoted string
	 */
	public function qStr($s, $magic_quotes = false)
	{
		if ($this->_connectionID) 
		{
			return $this->_connectionID->quote($s);
		}
		return parent::qStr($s,$magic_quotes);
	}

	/**
	  * Gets the database name from the DSN
	  *
	  * @param	string	$dsnString
	  *
	  * @return string
	  */
	protected function getDatabasenameFromDsn(string $dsnString): string{

		$dsnArray = preg_split('/[;=]+/',$dsnString);
		$dbIndex  = array_search('database',$dsnArray);

		return $dsnArray[$dbIndex + 1];
	}

}