<?php
/**
*
* Common PDO driver
*
* @version   v6.0.0-dev  ??-???-2019
* @copyright (c) 2019 Mark Newnham,Damien Regad and the ADOdb community
*
* Released under both BSD license and Lesser GPL library license. 
* You can choose which license you prefer.
*/
namespace ADOdb\drivers\PDO;

use ADOdb;


class adoRecordSet extends ADOdb\common\adoRecordSet {

	var $bind = false;
	var $databaseType = "pdo";
	var $dataProvider = "pdo";


	public function __construct($id,$mode=false)
	{
		if ($mode === false) {
			global $ADODB_FETCH_MODE;
			$mode = $ADODB_FETCH_MODE;
		}
		$this->adodbFetchMode = $mode;
		switch($mode) {
		case ADODB_FETCH_NUM: $mode = \PDO::FETCH_NUM; break;
		case ADODB_FETCH_ASSOC:  $mode = \PDO::FETCH_ASSOC; break;

		case ADODB_FETCH_BOTH:
		default: $mode = \PDO::FETCH_BOTH; break;
		}
		$this->fetchMode = $mode;

		$this->_queryID = $id;
		parent::__construct($id);
	}


	function Init()
	{
		if ($this->_inited) {
			return;
		}
		$this->_inited = true;
		if ($this->_queryID) {
			@$this->_initrs();
		}
		else {
			$this->_numOfRows = 0;
			$this->_numOfFields = 0;
		}
		if ($this->_numOfRows != 0 && $this->_currentRow == -1) {
			$this->_currentRow = 0;
			if ($this->EOF = ($this->_fetch() === false)) {
				$this->_numOfRows = 0; // _numOfRows could be -1
			}
		} else {
			$this->EOF = true;
		}
	}

	function _initrs()
	{
	global $ADODB_COUNTRECS;

		$this->_numOfRows = ($ADODB_COUNTRECS) ? @$this->_queryID->rowCount() : -1;
		if (!$this->_numOfRows) {
			$this->_numOfRows = -1;
		}
		$this->_numOfFields = $this->_queryID->columnCount();
	}

	/**
	* Returns field information
	*
	* @param int $fieldOffst
	*
	* @return obj
	*/
	public function FetchField($fieldOffset = -1)
	{
		$off=$fieldOffset+1; // offsets begin at 1

		$o = new ADOdb\common\ADOFieldObject();
		$arr = @$this->_queryID->getColumnMeta($fieldOffset);
		
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
		     $o->type = $this->adodb_pdo_type($arr['pdo_type']);
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

	function _seek($row)
	{
		return false;
	}

	function _fetch()
	{
		if (!$this->_queryID) {
			return false;
		}

		$this->fields = $this->_queryID->fetch($this->fetchMode);
		return !empty($this->fields);
	}

	function _close()
	{
		$this->_queryID = false;
	}

	function Fields($colname)
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
	
	private function adodb_pdo_type($t)
	{
		switch($t) {
		case 2: return 'VARCHAR';
		case 3: return 'BLOB';
		default: return 'NUMERIC';
		}
	}
}

