<?php
/*
@version   v5.21.0-dev  ??-???-2016
@copyright (c) 2000-2013 John Lim (jlim#natsoft.com). All rights reserved.
@copyright (c) 2014      Damien Regad, Mark Newnham and the ADOdb community
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.
  Set tabs to 8.

*/
namespace ADOdb\drivers\PDO\mysql;

use ADOdb;
use ADOdb\drivers\PDO;

class adoConnection extends ADOdb\drivers\PDO\adoConnection
{
	var $metaTablesSQL = "SELECT
			TABLE_NAME,
			CASE WHEN TABLE_TYPE = 'VIEW' THEN 'V' ELSE 'T' END
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_SCHEMA=";
	var $metaColumnsSQL = "SHOW COLUMNS FROM `%s`";
	var $sysDate = 'CURDATE()';
	var $sysTimeStamp = 'NOW()';
	var $hasGenID = true;
	var $_genIDSQL = "update %s set id=LAST_INSERT_ID(id+1);";
	var $_dropSeqSQL = "drop table %s";
	var $fmtTimeStamp = "'Y-m-d, H:i:s'";
	var $nameQuote = '`';

	function _init($parentDriver)
	{
		$parentDriver->hasTransactions = false;
		#$parentDriver->_bindInputArray = false;
		$parentDriver->hasInsertID = true;
		$parentDriver->_connectionID->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
	}

	// dayFraction is a day in floating point
	function OffsetDate($dayFraction, $date=false)
	{
		if (!$date) {
			$date = $this->sysDate;
		}

		$fraction = $dayFraction * 24 * 3600;
		return $date . ' + INTERVAL ' .	$fraction . ' SECOND';
//		return "from_unixtime(unix_timestamp($date)+$fraction)";
	}

	function Concat()
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

	function ServerInfo()
	{
		$arr['description'] = ADOConnection::GetOne('select version()');
		$arr['version'] = ADOConnection::_findvers($arr['description']);
		return $arr;
	}

	/*
	function MetaTables($ttype=false, $showSchema=false, $mask=false)
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
	*/

    /**
     * @param bool $auto_commit
     * @return void
     */
    function SetAutoCommit($auto_commit)
    {
        $this->_connectionID->setAttribute(PDO::ATTR_AUTOCOMMIT, $auto_commit);
    }

	function SetTransactionMode($transaction_mode)
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

	function MetaColumns($table, $normalize=true)
	{
		$mc = new ADOdb\drivers\PDO\mysql\datadict\metaColumns($this,$table, $normalize=true);
		return $mc->getResult();
	}
	
	// returns true or false
	function SelectDB($dbName)
	{
		$this->database = $dbName;
		$this->databaseName = $dbName; # obsolete, retained for compat with older adodb versions
		$try = $this->Execute('use ' . $dbName);
		return ($try !== false);
	}

	// parameters use PostgreSQL convention, not MySQL
	function SelectLimit($sql, $nrows=-1, $offset=-1, $inputarr=false, $secs=0)
	{
		$nrows = (int) $nrows;
		$offset = (int) $offset;		
		$offsetStr =($offset>=0) ? "$offset," : '';
		// jason judge, see http://phplens.com/lens/lensforum/msgs.php?id=9220
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

	function SQLDate($fmt, $col=false)
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
}
