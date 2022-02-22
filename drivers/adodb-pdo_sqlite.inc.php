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
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
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
	var $_genIDSQL       = "UPDATE %s SET id=id+1 WHERE id=%s";
	var $_genSeqSQL      = "CREATE TABLE %s (id integer)";
	var $_genSeqCountSQL = 'SELECT COUNT(*) FROM %s';
	var $_genSeq2SQL     = 'INSERT INTO %s VALUES(%s)';
	var $_dropSeqSQL     = 'DROP TABLE %s';
	var $concat_operator = '||';
	var $pdoDriver       = false;
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
	public function genID($seq='adodbseq',$start=1)
	{

		// if you have to modify the parameter below, your database is overloaded,
		// or you need to implement generation of id's yourself!
		$MAXLOOPS = 100;
		while (--$MAXLOOPS>=0) {
			@($num = array_pop($this->GetCol("SELECT id FROM {$seq}")));
			if ($num === false || !is_numeric($num)) {
				@$this->Execute(sprintf($this->_genSeqSQL ,$seq));
				$start -= 1;
				$num = '0';
				$cnt = $this->GetOne(sprintf($this->_genSeqCountSQL,$seq));
				if (!$cnt) {
					$ok = $this->Execute(sprintf($this->_genSeq2SQL,$seq,$start));
				}
				if (!$ok) return false;
			}
			$this->Execute(sprintf($this->_genIDSQL,$seq,$num));

			if ($this->affected_rows() > 0) {
                	        $num += 1;
                		$this->genID = intval($num);
                		return intval($num);
			}
		}
		if ($fn = $this->raiseErrorFn) {
			$fn($this->databaseType,'GENID',-32000,"Unable to generate unique id after $MAXLOOPS attempts",$seq,$num);
		}
		return false;
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
	public function createSequence($seqname='adodbseq',$start=1)
	{

		$ok = $this->Execute(sprintf($this->_genSeqSQL,$seqname));
		if (!$ok) return false;
		$start -= 1;
		return $this->Execute("insert into $seqname values($start)");
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
}
