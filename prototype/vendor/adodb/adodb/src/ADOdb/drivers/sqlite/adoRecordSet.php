<?php
namespace ADOdb\drivers\sqlite;

use ADOdb;

/*--------------------------------------------------------------------------------------
		Class Name: Recordset
--------------------------------------------------------------------------------------*/

class adoRecordset extends ADOdb\adoRecordSet 
{

	var $databaseType = "sqlite3";
	var $bind = false;

	function __construct($queryID,$mode=false)
	{

		if ($mode === false) {
			global $ADODB_FETCH_MODE;
			$mode = $ADODB_FETCH_MODE;
		}
		switch($mode) {
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
		$this->adodbFetchMode = $mode;

		$this->_queryID = $queryID;

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


	function FetchField($fieldOffset = -1)
	{
		$fld = new ADOFieldObject;
		$fld->name = $this->_queryID->columnName($fieldOffset);
		$fld->type = 'VARCHAR';
		$fld->max_length = -1;
		return $fld;
	}

	function _initrs()
	{
		$this->_numOfFields = $this->_queryID->numColumns();

	}

	function Fields($colname)
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

	function _seek($row)
	{
		// sqlite3 does not implement seek
		if ($this->debug) {
			ADOConnection::outp("SQLite3 does not implement seek");
		}
		return false;
	}

	function _fetch($ignore_fields=false)
	{
		$this->fields = $this->_queryID->fetchArray($this->fetchMode);
		return !empty($this->fields);
	}

	function _close()
	{
	}


/*
	function _initrs()
	{
		$this->_numOfRows = @sqlite_num_rows($this->_queryID);
		$this->_numOfFields = @sqlite_num_fields($this->_queryID);
	}
*/
	

}
