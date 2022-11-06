<?php
/**
 * ADOdb base PDO driver
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @package ADOdb
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v2.1 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-2.1-or-later
 *
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
 */

// security - hide paths
if (!defined('ADODB_DIR')) die();


/*
enum pdo_param_type {
PDO::PARAM_NULL, 0

/* int as in long (the php native int type).
 * If you mark a column as an int, PDO expects get_col to return
 * a pointer to a long
PDO::PARAM_INT, 1

/* get_col ptr should point to start of the string buffer
PDO::PARAM_STR, 2

/* get_col: when len is 0 ptr should point to a php_stream *,
 * otherwise it should behave like a string. Indicate a NULL field
 * value by setting the ptr to NULL
PDO::PARAM_LOB, 3

/* get_col: will expect the ptr to point to a new PDOStatement object handle,
 * but this isn't wired up yet
PDO::PARAM_STMT, 4 /* hierarchical result set

/* get_col ptr should point to a zend_bool
PDO::PARAM_BOOL, 5


/* magic flag to denote a parameter as being input/output
PDO::PARAM_INPUT_OUTPUT = 0x80000000
};
*/

function adodb_pdo_type($t)
{
	switch($t) {
	case 2: return 'VARCHAR';
	case 3: return 'BLOB';
	default: return 'NUMERIC';
	}
}

/*----------------------------------------------------------------------------*/

class ADODB_pdo extends ADOConnection 
{
	
	const BIND_USE_QUESTION_MARKS = 0;
	const BIND_USE_NAMED_PARAMETERS = 1;
	const BIND_USE_BOTH = 2;
	
	var $databaseType = "pdo";
	var $dataProvider = "pdo";
	var $fmtDate = "'Y-m-d'";
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
	
	/*
	* Set which style is used to bind parameters
	*
	* BIND_USE_QUESTION_MARKS   = Use only question marks
	* BIND_USE_NAMED_PARAMETERS = Use only named parameters
	* BIND_USE_BOTH             = Use both question marks and named parameters (Default)
	*/
	public $bindParameterStyle = self::BIND_USE_BOTH;

	/*
	 * Holds the current database name
	 */
	protected $databaseName = '';

	/*
	* Describe parameters passed directly to the PDO driver
	*
	* @example $db->pdoParameters = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
	*/
	public $pdoParameters = array();

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
		
		$driverClassArray = explode('_',get_class($this));
		/*
		* We have already instantiated the correct driver class using pdo\<driver> so the array looks like
		*
		Array
		(
			[0] => ADODB
			[1] => pdo
			[2] => mysql
		)
		* So its easy to determine that the driver we are using is mysql
		*/
		$driverClass      = array_pop($driverClassArray);
	
		$at = strpos($argDSN,':');
		if ($at > 0)
		{
		
			$this->dsnType = substr($argDSN,0,$at);
			if (strcmp($this->dsnType,$driverClass) <> 0)
				die('If a database type is specified, it must match the previously defined driver');
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
		} catch (Exception $e) {
			$this->_connectionID = false;
			$this->_errorno = -1;
			//var_dump($e);
			$this->_errormsg = 'Connection attempt failed: '.$e->getMessage();
			return false;
		}

