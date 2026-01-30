<?php
/**
 * SQLite3 driver
 *
 * @link https://www.sqlite.org/
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
 *
 * @TODO Duplicate code is due to the legacy sqlite driver - delete this when removing the old driver
 * @noinspection DuplicatedCode
 */

// security - hide paths
if (!defined('ADODB_DIR')) die();

/**
 * Class ADODB_sqlite3
 */
class ADODB_sqlite3 extends ADOConnection {
	var $databaseType = "sqlite3";
	var $dataProvider = "sqlite";
	var $replaceQuote = "''"; // string to use to replace quotes
	var $concat_operator='||';
	var $hasLimit = true;
	var $hasInsertID = true; 		/// supports autoincrement ID?
	var $hasAffectedRows = true; 	/// supports affected rows for update/delete?
	var $metaTablesSQL = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name";
	var $sysDate = "DATE('now','localtime')";
	var $sysTimeStamp = "DATETIME('now','localtime')";
	var $fmtTimeStamp = "'Y-m-d H:i:s'";
	var $_genSeqSQL = "create table %s (id integer)";
	var $_dropSeqSQL = 'drop table %s';

	/**
	 * The SQLite3 connection object
	 *
	 * @var SQLite3
	 */
	var $_connectionID;

	/**
	 * Returns an array with the server information
	 *
	 * @return array
	 */
	function serverInfo()
	{
		$version = SQLite3::version();
		$arr['version'] = $version['versionString'];
		$arr['description'] = 'SQLite 3';
		return $arr;
	}

	/**
	 * Return string with a database specific IFNULL statement
	 *
	 * @param string $field Field name to check for null
	 * @param string $ifNull Value to return if $field is null
	 *
	 * @return string
	 */
	function ifNull( $field, $ifNull ) {
		return " IFNULL($field, $ifNull) "; // if SQLite 3.X
	}

	/**
	 * Begin a transaction
	 *
	 * @return bool
	 */
	function beginTrans()
	{
		if ($this->transOff) {
			return true;
		}
		$this->Execute("BEGIN TRANSACTION");
		$this->transCnt += 1;
		return true;
	}

	/**
	 * Commit a transaction
	 *
	 * @param bool $ok If false, will rollback the transaction
	 *
	 * @return bool
	 */
	function commitTrans($ok=true)
	{
		if ($this->transOff) {
			return true;
		}
		if (!$ok) {
			return $this->RollbackTrans();
		}
		$ret = $this->Execute("COMMIT");
		if ($this->transCnt > 0) {
			$this->transCnt -= 1;
		}
		return !empty($ret);
	}

	/**
	 * Rollback a transaction
	 *
	 * @return bool
	 */
	function rollbackTrans()
	{
		if ($this->transOff) {
			return true;
		}
		$ret = $this->Execute("ROLLBACK");
		if ($this->transCnt > 0) {
			$this->transCnt -= 1;
		}
		return !empty($ret);
	}

