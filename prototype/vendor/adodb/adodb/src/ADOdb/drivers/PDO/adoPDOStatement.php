<?php
namespace ADOdb\drivers\PDO;

use ADOdb;
class adoPDOStatement {

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