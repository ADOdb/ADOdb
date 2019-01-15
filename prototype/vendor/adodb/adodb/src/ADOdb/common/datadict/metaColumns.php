<?php
namespace ADOdb\common\datadict;

use ADOdb;

/**
* Class placeholder for autoloading
*
*/
class metaColumns extends \ADOdb\common\ADOdbMethod
{
	
	protected $metaColumnsSQL;
	
	/**
	* initializes the metaFunctions function and places the result`
	*  in the correct variable
	*
	* @param	obj		$connection	The database connection
	* @param	str 	$table		The table to query
	* @param 	bool	$normalize
	*
	* @return mixed
	*/
	public function __construct($connection, $table, $normalize=true)
	{
		global $ADODB_FETCH_MODE;

		if (!empty($connection->metaColumnsSQL)) {
			$schema = false;
			$connection->_findschema($table,$schema);

			$save = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
			if ($connection->fetchMode !== false) {
				$savem = $connection->SetFetchMode(false);
			}
			$rs = $connection->execute(sprintf($connection->metaColumnsSQL,($normalize)?strtoupper($table):$table));
			if (isset($savem)) {
				$connection->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			if ($rs === false || $rs->EOF) {
				return false;
			}

			$retarr = array();
			while (!$rs->EOF) { //print_r($rs->fields);
				$fld = new ADOFieldObject();
				$fld->name = $rs->fields[0];
				$fld->type = $rs->fields[1];
				if (isset($rs->fields[3]) && $rs->fields[3]) {
					if ($rs->fields[3]>0) {
						$fld->max_length = $rs->fields[3];
					}
					$fld->scale = $rs->fields[4];
					if ($fld->scale>0) {
						$fld->max_length += 1;
					}
				} else {
					$fld->max_length = $rs->fields[2];
				}

				if ($ADODB_FETCH_MODE == ADODB_FETCH_NUM) {
					$retarr[] = $fld;
				} else {
					$retarr[strtoupper($fld->name)] = $fld;
				}
				$rs->MoveNext();
			}
			$rs->Close();
			$this->methodResult = $retarr;
		}
	}
	
		
	final protected function _findschema(&$table,&$schema) {
		if (!$schema && ($at = strpos($table,'.')) !== false) {
			$schema = substr($table,0,$at);
			$table = substr($table,$at+1);
		}
	}
}