	/**
	 * Returns the ADOdb metatype for a given SQLite type
	 *
	 * @param string|ADOFieldObject $t        The type to convert
	 * @param int                   $len      The length of the field (not used)
	 * @param bool                  $fieldobj If true, $t is an ADOFieldObject
	 *
	 * @return string The ADOdb metatype
	 */
	function metaType($t,$len=-1,$fieldobj=false)
	{

		if (is_object($t)) {
			$fieldobj = $t;
			$t = $fieldobj->type;
		}

		$t = strtoupper($t);

		if (array_key_exists($t,$this->customActualTypes))
			return  $this->customActualTypes[$t];

		/*
		* We are using the Sqlite affinity method here
		* @link https://www.sqlite.org/datatype3.html
		*/
		$affinity = array(
		'INT'=>'INTEGER',
		'INTEGER'=>'INTEGER',
		'TINYINT'=>'INTEGER',
		'SMALLINT'=>'INTEGER',
		'MEDIUMINT'=>'INTEGER',
		'BIGINT'=>'INTEGER',
		'UNSIGNED BIG INT'=>'INTEGER',
		'INT2'=>'INTEGER',
		'INT8'=>'INTEGER',

		'CHARACTER'=>'TEXT',
		'VARCHAR'=>'TEXT',
		'VARYING CHARACTER'=>'TEXT',
		'NCHAR'=>'TEXT',
		'NATIVE CHARACTER'=>'TEXT',
		'NVARCHAR'=>'TEXT',
		'TEXT'=>'TEXT',
		'CLOB'=>'TEXT',

		'BLOB'=>'BLOB',

		'REAL'=>'REAL',
		'DOUBLE'=>'REAL',
		'DOUBLE PRECISION'=>'REAL',
		'FLOAT'=>'REAL',

		'NUMERIC'=>'NUMERIC',
		'DECIMAL'=>'NUMERIC',
		'BOOLEAN'=>'NUMERIC',
		'DATE'=>'NUMERIC',
		'DATETIME'=>'NUMERIC'
		);

		if (!isset($affinity[$t]))
			return ADODB_DEFAULT_METATYPE;

		$subt = $affinity[$t];
		/*
		* Now that we have subclassed the provided data down
		* the sqlite 'affinity', we convert to ADOdb metatype
		*/

		$subclass = array('INTEGER'=>'I',
						  'TEXT'=>'X',
						  'BLOB'=>'B',
						  'REAL'=>'N',
						  'NUMERIC'=>'N');

		return $subclass[$subt];
	}

	/**
	 * Returns the metadata for a table
	 *
	 * @param string $table     The table name
	 * @param bool   $normalize If true, will return the field names in uppercase
	 *
	 * @return array|false An array of ADOFieldObject objects or false on failure
	 */
	function metaColumns($table, $normalize=true)
	{
		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
		if ($this->fetchMode !== false) {
			$savem = $this->SetFetchMode(false);
		}

		$rs = $this->execute("PRAGMA table_info(?)", array($table));

		if (isset($savem)) {
			$this->SetFetchMode($savem);
		}

		if (!$rs) {
			$ADODB_FETCH_MODE = $save;
			return false;
		}

		$arr = array();
		while ($r = $rs->FetchRow()) {
			// Metacolumns returns column names in lowercase
			$r = array_change_key_case($r, CASE_LOWER);

			$type = explode('(', $r['type']);
			$size = '';
			if (sizeof($type) == 2) {
				$size = trim($type[1], ')');
			}
			$fld = new ADOFieldObject;
			$fld->name = $r['name'];
			$fld->type = $type[0];
			$fld->max_length = $size;
			$fld->not_null = $r['notnull'];
			$fld->default_value = $r['dflt_value'];
			$fld->scale = 0;
			if (isset($r['pk']) && $r['pk']) {
				$fld->primary_key = 1;
			}
			if ($save == ADODB_FETCH_NUM) {
				$arr[] = $fld;
			} else {
				$arr[strtoupper($fld->name)] = $fld;
			}
		}
		$rs->Close();
		$ADODB_FETCH_MODE = $save;
		return $arr;
	}

	/**
	 * Returns the foreign keys for a table
	 *
	 * @param string $table       The table name
	 * @param string $owner       The owner of the table (not used)
	 * @param bool   $upper       If true, will return uppercase table names
	 * @param bool   $associative If true, will return an associative array
	 *
	 * @return array An array of foreign keys or false on failure
	 */
	public function metaForeignKeys($table, $owner = '', $upper =  false, $associative =  false)
	{
		global $ADODB_FETCH_MODE;
		if ($ADODB_FETCH_MODE == ADODB_FETCH_ASSOC || $this->fetchMode == ADODB_FETCH_ASSOC) {
			$associative = true;
		}

		// Read sqlite master to find foreign keys
		$sql = "SELECT sql
				FROM sqlite_master
				WHERE sql NOTNULL
				  AND LOWER(name) = ?";
		$tableSql = $this->getOne($sql, [strtolower($table)]);

		// Regex will identify foreign keys in both column and table constraints
		// Reference: https://sqlite.org/syntax/foreign-key-clause.html
		// Subpatterns: 1/2 = source columns; 3 = parent table; 4 = parent columns.
		preg_match_all(
			'/[(,]\s*(?:FOREIGN\s+KEY\s*\(([^)]+)\)|(\w+).*?)\s*REFERENCES\s+(\w+|"[^"]+")\(([^)]+)\)/i',
			$tableSql,
			$fkeyMatches,
			PREG_SET_ORDER
		);

		$fkeyList = array();
		foreach ($fkeyMatches as $fkey) {
			$src_col = $fkey[1] ?: $fkey[2];
			$ref_table = $upper ? strtoupper($fkey[3]) : $fkey[3];
			$ref_col = $fkey[4];

			if ($associative) {
				$fkeyList[$ref_table][$src_col] = $ref_col;
			} else {
				$fkeyList[$ref_table][] = $src_col . '=' . $ref_col;
			}
		}

		return $fkeyList;
	}

