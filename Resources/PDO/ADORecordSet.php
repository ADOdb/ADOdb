<?php

namespace ADOdb\Resources\PDO;

use \PDO;
use ADOdb\Resources\ADOFieldObject;

class ADORecordSet extends \ADOdb\Resources\ADORecordSet
{

	var $bind = false;
	var $databaseType = "pdo";
	var $dataProvider = "pdo";

	/** @var PDOStatement */
	var $_queryID;

	function __construct($queryID, $mode=false)
	{
		parent::__construct($queryID, $mode);

		switch($this->adodbFetchMode) {
			case ADODB_FETCH_NUM:
				$mode = PDO::FETCH_NUM;
				break;
			case ADODB_FETCH_ASSOC:
				$mode = PDO::FETCH_ASSOC;
				break;
			case ADODB_FETCH_BOTH:
			default:
				$mode = PDO::FETCH_BOTH;
				break;
		}
		$this->fetchMode = $mode;
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