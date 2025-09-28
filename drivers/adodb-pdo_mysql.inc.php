<?php
/**
 * PDO MySQL driver
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

class ADODB_pdo_mysql extends ADODB_pdo {

	var $metaTablesSQL = "SELECT
			TABLE_NAME,
			CASE WHEN TABLE_TYPE = 'VIEW' THEN 'V' ELSE 'T' END
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_SCHEMA=";
	var $metaColumnsSQL = "SHOW COLUMNS FROM `%s`";
	var $sysDate = '(CURDATE())';
	var $sysTimeStamp = '(NOW())';
	var $hasGenID = true;
	
	/*
	* Sequence management statements
	*/
	public $_genIDSQL 		 = 'UPDATE %s SET id=LAST_INSERT_ID(id+1);';
	public $_genSeqSQL 	 	 = 'CREATE TABLE IF NOT EXISTS %s (id int not null)';
	public $_genSeqCountSQL  = 'SELECT COUNT(*) FROM %s';
	public $_genSeq2SQL 	 = 'INSERT INTO %s VALUES (%s)';
	public $_dropSeqSQL 	 = 'DROP TABLE IF EXISTS %s';
	
	var $fmtTimeStamp = "'Y-m-d H:i:s'";
	var $nameQuote = '`';

	public $hasTransactions = true;
	public $hasInsertID     = true;

	function _init($parentDriver)
	{
		$this->_connectionID->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
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
	function OffsetDate($dayFraction, $date=false)
	{
		if (!$date) {
			$date = $this->sysDate;
		}

		$fraction = $dayFraction * 24 * 3600;
		return $date . ' + INTERVAL ' .	$fraction . ' SECOND';
//		return "from_unixtime(unix_timestamp($date)+$fraction)";
	}

	/**
	 * Get a list of indexes on the specified table.
	 *
	 * @param string $table The name of the table to get indexes for.
	 * @param bool $primary (Optional) Whether or not to include the primary key.
	 * @param bool $owner (Optional) Unused.
	 *
	 * @return array|bool An array of the indexes, or false if the query to get the indexes failed.
	 */
	public function metaIndexes($table, $primary = false, $owner = false)
	{
		// save old fetch mode
		global $ADODB_FETCH_MODE;

		$false = false;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		if ($this->fetchMode !== FALSE) {
			$savem = $this->setFetchMode(FALSE);
		}

		// get index details
		$rs = $this->execute(sprintf('SHOW INDEXES FROM %s',$table));

		// restore fetchmode
		if (isset($savem)) {
			$this->setFetchMode($savem);
		}
		$ADODB_FETCH_MODE = $save;

		if (!is_object($rs)) {
			return $false;
		}

		$indexes = array ();

		// parse index data into array
		while ($row = $rs->fetchRow()) {
			if ($primary == FALSE AND $row[2] == 'PRIMARY') {
				continue;
			}

			if (!isset($indexes[$row[2]])) {
				$indexes[$row[2]] = array(
					'unique' => ($row[1] == 0),
					'columns' => array()
				);
			}

			$indexes[$row[2]]['columns'][$row[3] - 1] = $row[4];
		}

		// sort columns by order in the index
		foreach ( array_keys ($indexes) as $index )
		{
			ksort ($indexes[$index]['columns']);
		}

		return $indexes;
	}

	/**
	 * Returns a database-specific concatenation of strings.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:concat
	 *
	 * @return string
	 */
	public function concat()
	{
		$s = '';
		$arr = func_get_args();

		// suggestion by andrew005#mnogo.ru
		$s = implode(',', $arr);
		if (strlen($s) > 0) {
			return "CONCAT($s)";
		}
		return '';
	}

	/**
	 * Get information about the current MySQL server.
	 *
	 * @return array
	 */
	public function serverInfo()
	{
		$arr = array();
		$arr['description'] = ADOConnection::GetOne('select version()');
		$arr['version'] 	= ADOConnection::_findvers($arr['description']);
		return $arr;
	}

	/**
	 * Retrieves a list of tables based on given criteria
	 *
	 * @param string|bool $ttype (Optional) Table type = 'TABLE', 'VIEW' or false=both (default)
	 * @param string|bool $showSchema (Optional) schema name, false = current schema (default)
	 * @param string|bool $mask (Optional) filters the table by name
	 *
	 * @return array list of tables
	 */
	public function metaTables($ttype=false, $showSchema=false, $mask=false)
	{
		$save = $this->metaTablesSQL;
		if ($showSchema && is_string($showSchema)) {
			$this->metaTablesSQL .= $this->qstr($showSchema);
		} else {
			$this->metaTablesSQL .= 'schema()';
		}

		if ($mask) {
			$mask = $this->qstr($mask);
			$this->metaTablesSQL .= " like $mask";
		}
		$ret = ADOConnection::MetaTables($ttype, $showSchema);

		$this->metaTablesSQL = $save;
		return $ret;
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
	 * Sets the isolation level of a transaction.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:settransactionmode
	 *
	 * @param string $transaction_mode The transaction mode to set.
	 *
	 * @return void
	 */
	public function setTransactionMode($transaction_mode)
	{
		$this->_transmode  = $transaction_mode;
		if (empty($transaction_mode)) {
			$this->Execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
			return;
		}
		if (!stristr($transaction_mode, 'isolation')) {
			$transaction_mode = 'ISOLATION LEVEL ' . $transaction_mode;
		}
		$this->Execute('SET SESSION TRANSACTION ' . $transaction_mode);
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
	public function metaColumns($table, $normalize=true)
	{
		$this->_findschema($table, $schema);
		if ($schema) {
			$dbName = $this->database;
			$this->SelectDB($schema);
		}
		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

		if ($this->fetchMode !== false) {
			$savem = $this->SetFetchMode(false);
		}
		$rs = $this->Execute(sprintf($this->metaColumnsSQL, $table));

		if ($schema) {
			$this->SelectDB($dbName);
		}

		if (isset($savem)) {
			$this->SetFetchMode($savem);
		}
		$ADODB_FETCH_MODE = $save;
		if (!is_object($rs)) {
			$false = false;
			return $false;
		}

		$retarr = array();
		while (!$rs->EOF){
			$fld = new ADOFieldObject();
			$fld->name = $rs->fields[0];
			$type = $rs->fields[1];

			// split type into type(length):
			$fld->scale = null;
			if (preg_match('/^(.+)\((\d+),(\d+)/', $type, $query_array)) {
				$fld->type = $query_array[1];
				$fld->max_length = is_numeric($query_array[2]) ? $query_array[2] : -1;
				$fld->scale = is_numeric($query_array[3]) ? $query_array[3] : -1;
			} elseif (preg_match('/^(.+)\((\d+)/', $type, $query_array)) {
				$fld->type = $query_array[1];
				$fld->max_length = is_numeric($query_array[2]) ? $query_array[2] : -1;
			} elseif (preg_match('/^(enum)\((.*)\)$/i', $type, $query_array)) {
				$fld->type = $query_array[1];
				$arr = explode(',', $query_array[2]);
				$fld->enums = $arr;
				$zlen = max(array_map('strlen', $arr)) - 2; // PHP >= 4.0.6
				$fld->max_length = ($zlen > 0) ? $zlen : 1;
			} else { 
				$fld->type = $type;
				$fld->max_length = -1;
			}
			$fld->not_null = ($rs->fields[2] != 'YES');
			$fld->primary_key = ($rs->fields[3] == 'PRI');
			$fld->auto_increment = (strpos($rs->fields[5], 'auto_increment') !== false);
			$fld->binary = (strpos($type, 'blob') !== false);
			$fld->unsigned = (strpos($type, 'unsigned') !== false);

			if (!$fld->binary) {
				$d = $rs->fields[4];
				if ($d != '' && $d != 'NULL') {
					$fld->has_default = true;
					$fld->default_value = $d;
				} else {
					$fld->has_default = false;
				}
			}

			if ($save == ADODB_FETCH_NUM) {
				$retarr[] = $fld;
			} else {
				$retarr[strtoupper($fld->name)] = $fld;
			}
			$rs->MoveNext();
		}

		$rs->Close();
		return $retarr;
	}

	/**
	 * Choose a database to connect to. Many databases do not support this.
	 *
	 * @param string $dbName the name of the database to select
	 * @return bool
	 */
	public function selectDb($dbName)
	{
		$this->database = $dbName;
		$try = $this->Execute('use ' . $dbName);
		return ($try !== false);
	}

	/**
	 * Executes a provided SQL statement and returns a handle to the result, with the ability to supply a starting
	 * offset and record count.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:selectlimit
	 *
	 * @param string $sql The SQL to execute.
	 * @param int $nrows (Optional) The limit for the number of records you want returned. By default, all results.
	 * @param int $offset (Optional) The offset to use when selecting the results. By default, no offset.
	 * @param array|bool $inputarr (Optional) Any parameter values required by the SQL statement, or false if none.
	 * @param int $secs (Optional) If greater than 0, perform a cached execute. By default, normal execution.
	 *
	 * @return ADORecordSet|false The query results, or false if the query failed to execute.
	 */
	public function selectLimit($sql, $nrows=-1, $offset=-1, $inputarr=false, $secs=0)
	{
		$nrows = (int) $nrows;
		$offset = (int) $offset;
		$offsetStr =($offset>=0) ? "$offset," : '';
		// jason judge, see PHPLens Issue No: 9220
		if ($nrows < 0) {
			$nrows = '18446744073709551615';
		}

		if ($secs) {
			$rs = $this->CacheExecute($secs, $sql . " LIMIT $offsetStr$nrows", $inputarr);
		} else {
			$rs = $this->Execute($sql . " LIMIT $offsetStr$nrows", $inputarr);
		}
		return $rs;
	}

	/**
	 * Returns a portably-formatted date string from a timestamp database column.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:sqldate
	 *
	 * @param string $fmt The date format to use.
	 * @param string|bool $col (Optional) The table column to date format, or if false, use NOW().
	 *
	 * @return bool|string The SQL DATE_FORMAT() string, or false if the provided date format was empty.
	 */
	public function sqlDate($fmt, $col=false)
	{
		if (!$col) {
			$col = $this->sysTimeStamp;
		}
		$s = 'DATE_FORMAT(' . $col . ",'";
		$concat = false;
		$len = strlen($fmt);
		for ($i=0; $i < $len; $i++) {
			$ch = $fmt[$i];
			switch($ch) {

				default:
					if ($ch == '\\') {
						$i++;
						$ch = substr($fmt, $i, 1);
					}
					// FALL THROUGH
				case '-':
				case '/':
					$s .= $ch;
					break;

				case 'Y':
				case 'y':
					$s .= '%Y';
					break;

				case 'M':
					$s .= '%b';
					break;

				case 'm':
					$s .= '%m';
					break;

				case 'D':
				case 'd':
					$s .= '%d';
					break;

				case 'Q':
				case 'q':
					$s .= "'),Quarter($col)";

					if ($len > $i+1) {
						$s .= ",DATE_FORMAT($col,'";
					} else {
						$s .= ",('";
					}
					$concat = true;
					break;

				case 'H':
					$s .= '%H';
					break;

				case 'h':
					$s .= '%I';
					break;

				case 'i':
					$s .= '%i';
					break;

				case 's':
					$s .= '%s';
					break;

				case 'a':
				case 'A':
					$s .= '%p';
					break;

				case 'w':
					$s .= '%w';
					break;

				case 'W':
					$s .= '%U';
					break;

				case 'l':
					$s .= '%W';
					break;
			}
		}
		$s .= "')";
		if ($concat) {
			$s = "CONCAT($s)";
		}
		return $s;
	}

	/**
	 * A portable method of creating sequence numbers.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:genid
	 *
	 * @param string $seqname (Optional) The name of the sequence to use.
	 * @param int $startID (Optional) The point to start at in the sequence.
	 *
	 * @return bool|int|string
	 */
	public function genID($seqname='adodbseq',$startID=1)
	{
		$getnext = sprintf($this->_genIDSQL,$seqname);
		$holdtransOK = $this->_transOK; // save the current status
		$rs = @$this->Execute($getnext);
		if (!$rs) {
			if ($holdtransOK) $this->_transOK = true; //if the status was ok before reset
			$this->Execute(sprintf($this->_genSeqSQL,$seqname));
			$cnt = $this->GetOne(sprintf($this->_genSeqCountSQL,$seqname));
			if (!$cnt) $this->Execute(sprintf($this->_genSeq2SQL,$seqname,$startID-1));
			$rs = $this->Execute($getnext);
		}

		if ($rs) {
			$this->genID = $this->_connectionID->lastInsertId($seqname);
			$rs->Close();
		} else {
			$this->genID = 0;
		}

		return $this->genID;
	}

	/**
	 * Creates a sequence in the database.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:createsequence
	 *
	 * @param string $seqname The sequence name.
	 * @param int $startID The start id.
	 *
	 * @return ADORecordSet|bool A record set if executed successfully, otherwise false.
	 */
	function createSequence($seqname='adodbseq',$startID=1)
	{
		if (empty($this->_genSeqSQL)) {
			return false;
		}
		$ok = $this->Execute(sprintf($this->_genSeqSQL,$seqname,$startID));
		if (!$ok) {
			return false;
		}

		return $this->Execute(sprintf($this->_genSeq2SQL,$seqname,$startID-1));
	}

	/**
	 * Return information about a table's foreign keys.
	 *
	 * @param string $table The name of the table to get the foreign keys for.
	 * @param string|bool $owner (Optional) The database the table belongs to, or false to assume the current db.
	 * @param string|bool $upper (Optional) Force uppercase table name on returned array keys.
	 * @param bool $associative (Optional) Whether to return an associate or numeric array.
	 *
	 * @return array|bool An array of foreign keys, or false no foreign keys could be found.
	 */
	public function metaForeignKeys($table, $owner = '', $upper =  false, $associative =  false)
	{
	 global $ADODB_FETCH_MODE;
		if ($ADODB_FETCH_MODE == ADODB_FETCH_ASSOC || $this->fetchMode == ADODB_FETCH_ASSOC) $associative = true;

		if ( !empty($owner) ) {
			$table = "$owner.$table";
		}
		$a_create_table = $this->getRow(sprintf('SHOW CREATE TABLE %s', $table));
		if ($associative) {
			$create_sql = isset($a_create_table["Create Table"]) ? $a_create_table["Create Table"] : $a_create_table["Create View"];
		} else {
			$create_sql = $a_create_table[1];
		}

		$matches = array();

		if (!preg_match_all("/FOREIGN KEY \(`(.*?)`\) REFERENCES `(.*?)` \(`(.*?)`\)/", $create_sql, $matches)) return false;
		$foreign_keys = array();
		$num_keys = count($matches[0]);
		for ( $i = 0; $i < $num_keys; $i ++ ) {
			$my_field  = explode('`, `', $matches[1][$i]);
			$ref_table = $matches[2][$i];
			$ref_field = explode('`, `', $matches[3][$i]);

			if ( $upper ) {
				$ref_table = strtoupper($ref_table);
			}

			// see https://sourceforge.net/p/adodb/bugs/100/
			if (!isset($foreign_keys[$ref_table])) {
				$foreign_keys[$ref_table] = array();
			}
			$num_fields = count($my_field);
			for ( $j = 0; $j < $num_fields; $j ++ ) {
				if ( $associative ) {
					$foreign_keys[$ref_table][$ref_field[$j]] = $my_field[$j];
				} else {
					$foreign_keys[$ref_table][] = "{$my_field[$j]}={$ref_field[$j]}";
				}
			}
		}

		return $foreign_keys;
	}

	/**
	 * Returns information about stored procedures and stored functions.
	 *
	 * @param string|bool $NamePattern (Optional) Only look for procedures/functions with a name matching this pattern.
	 * @param null $catalog (Optional) Unused.
	 * @param null $schemaPattern (Optional) Unused.
	 *
	 * @return array
	 */
	public function metaProcedures($NamePattern = false, $catalog = null, $schemaPattern = null)
	{
		// save old fetch mode
		global $ADODB_FETCH_MODE;

		$false = false;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

		if ($this->fetchMode !== FALSE) {
			$savem = $this->SetFetchMode(FALSE);
		}

		$procedures = array ();

		// get index details

		$likepattern = '';
		if ($NamePattern) {
			$likepattern = " LIKE '".$NamePattern."'";
		}
		$rs = $this->Execute('SHOW PROCEDURE STATUS'.$likepattern);
		if (is_object($rs)) {

			// parse index data into array
			while ($row = $rs->FetchRow()) {
				$procedures[$row[1]] = array(
					'type' => 'PROCEDURE',
					'catalog' => '',
					'schema' => '',
					'remarks' => $row[7],
				);
			}
		}

		$rs = $this->Execute('SHOW FUNCTION STATUS'.$likepattern);
		if (is_object($rs)) {
			// parse index data into array
			while ($row = $rs->FetchRow()) {
				$procedures[$row[1]] = array(
					'type' => 'FUNCTION',
					'catalog' => '',
					'schema' => '',
					'remarks' => $row[7]
				);
			}
		}

		// restore fetchmode
		if (isset($savem)) {
			$this->SetFetchMode($savem);
		}
		$ADODB_FETCH_MODE = $save;

		return $procedures;
	}

}