	/**
	 * Initialize the driver
	 *
	 * @param ADOConnection $parentDriver The parent connection object
	 *
	 * @return void
	 */
	function _init($parentDriver)
	{
		$parentDriver->hasTransactions = false;
		$parentDriver->hasInsertID = true;
	}

	/**
	 * Returns the last inserted ID
	 *
	 * @param string $table  The table name (not used)
	 * @param string $column The column name (not used)
	 *
	 * @return int The last inserted ID
	 */
	protected function _insertID($table = '', $column = '')
	{
		return $this->_connectionID->lastInsertRowID();
	}

	/**
	 * Returns the number of affected rows by the last query
	 *
	 * @return int The number of affected rows
	 */
	function _affectedrows()
	{
		return $this->_connectionID->changes();
	}

	/**
	 * Sets the last error message and code
	 *
	 * This is called after a failed query to set the error message and code
	 *
	 * @return void
	 */
	protected function lastError()
	{
		$this->_errorMsg = $this->_connectionID->lastErrorMsg();
		$this->_errorCode = $this->_connectionID->lastErrorCode();
	}

	/**
	 * Returns the last error message
	 *
	 * @return string The last error message
	 */
	function errorMsg()
	 {
		return $this->_errorMsg;
	}

	/**
	 * Returns the last error code
	 *
	 * @return int The last error code
	 */
	function errorNo()
	{
		return $this->_errorCode;
	}

	/**
	 * Returns a formatted date string for SQLite
	 *
	 * This function formats the date according to the SQLite strftime function
	 * and ensures proper casing for certain fields.
	 *
	 * @param string $fmt The format string
	 * @param bool   $col If true, will use the column name in the format
	 *
	 * @return string The formatted date string
	 */
	function sqlDate($fmt, $col=false)
	{
		// In order to map the values correctly, we must ensure the proper
		// casing for certain fields:
		// - Y must be UC, because y is a 2 digit year
		// - d must be LC, because D is 3 char day
		// - A must be UC  because a is non-portable am
		// - Q must be UC  because q means nothing
		$fromChars = array('y', 'D', 'a', 'q');
		$toChars = array('Y', 'd', 'A', 'Q');
		$fmt = str_replace($fromChars, $toChars, $fmt);

		$fmt = $this->qstr($fmt);
		return $col ? "adodb_date($fmt,$col)" : "adodb_date($fmt)";
	}

	/**
	 * Connects to the SQLite database
	 *
	 * @param string $argHostname     The hostname or database file path
	 * @param string $argUsername     The username (not used)
	 * @param string $argPassword     The password (not used)
	 * @param string $argDatabasename The database name (not used)
	 *
	 * @noinspection PhpUnusedParameterInspection
	 *
	 * @return bool True on success, false on failure
	 */
	function _connect($argHostname, $argUsername, $argPassword, $argDatabasename)
	{
		if (empty($argHostname) && $argDatabasename) {
			$argHostname = $argDatabasename;
		}
		$this->_connectionID = new SQLite3($argHostname);

		// Register date conversion function for SQLDate() method
		// Replaces the legacy adodb_date() functions removed in 5.23.0
		$this->_connectionID->createFunction('adodb_date',
			function (string $fmt, $date = null) : string {
				if ($date === null || $date === false) {
					return date($fmt);
				}

				// If it's an int then assume a Unix timestamp, otherwise convert it
				return date($fmt, is_int($date) ? $date : strtotime($date));
			}
		);

		return true;
	}

