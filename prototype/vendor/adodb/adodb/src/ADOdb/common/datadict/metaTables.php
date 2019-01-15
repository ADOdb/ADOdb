<?php
namespace ADOdb\common\datadict;
use ADOdb;

class metaTables extends \ADOdb\common\ADOdbMethod
{

	protected $metaTablesSQL = '';

	function __construct($connection,$ttype=false,$showSchema=false,$mask=false){}

	/**
	 * @param ttype can either be 'VIEW' or 'TABLE' or false.
	 *		If false, both views and tables are returned.
	 *		"VIEW" returns only views
	 *		"TABLE" returns only tables
	 * @param showSchema returns the schema/user with the table name, eg. USER.TABLE
	 * @param mask  is the input mask - only supported by oci8 and postgresql
	 *
	 * @return  array of tables for current database.
	 */
	protected function retrieveMetaTables($connection, $ttype,$showSchema,$mask)
	{
		global $ADODB_FETCH_MODE;

print "MT";
		if ($mask) {
			return false;
		}
		if ($this->metaTablesSQL) {
			$save = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

			if ($connection->fetchMode !== false) {
				$savem = $connection->SetFetchMode(false);
			}
			$rs = $connection->execute($this->metaTablesSQL);
			if (isset($savem)) {
				$connection->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;

			if ($rs === false) {
				return false;
			}
			$arr = $rs->GetArray();
			$arr2 = array();

			if ($hast = ($ttype && isset($arr[0][1]))) {
				$showt = strncmp($ttype,'T',1);
			}

			for ($i=0; $i < sizeof($arr); $i++) {
				if ($hast) {
					if ($showt == 0) {
						if (strncmp($arr[$i][1],'T',1) == 0) {
							$arr2[] = trim($arr[$i][0]);
						}
					} else {
						if (strncmp($arr[$i][1],'V',1) == 0) {
							$arr2[] = trim($arr[$i][0]);
						}
					}
				} else
					$arr2[] = trim($arr[$i][0]);
			}
			$rs->Close();
			$this->methodResult = $arr2;
		}
		
	}
}