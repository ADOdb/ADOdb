<?php
/**
 * PDO SQLite driver
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
 * @copyright 2022 Damien Regad, Mark Newnham and the ADOdb community
 * @author Diogo Toscano <diogo@scriptcase.net>
 * @author Sid Dunayer <sdunayer@interserv.com>
 */

final class ADODB_pdo_sqlite extends ADODB_pdo {

	var $metaTablesSQL   = "SELECT name FROM sqlite_master WHERE type='table'";
	var $sysDate         = 'current_date';
	var $sysTimeStamp    = 'current_timestamp';
	var $nameQuote       = '`';
	var $replaceQuote    = "''";
	var $hasGenID        = true;
	
	var $concat_operator = '||';
	var $random='abs(random())';

	public $bindInputArray = true;
	public $hasTransactions = false; // // should be set to false because of PDO SQLite driver not supporting changing autocommit mode
	public $hasInsertID = true;

	/**
	 * Get information about the current SQLite database
	 *
	 * @return array
	 */
	public function serverInfo()
	{
		
		@($ver = array_pop($this->GetCol("SELECT sqlite_version()")));
		@($enc = array_pop($this->GetCol("PRAGMA encoding")));

		$arr['version']     = $ver;
		$arr['description'] = 'SQLite ';
		$arr['encoding']    = $enc;

		return $arr;
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
	public function selectLimit($sql,$nrows=-1,$offset=-1,$inputarr=false,$secs2cache=0)
	{
		$nrows = (int) $nrows;
		$offset = (int) $offset;

		$offsetStr = ($offset >= 0) ? " OFFSET $offset" : '';
		$limitStr  = ($nrows >= 0)  ? " LIMIT $nrows" : ($offset >= 0 ? ' LIMIT 999999999' : '');
		if ($secs2cache)
			$rs = $this->CacheExecute($secs2cache,$sql."$limitStr$offsetStr",$inputarr);
		else
			$rs = $this->Execute($sql."$limitStr$offsetStr",$inputarr);

		return $rs;
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
	public function GgenId($seq='adodbseq',$start=1)
	{
		// if you have to modify the parameter below, your database is overloaded,
		// or you need to implement generation of id's yourself!
		$MAXLOOPS = 100;
		//$this->debug=1;
		while (--$MAXLOOPS>=0) {
			@($num = $this->GetOne("select id from $seq"));
			if ($num === false) {
				$this->Execute(sprintf($this->_genSeqSQL ,$seq));
				$start -= 1;
				$num = '0';
				$ok = $this->Execute("insert into $seq values($start)");
				if (!$ok) {
					return false;
				}
			}
			$this->Execute("update $seq set id=id+1 where id=$num");

			if ($this->affected_rows() > 0) {
				$num += 1;
				$this->genID = $num;
				return $num;
			}
		}
		if ($fn = $this->raiseErrorFn) {
			$fn($this->databaseType,'GENID',-32000,"Unable to generate unique id after $MAXLOOPS attempts",$seq,$num);
		}
		return false;
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
		$this->_transmode = strtoupper($transaction_mode);
	}

	/**
	 * Begins a granular transaction.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:begintrans
	 *
	 * @return bool Always returns true.
	 */
	public function beginTrans()
	{

		if ($this->transOff) return true;
		$this->transCnt += 1;
		$this->_autocommit = false;
		return $this->Execute("BEGIN {$this->_transmode}");
	}

	/**
	 * Commits a granular transaction.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:committrans
	 *
	 * @param bool $ok (Optional) If false, will rollback the transaction instead.
	 *
	 * @return bool Always returns true.
	 */
	public function commitTrans($ok=true)
	{

		if ($this->transOff) return true;
		if (!$ok) return $this->RollbackTrans();
		if ($this->transCnt) $this->transCnt -= 1;
		$this->_autocommit = true;

		$ret = $this->Execute('COMMIT');
		return $ret;
	}

	/**
	 * Rollback a smart transaction.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:rollbacktrans
	 *
	 * @return bool Always returns true.
	 */
	public function rollbackTrans()
	{

		if ($this->transOff) return true;
		if ($this->transCnt) $this->transCnt -= 1;
		$this->_autocommit = true;

		$ret = $this->Execute('ROLLBACK');
		return $ret;
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
	public function metaForeignKeys($table, $owner = '', $upper =  false, $associative =  false)
	{
	    global $ADODB_FETCH_MODE;
		if ($ADODB_FETCH_MODE == ADODB_FETCH_ASSOC
		|| $this->fetchMode == ADODB_FETCH_ASSOC)
		$associative = true;

	    /*
		* Read sqlite master to find foreign keys
		*/
		$sql = "SELECT sql
				 FROM (
				SELECT sql sql, type type, tbl_name tbl_name, name name
				  FROM sqlite_master
			          )
				WHERE type != 'meta'
				  AND sql NOTNULL
				  AND LOWER(name) ='" . strtolower($table) . "'";

		$tableSql = $this->getOne($sql);

		$fkeyList = array();
		$ylist = preg_split("/,+/",$tableSql);
		foreach ($ylist as $y)
		{
			if (!preg_match('/FOREIGN/',$y))
				continue;

			$matches = false;
			preg_match_all('/\((.+?)\)/i',$y,$matches);
			$tmatches = false;
			preg_match_all('/REFERENCES (.+?)\(/i',$y,$tmatches);

			if ($associative)
			{
				if (!isset($fkeyList[$tmatches[1][0]]))
					$fkeyList[$tmatches[1][0]]	= array();
				$fkeyList[$tmatches[1][0]][$matches[1][0]] = $matches[1][1];
			}
			else
				$fkeyList[$tmatches[1][0]][] = $matches[1][0] . '=' . $matches[1][1];
		}

		if ($associative)
		{
			if ($upper)
				$fkeyList = array_change_key_case($fkeyList,CASE_UPPER);
			else
				$fkeyList = array_change_key_case($fkeyList,CASE_LOWER);
		}
		return $fkeyList;
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
	public function metaColumns($tab,$normalize=true)
	{
		global $ADODB_FETCH_MODE;

		$false = false;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
		if ($this->fetchMode !== false) {

			$savem = $this->SetFetchMode(false);
		}
		$rs = $this->Execute("PRAGMA table_info('$tab')");
		if (isset($savem)) {
			$this->SetFetchMode($savem);
		}
		if (!$rs) {
			$ADODB_FETCH_MODE = $save;
			return $false;
		}
		$arr = array();
		while ($r = $rs->FetchRow()) {
			$type = explode('(', $r['type']);
			$size = '';
			if (sizeof($type) == 2) {
				$size = trim($type[1], ')');
			}
			$fn = strtoupper($r['name']);
			$fld = new ADOFieldObject;
			$fld->name = $r['name'];
			$fld->type = $type[0];
			$fld->max_length = $size;
			$fld->not_null = $r['notnull'];
			$fld->primary_key = $r['pk'];
			$fld->default_value = $r['dflt_value'];
			$fld->scale = 0;
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
	 * Retrieves a list of tables based on given criteria
	 *
	 * @param string|bool $ttype (Optional) Table type = 'TABLE', 'VIEW' or false=both (default)
	 * @param string|bool $showSchema (Optional) schema name, false = current schema (default)
	 * @param string|bool $mask (Optional) filters the table by name
	 *
	 * @return array list of tables
	 */
	public function metaTables($ttype=false,$showSchema=false,$mask=false)
	{
	
		if ($mask) {
			$save = $this->metaTablesSQL;
			$mask = $this->qstr(strtoupper($mask));
			$this->metaTablesSQL .= " AND name LIKE $mask";
		}

		$ret = $this->GetCol($this->metaTablesSQL);

		if ($mask) {
			$this->metaTablesSQL = $save;
		}
		return $ret;
	}

	/**
	 * Returns a driver-specific format for a bind parameter
	 *
	 * @param string $name
	 * @param string $type (ignored in driver)
	 *
	 * @return string
	 */
	public function param($name,$type='C')
	{
		return sprintf(':%s', $name);
	}

	/**
	  * Gets the database name from the DSN
	  *
	  * @param	string	$dsnString
	  *
	  * @return string
	  */
	  protected function getDatabasenameFromDsn($dsnString){

		return $dsnString;
	}
	
}