	/**
	 * Connects to the SQLite database using a persistent connection
	 *
	 * @param string $argHostname     The hostname or database file path
	 * @param string $argUsername     The username (not used)
	 * @param string $argPassword     The password (not used)
	 * @param string $argDatabasename The database name (not used)
	 *
	 * @noinspection PhpUnusedParameterInspection
	 *
	 * @return bool True on success, false on failure
	 */
	function _pconnect($argHostname, $argUsername, $argPassword, $argDatabasename)
	{
		// There's no permanent connect in SQLite3
		return $this->_connect($argHostname, $argUsername, $argPassword, $argDatabasename);
	}

	/**
	 * Executes a query on the SQLite database
	 *
	 * @param string $sql      The SQL query to execute
	 * @param mixed  $inputarr An array of input parameters (not used)
	 *
	 * @return SQLite3Result|bool The result set or true on success, false on failure
	 */
	function _query($sql,$inputarr=false)
	{
		$rez = $this->_connectionID->query($sql);
		if ($rez === false) {
			$this->lastError();
		} elseif ($rez->numColumns() == 0) {
			// If no data was returned, we don't need to create a real recordset
			$rez->finalize();
			$rez = true;
		}

		return $rez;
	}

	/**
	 * Executes a query with a limit and offset
	 *
	 * This function modifies the SQL query to include a LIMIT and OFFSET clause
	 * based on the provided parameters.
	 *
	 * @param string $sql        The SQL query to execute
	 * @param int    $nrows      The number of rows to return (default -1 for no limit)
	 * @param int    $offset     The offset to start returning rows from (default -1 for no offset)
	 * @param mixed  $inputarr   An array of input parameters (not used)
	 * @param int    $secs2cache Number of seconds to cache the result (default 0 for no caching)
	 *
	 * @return SQLite3Result|bool The result set or true on success, false on failure
	 */
	function selectLimit($sql,$nrows=-1,$offset=-1,$inputarr=false,$secs2cache=0)
	{
		$nrows = (int) $nrows;
		$offset = (int) $offset;
		$offsetStr = ($offset >= 0) ? " OFFSET $offset" : '';
		$limitStr  = ($nrows >= 0)  ? " LIMIT $nrows" : ($offset >= 0 ? ' LIMIT 999999999' : '');
		if ($secs2cache) {
			$rs = $this->CacheExecute($secs2cache,$sql."$limitStr$offsetStr",$inputarr);
		} else {
			$rs = $this->Execute($sql."$limitStr$offsetStr",$inputarr);
		}

		return $rs;
	}

	/**
	 * Generates a unique ID using a sequence table
	 *
	 * This function uses a sequence table to generate unique IDs. If the sequence
	 * table does not exist, it will create it. The function will return false if
	 * it is unable to generate a unique ID after a specified number of attempts.
	 *
	 * @param string $seqname The name of the sequence table (default 'adodbseq')
	 * @param int    $startID The starting ID value (default 1)
	 *
	 * @return int|false The generated unique ID or false on failure
	*/
	function genID($seqname='adodbseq', $startID=1)
	{
		// if you have to modify the parameter below, your database is overloaded,
		// or you need to implement generation of id's yourself!
		$MAXLOOPS = 100;
		//$this->debug=1;
		while (--$MAXLOOPS>=0) {
			@($num = $this->GetOne("select id from $seqname"));
			if ($num === false) {
				$this->Execute(sprintf($this->_genSeqSQL ,$seqname));
				$startID -= 1;
				$num = '0';
				$ok = $this->Execute("insert into $seqname values($startID)");
				if (!$ok) {
					return false;
				}
			}
			$this->Execute("update $seqname set id=id+1 where id=$num");

			if ($this->affected_rows() > 0) {
				$num += 1;
				$this->genID = $num;
				return $num;
			}
		}
		if ($fn = $this->raiseErrorFn) {
			$fn($this->databaseType, 'GENID', -32000, "Unable to generate unique id after $MAXLOOPS attempts", $seqname, $num);
		}
		return false;
	}

