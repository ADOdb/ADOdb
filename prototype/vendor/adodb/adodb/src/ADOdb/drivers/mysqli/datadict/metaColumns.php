<?php
namespace ADOdb\drivers\mysqli\datadict;

use ADOdb;


class metaColumns extends \ADOdb\common\datadict\metaColumns
{
	
	function __construct($connection, $table, $normalize=true)
	{
		
		$this->_findschema($table, $schema);
		if ($schema) {
			$dbName = $connection->database;
			$connection->SelectDB($schema);
		}
		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

		if ($connection->fetchMode !== false) {
			$savem = $connection->SetFetchMode(false);
		}
		$rs = $connection->Execute(sprintf($connection->metaColumnsSQL, $table));

		if ($schema) {
			$connection->SelectDB($dbName);
		}

		if (isset($savem)) {
			$connection->SetFetchMode($savem);
		}

		$ADODB_FETCH_MODE = $save;
		if (!is_object($rs)) {
			$false = false;
			return $false;
		}

		$retarr = array();
		while (!$rs->EOF){
			$fld = new ADOdb\common\adoFieldObject();
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
		
		$this->methodResult = $retarr;
	}
}