		if ($this->_connectionID) {
			switch(ADODB_ASSOC_CASE){
				case ADODB_ASSOC_CASE_LOWER:
					$m = PDO::CASE_LOWER;
					break;
				case ADODB_ASSOC_CASE_UPPER:
					$m = PDO::CASE_UPPER;
					break;
				default:
				case ADODB_ASSOC_CASE_NATIVE:
					$m = PDO::CASE_NATURAL;
					break;
			}

			//$this->_connectionID->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_SILENT );
			$this->_connectionID->setAttribute(PDO::ATTR_CASE,$m);

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
			if ((integer)$arr[0]) {
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
		$this->_connectionID->setAttribute(PDO::ATTR_AUTOCOMMIT, $auto_commit);
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
	* @return bool|mysqli_result
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
			if ((integer)$arr[1]) {
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
	private function conformToBindParameterStyle($sql, $inputarr)
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
	private function containsQuestionMarkPlaceholder($sql)
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
	 * Returns the server information
	 * 
	 * @return array()
	 */
	public function serverInfo() 
	{

		global $ADODB_FETCH_MODE;
		static $arr = false;
		if (is_array($arr))
			return $arr;
		if ($this->fetchMode === false) {
			$savem = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		} elseif ($this->fetchMode >=0 && $this->fetchMode <=2) {
			$savem = $this->fetchMode;
		} else
			$savem = $this->SetFetchMode(ADODB_FETCH_NUM);

		//$arr = $this->_connectionID->getAttribute(constant("PDO::ATTR_SERVER_INFO"));
		
		$ADODB_FETCH_MODE = $savem;
		return $arr;
	}

	/**
	  * Gets the database name from the DSN
	  *
	  * @param	string	$dsnString
	  *
	  * @return string
	  */
	  protected function getDatabasenameFromDsn($dsnString){

		$dsnArray = preg_split('/[;=]+/',$dsnString);
		$dbIndex  = array_search('database',$dsnArray);

		return $dsnArray[$dbIndex + 1];
	}


}

/**
 * Undocumented class to support the PDO preparestmt method
 */
class ADOPDOStatement {

	var $databaseType = "pdo";
	var $dataProvider = "pdo";
	var $_stmt;
	var $_connectionID;

	function __construct($stmt,$connection)
	{
		$this->_stmt = $stmt;
		$this->_connectionID = $connection;
	}

	function Execute($inputArr=false)
	{
		$savestmt = $this->_connectionID->_stmt;
		$rs = $this->_connectionID->Execute(array(false,$this->_stmt),$inputArr);
		$this->_connectionID->_stmt = $savestmt;
		return $rs;
	}

	function InParameter(&$var,$name,$maxLen=4000,$type=false)
	{

		if ($type) {
			$this->_stmt->bindParam($name,$var,$type,$maxLen);
		}
		else {
			$this->_stmt->bindParam($name, $var);
		}
	}

	function Affected_Rows()
	{
		return ($this->_stmt) ? $this->_stmt->rowCount() : 0;
	}

	function ErrorMsg()
	{
		if ($this->_stmt) {
			$arr = $this->_stmt->errorInfo();
		}
		else {
			$arr = $this->_connectionID->errorInfo();
		}

		if (is_array($arr)) {
			if ((integer) $arr[0] && isset($arr[2])) {
				return $arr[2];
			}
			else {
				return '';
			}
		} else {
			return '-1';
		}
	}

	function NumCols()
	{
		return ($this->_stmt) ? $this->_stmt->columnCount() : 0;
	}

	function ErrorNo()
	{
		if ($this->_stmt) {
			return $this->_stmt->errorCode();
		}
		else {
			return $this->_connectionID->errorInfo();
		}
	}

	
}

/*--------------------------------------------------------------------------------------
	Class Name: Recordset
--------------------------------------------------------------------------------------*/

class ADORecordSet_pdo extends ADORecordSet {

	var $bind = false;
	var $databaseType = "pdo";
	var $dataProvider = "pdo";

	function __construct($id,$mode=false)
	{
		if ($mode === false) {
			global $ADODB_FETCH_MODE;
			$mode = $ADODB_FETCH_MODE;
		}
		$this->adodbFetchMode = $mode;
		switch($mode) {
		case ADODB_FETCH_NUM: $mode = PDO::FETCH_NUM; break;
		case ADODB_FETCH_ASSOC:  $mode = PDO::FETCH_ASSOC; break;

		case ADODB_FETCH_BOTH:
		default: $mode = PDO::FETCH_BOTH; break;
		}
		$this->fetchMode = $mode;

		$this->_queryID = $id;
		parent::__construct($id);
	}

	
	public function _initrs()
	{
		global $ADODB_COUNTRECS;

		$this->_numOfRows = ($ADODB_COUNTRECS) ? @$this->_queryID->rowCount() : -1;
		if (!$this->_numOfRows) {
			$this->_numOfRows = -1;
		}
		$this->_numOfFields = $this->_queryID->columnCount();
	}

	/**
	 * Returns raw, database specific information about a field.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:recordset:fetchfield
	 *
	 * @param int $fieldOffset (Optional) The field number to get information for.
	 *
	 * @return ADOFieldObject|bool
	 */
	public function fetchField($fieldOffset = -1)
	{
		$off=$fieldOffset+1; // offsets begin at 1

		$o= new ADOFieldObject();
		$arr = @$this->_queryID->getColumnMeta($fieldOffset);
		if (!$arr) {
			$o->name = 'bad getColumnMeta()';
			$o->max_length = -1;
			$o->type = 'VARCHAR';
			$o->precision = 0;
	#		$false = false;
			return $o;
		}
		//adodb_pr($arr);
		$o->name = $arr['name'];
		if (isset($arr['sqlsrv:decl_type']) && $arr['sqlsrv:decl_type'] <> "null")
		{
		    /*
		    * If the database is SQL server, use the native built-ins
		    */
		    $o->type = $arr['sqlsrv:decl_type'];
		}
		elseif (isset($arr['native_type']) && $arr['native_type'] <> "null")
		{
		    $o->type = $arr['native_type'];
		}
		else
		{
		     $o->type = adodb_pdo_type($arr['pdo_type']);
		}

		$o->max_length = $arr['len'];
		$o->precision = $arr['precision'];

		switch(ADODB_ASSOC_CASE) {
			case ADODB_ASSOC_CASE_LOWER:
				$o->name = strtolower($o->name);
				break;
			case ADODB_ASSOC_CASE_UPPER:
				$o->name = strtoupper($o->name);
				break;
		}
		return $o;
	}
	
	/**
	 * Adjusts the result pointer to an arbitrary row in the result.
	 *
	 * @param int $row The row to seek to.
	 *
	 * @return bool False if the recordset contains no rows, otherwise true.
	 */
	public function _seek($row)
	{
		return false;
	}
	
	/**
	 * Attempt to fetch a result row using the current fetch mode and return whether or not this was successful.
	 *
	 * @return bool True if row was fetched successfully, otherwise false.
	 */
	public function _fetch()
	{
		if (!$this->_queryID) {
			return false;
		}

		$this->fields = $this->_queryID->fetch($this->fetchMode);
		return !empty($this->fields);
	}
	
	/**
	 * Frees the memory associated with a result.
	 *
	 * @return void
	 */
	public function _close()
	{
		$this->_queryID = false;
	}

	/**
	 * Returns a single field in a single row of the current recordset.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:recordset:fields
	 *
	 * @param string $colname The name of the field to retrieve.
	 *
	 * @return mixed
	 */
	public function fields($colname)
	{
		if ($this->adodbFetchMode != ADODB_FETCH_NUM) {
			return @$this->fields[$colname];
		}

		if (!$this->bind) {
			$this->bind = array();
			for ($i=0; $i < $this->_numOfFields; $i++) {
				$o = $this->FetchField($i);
				$this->bind[strtoupper($o->name)] = $i;
			}
		}
		return $this->fields[$this->bind[strtoupper($colname)]];
	}

}

class ADORecordSet_array_pdo extends ADORecordSet_array {}