	/**
	 * Creates a sequence table
	 *
	 * This function creates a sequence table with the specified name and starts
	 * the ID at the specified value. If the sequence table already exists, it will
	 * return false.
	 *
	 * @param string $seqname The name of the sequence table (default 'adodbseq')
	 * @param int    $startID The starting ID value (default 1)
	 *
	 * @return bool True on success, false on failure
	 */
	function createSequence($seqname='adodbseq', $startID=1)
	{
		if (empty($this->_genSeqSQL)) {
			return false;
		}
		$ok = $this->Execute(sprintf($this->_genSeqSQL,$seqname));
		if (!$ok) {
			return false;
		}
		$startID -= 1;
		return $this->Execute("insert into $seqname values($startID)");
	}

	/**
	 * Drops a sequence table
	 *
	 * This function drops the specified sequence table. If the sequence table does
	 * not exist or if the drop SQL is not set, it will return false.
	 *
	 * @param string $seqname The name of the sequence table (default 'adodbseq')
	 *
	 * @return bool True on success, false on failure
	 */
	function dropSequence($seqname = 'adodbseq')
	{
		if (empty($this->_dropSeqSQL)) {
			return false;
		}
		return $this->Execute(sprintf($this->_dropSeqSQL,$seqname));
	}

	/**
	 * Closes the SQLite connection
	 *
	 * This function closes the SQLite connection and returns true on success.
	 *
	 * @return bool True on success, false on failure
	 */
	function _close()
	{
		return $this->_connectionID->close();
	}

	/**
	 * Returns the indexes for a table
	 *
	 * This function retrieves the indexes for a given table from the SQLite master table.
	 * It can also return the primary key index if requested.
	 *
	 * @param string $table   The table name
	 * @param bool   $primary If true, will include the primary key index
	 * @param bool   $owner   Not used, included for compatibility
	 *
	 * @return array|false An array of indexes or false on failure
	 */
	function metaIndexes($table, $primary = false, $owner = false)
	{
		// save old fetch mode
		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		if ($this->fetchMode !== false) {
			$savem = $this->SetFetchMode(false);
		}

		$table = strtolower($table);

		// Exclude the empty entry for the primary index
		$sql = "SELECT name,sql
				FROM sqlite_master
				WHERE type='index'
				  AND sql IS NOT NULL
				  AND LOWER(tbl_name)=?";
		$rs = $this->execute($sql, [$table]);

		if (!is_object($rs)) {
			if (isset($savem)) {
				$this->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			return false;
		}

		$indexes = array();

		while ($row = $rs->FetchRow()) {
			if (!isset($indexes[$row[0]])) {
				$indexes[$row[0]] = array(
					'unique' => preg_match("/unique/i", $row[1]),
				);
			}
			// Index elements appear in the SQL statement in cols[1] between parentheses
			// e.g CREATE UNIQUE INDEX ware_0 ON warehouse (org,warehouse)
			preg_match_all('/\((.*)\)/', $row[1], $indexExpression);
			$indexes[$row[0]]['columns'] = array_map('trim', explode(',', $indexExpression[1][0]));
		}

		// If we want the primary key, we must extract it from the pragma
		if ($primary){
			$pragmaData = $this->getAll('PRAGMA table_info(?);', [$table]);
			$pkIndexData = array('unique'=>1,'columns'=>array());

			$pkCallBack = function ($value, $key) use (&$pkIndexData) {
				// As we iterate the elements check for pk index
				if ($value[5] > 0) {
					$pkIndexData['columns'][$value[5]] = strtolower($value[1]);
				}
			};
			array_walk($pragmaData, $pkCallBack);

			// If we found no columns, there is no primary index
			if (count($pkIndexData['columns']) > 0) {
				$indexes['PRIMARY'] = $pkIndexData;
			}
		}

		if (isset($savem)) {
			$this->SetFetchMode($savem);
			$ADODB_FETCH_MODE = $save;
		}

		return $indexes;
	}

	/**
	* Returns the maximum size of a MetaType C field. Because of the
	* database design, sqlite places no limits on the size of data inserted
	*
	* @return int
	*/
	function charMax()
	{
		return ADODB_STRINGMAX_NOLIMIT;
	}

	/**
	* Returns the maximum size of a MetaType X field. Because of the
	* database design, sqlite places no limits on the size of data inserted
	*
	* @return int
	*/
	function textMax()
	{
		return ADODB_STRINGMAX_NOLIMIT;
	}

	/**
	 * Converts a date to a month only field and pads it to 2 characters
	 *
	 * This uses the more efficient strftime native function to process
	 *
	 * @param string $fld	The name of the field to process
	 *
	 * @return string The SQL Statement
	 */
	function month($fld)
	{
		return "strftime('%m',$fld)";
	}

	/**
	 * Converts a date to a day only field and pads it to 2 characters
	 *
	 * This uses the more efficient strftime native function to process
	 *
	 * @param string $fld	The name of the field to process
	 *
	 * @return string The SQL Statement
	 */
	function day($fld) {
		return "strftime('%d',$fld)";
	}

	/**
	 * Converts a date to a year only field
	 *
	 * This uses the more efficient strftime native function to process
	 *
	 * @param string $fld	The name of the field to process
	 *
	 * @return string The SQL Statement
	 */
	function year($fld)
	{
		return "strftime('%Y',$fld)";
	}

	/**
	 * SQLite update for blob
	 *
	 * SQLite must be a fully prepared statement (all variables must be bound),
	 * so $where can either be an array (array params) or a string that we will
	 * do our best to unpack and turn into a prepared statement.
	 *
	 * @param string $table    The table name
	 * @param string $column   The column name to update
	 * @param string $val      Blob value to set
	 * @param mixed  $where    An array of parameters (key => value pairs),
	 *                         or a string (where clause).
	 * @param string $blobtype ignored
	 *
	 * @return bool success
	 */
	function updateBlob($table, $column, $val, $where, $blobtype = 'BLOB')
	{
		if (is_array($where)) {
			// We were passed a set of key=>value pairs
			$params = $where;
		} else {
			// Given a where clause string, we have to disassemble the
			// statements into keys and values
			$params = array();
			$temp = preg_split('/(where|and)/i', $where);
			$where = array_filter($temp);

			foreach ($where as $wValue) {
				$wTemp = preg_split('/[= \']+/', $wValue);
				$wTemp = array_filter($wTemp);
				$wTemp = array_values($wTemp);
				$params[$wTemp[0]] = $wTemp[1];
			}
		}

		$paramWhere = array();
		foreach ($params as $bindKey => $bindValue) {
			$paramWhere[] = $bindKey . '=?';
		}

		$sql = "UPDATE $table SET $column=? WHERE "
			. implode(' AND ', $paramWhere);

		// Prepare the statement
		$stmt = $this->_connectionID->prepare($sql);
		if ($stmt === false) {
			$this->lastError();
			return false;
		}

		// Set the first bind value equal to value we want to update
		if (!$stmt->bindValue(1, $val, SQLITE3_BLOB)) {
			return false;
		}

		// Build as many keys as available
		$bindIndex = 2;
		foreach ($params as $bindValue) {
			if (is_integer($bindValue) || is_bool($bindValue) || is_float($bindValue)) {
				$type = SQLITE3_NUM;
			} elseif (is_object($bindValue)) {
				// Assume a blob, this should never appear in
				// the binding for a where statement anyway
				$type = SQLITE3_BLOB;
			} else {
				$type = SQLITE3_TEXT;
			}

			if (!$stmt->bindValue($bindIndex, $bindValue, $type)) {
				return false;
			}

			$bindIndex++;
		}

		// Now execute the update. NB this is SQLite execute, not ADOdb
		$ok = $stmt->execute();
		return is_object($ok);
	}

	/**
	 * SQLite update for blob from a file
	 *
	 * @param string $table    The table name
	 * @param string $column   The column name to update
	 * @param string $path      Filename containing blob data
	 * @param mixed  $where    {@see updateBlob()}
	 * @param string $blobtype ignored
	 *
	 * @return bool success
	 */
	function updateBlobFile($table, $column, $path, $where, $blobtype = 'BLOB')
	{
		if (!file_exists($path)) {
			return false;
		}

		// Read file information
		$fileContents = file_get_contents($path);
		if ($fileContents === false)
			// Distinguish between an empty file and failure
			return false;

		return $this->updateBlob($table, $column, $fileContents, $where, $blobtype);
	}

}

/*--------------------------------------------------------------------------------------
		Class Name: Recordset
--------------------------------------------------------------------------------------*/

/**
 * Class ADORecordset_sqlite3
 *
 * This class extends ADORecordSet to provide SQLite3 specific functionality.
 * It handles fetching records, field metadata, and other recordset operations.
 */
class ADORecordset_sqlite3 extends ADORecordSet {

	var $databaseType = "sqlite3";
	var $bind = false;

	/**
	 * The SQLite3Result object
	 *
	 * @var SQLite3Result
	 */
	var $_queryID;


	/**
	 * Constructor for the ADORecordset_sqlite3 class
	 *
	 * @param SQLite3Result $queryID The SQLite3Result object
	 * @param bool          $mode    The fetch mode (default false)
	 *
	 * @noinspection PhpMissingParentConstructorInspection
	 */
	function __construct($queryID, $mode=false)
	{
		parent::__construct($queryID, $mode);
		switch($this->adodbFetchMode) {
			case ADODB_FETCH_NUM:
				$this->fetchMode = SQLITE3_NUM;
				break;
			case ADODB_FETCH_ASSOC:
				$this->fetchMode = SQLITE3_ASSOC;
				break;
			default:
				$this->fetchMode = SQLITE3_BOTH;
				break;
		}

		$this->_inited = true;
		$this->fields = array();
		if ($queryID) {
			$this->_currentRow = 0;
			$this->EOF = !$this->_fetch();
			@$this->_initrs();
		} else {
			$this->_numOfRows = 0;
			$this->_numOfFields = 0;
			$this->EOF = true;
		}

		return $this->_queryID;
	}

	/**
	 * Returns the field object for a given field offset
	 *
	 * This function retrieves the field object for a specific field offset.
	 * It creates a new ADOFieldObject and sets its name and type.
	 *
	 * @param int $fieldOffset The offset of the field (default -1 for the first field)
	 *
	 * @return ADOFieldObject The field object
	 */
	function fetchField($fieldOffset = -1)
	{
		$fld = new ADOFieldObject;
		$fld->name = $this->_queryID->columnName($fieldOffset);
		$fld->type = 'VARCHAR';
		$fld->max_length = -1;
		return $fld;
	}

	/**
	 * Initializes the recordset by setting the number of fields
	 *
	 * This function is called after the query has been executed to set the
	 * number of fields in the recordset.
	 *
	 * @return void
	 */
	function _initrs()
	{
		$this->_numOfFields = $this->_queryID->numColumns();

	}

	/**
	 * Returns the value of a field by its name
	 *
	 * This function retrieves the value of a field by its name. If the fetch mode
	 * is not numeric, it will return the value directly. If it is numeric, it will
	 * use a binding array to map the column names to their respective indices.
	 *
	 * @param string $colname The name of the column
	 *
	 * @return mixed The value of the field
	 */
	function fields($colname)
	{
		if ($this->fetchMode != SQLITE3_NUM) {
			return $this->fields[$colname];
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

	/**
	 * Returns the number of rows in the recordset
	 *
	 * This function returns the number of rows in the recordset. If the recordset
	 * is empty, it will return 0.
	 *
	 * @param array $row ignored for SQLite3
	 *
	 * @return int The number of rows in the recordset
	 */
	function _seek($row)
	{
		// sqlite3 does not implement seek
		if ($this->debug) {
			ADOConnection::outp("SQLite3 does not implement seek");
		}
		return false;
	}

	/**
	 * Uses the SQLite3 fetchArray method to retrieve the next row.
	 *
	 * @param bool $ignore_fields discarded for SQLite3.
	 *
	 * @return bool
	 */
	function _fetch($ignore_fields=false)
	{
		$this->fields = $this->_queryID->fetchArray($this->fetchMode);
		if (!empty($this->fields) && ADODB_ASSOC_CASE != ADODB_ASSOC_CASE_NATIVE) {
			$this->fields = array_change_key_case($this->fields, ADODB_ASSOC_CASE);
		}

		return !empty($this->fields);
	}

	/**
	 * Closes the recordset
	 *
	 * This function is called to close the recordset. It does not perform any
	 * specific actions for SQLite3, as the connection is managed by the ADOConnection class.
	 *
	 * @return void
	 */
	function _close()
	{
	}

}
