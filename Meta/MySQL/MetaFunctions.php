<?php

/**
 * Datadict
 *
 */

namespace ADOdb\Meta\MySQL;

$path = ADODB_DIR."/Meta/MetaFunctions.php";
include_once($path);

class MetaFunctions extends \ADOdb\Meta\MetaFunctions {

	var $metaTablesSQL = /** @lang text */
		"SELECT
			TABLE_NAME,
			CASE WHEN TABLE_TYPE = 'VIEW' THEN 'V' ELSE 'T' END
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_SCHEMA=";

	/**
	 * Returns information about stored procedures and stored functions.
	 *
	 * @param string|bool $procedureNamePattern (Optional) Only look for procedures/functions with a name matching this pattern.
	 * @param null $catalog (Optional) Unused.
	 * @param null $schemaPattern (Optional) Unused.
	 *
	 * @return array
	 */
	function MetaProcedures($db, $procedureNamePattern = false, $catalog  = null, $schemaPattern  = null)
	{
		// save old fetch mode
		global $ADODB_FETCH_MODE;

		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

		if ($db->fetchMode !== FALSE) {
			$savem = $db->setFetchMode(FALSE);
		}

		$procedures = array ();

		// get index details

		$likepattern = '';
		if ($procedureNamePattern) {
			$likepattern = " LIKE '".$procedureNamePattern."'";
		}
		$rs = $db->execute('SHOW PROCEDURE STATUS'.$likepattern);
		if (is_object($rs)) {

			// parse index data into array
			while ($row = $rs->fetchRow()) {
				$procedures[$row[1]] = array(
					'type' => 'PROCEDURE',
					'catalog' => '',
					'schema' => '',
					'remarks' => $row[7],
				);
			}
		}

		$rs = $db->execute('SHOW FUNCTION STATUS'.$likepattern);
		if (is_object($rs)) {
			// parse index data into array
			while ($row = $rs->fetchRow()) {
				$procedures[$row[1]] = array(
					'type' => 'FUNCTION',
					'catalog' => '',
					'schema' => '',
					'remarks' => $row[7]
				);
			}
		}

		// restore fetchmode
		if (isset($savem)) {
				$db->setFetchMode($savem);
		}
		$ADODB_FETCH_MODE = $save;

		return $procedures;
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
	function MetaTables($db, $ttype = false, $showSchema = false, $mask = false)
	{
		$save = $this->metaTablesSQL;
		if ($showSchema && is_string($showSchema)) {
			$this->metaTablesSQL .= $db->qstr($showSchema);
		} else {
			$this->metaTablesSQL .= "schema()";
		}

		if ($mask) {
			$mask = $db->qstr($mask);
			$this->metaTablesSQL .= " AND table_name LIKE $mask";
		}
		$ret = ADOConnection::metaTables($ttype,$showSchema);

		$this->metaTablesSQL = $save;
		return $ret;
	}

	/**
	 * Return information about a table's foreign keys.
	 *
	 * @param string $table The name of the table to get the foreign keys for.
	 * @param string|bool $owner (Optional) The database the table belongs to, or false to assume the current db.
	 * @param string|bool $upper (Optional) Force uppercase table name on returned array keys.
	 * @param bool $associative (Optional) Whether to return an associate or numeric array.
	 *
	 * @return array|bool An array of foreign keys, or false no foreign keys could be found.
	 */
	public function metaForeignKeys($db, $table, $owner = '', $upper = false, $associative = false)
	{
		global $ADODB_FETCH_MODE;

		if ($ADODB_FETCH_MODE == ADODB_FETCH_ASSOC
		|| $db->fetchMode == ADODB_FETCH_ASSOC)
			$associative = true;

		$savem = $ADODB_FETCH_MODE;
		$db->setFetchMode(ADODB_FETCH_ASSOC);

		if ( !empty($owner) ) {
			$table = "$owner.$table";
		}

		$showCreate = $db->getRow(
				sprintf('SHOW CREATE TABLE `%s`', $table)
		);

		if ( !$showCreate || !is_array($showCreate) ) {
			/*
			* Invalid table or owner provided
			*/
			$db->setFetchMode($savem);
			return false;
		}

		$a_create_table = array_change_key_case($showCreate, CASE_UPPER);

		$db->setFetchMode($savem);

		$create_sql = $a_create_table["CREATE TABLE"] ?? $a_create_table["CREATE VIEW"];

		$matches = array();

		if (!preg_match_all(
			"/FOREIGN KEY \(`(.*?)`\) REFERENCES `(.*?)` \(`(.*?)`\)/", 
			$create_sql, 
			$matches
			)
		) {
				return false;
		}
		
		$foreign_keys = array();
		$num_keys = count($matches[0]);
		for ( $i = 0; $i < $num_keys; $i ++ ) {
			$my_field  = explode('`, `', $matches[1][$i]);
			$ref_table = $matches[2][$i];
			$ref_field = explode('`, `', $matches[3][$i]);

			if ( $upper ) {
				$ref_table = strtoupper($ref_table);
			}

			// see https://sourceforge.net/p/adodb/bugs/100/
			if (!isset($foreign_keys[$ref_table])) {
				$foreign_keys[$ref_table] = array();
			}
			$num_fields = count($my_field);
			for ( $j = 0; $j < $num_fields; $j ++ ) {
				if ( $associative ) {
					$foreign_keys[$ref_table][$ref_field[$j]] = $my_field[$j];
				} else {
					$foreign_keys[$ref_table][] = $my_field[$j] . '=' . $ref_field[$j];
				}
			}
		}

		return $foreign_keys;
	}

	/**
	 * Return an array of information about a table's columns.
	 *
	 * @param string $table The name of the table to get the column info for.
	 * @param bool $normalize (Optional) Unused.
	 *
	 * @return ADOFieldObject[]|bool An array of info for each column, or false if it could not determine the info.
	 */
	function MetaColumns($db, $table, $normalize = true)
	{
		if (!$this->metaColumnsSQL)
			return false;

		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		if ($db->fetchMode !== false)
			$savem = $db->SetFetchMode(false);
		/*
		* Return assoc array where key is column name, value is column type
		*    [1] => int unsigned
		*/

		$SQL = "SELECT column_name, column_type
				  FROM information_schema.columns
				 WHERE table_schema='$db->database'
				   AND table_name='$table'";

		$schemaArray = $db->getAssoc($SQL);
		if (is_array($schemaArray)) {
			$schemaArray = array_change_key_case($schemaArray,CASE_LOWER);
			$rs = $db->Execute(sprintf($db->metaColumnsSQL,$table));
		}

		if (isset($savem)) $db->SetFetchMode($savem);
		$ADODB_FETCH_MODE = $save;
		if (!is_object($rs))
			return false;

		$retarr = array();
		while (!$rs->EOF) {
			$fld = new ADOFieldObject();
			$fld->name = $rs->fields[0];

			/*
			* Type from information_schema returns
			* the same format in V8 mysql as V5
			*/
			$type = $schemaArray[strtolower($fld->name)];

			// split type into type(length):
			$fld->scale = null;
			if (preg_match("/^(.+)\((\d+),(\d+)/", $type, $query_array)) {
				$fld->type = $query_array[1];
				$fld->max_length = is_numeric($query_array[2]) ? $query_array[2] : -1;
				$fld->scale = is_numeric($query_array[3]) ? $query_array[3] : -1;
			} elseif (preg_match("/^(.+)\((\d+)/", $type, $query_array)) {
				$fld->type = $query_array[1];
				$fld->max_length = is_numeric($query_array[2]) ? $query_array[2] : -1;
			} elseif (preg_match("/^(enum)\((.*)\)$/i", $type, $query_array)) {
				$fld->type = $query_array[1];
				$arr = explode(",",$query_array[2]);
				$fld->enums = $arr;
				$zlen = max(array_map("strlen",$arr)) - 2; // PHP >= 4.0.6
				$fld->max_length = ($zlen > 0) ? $zlen : 1;
			} else {
				$fld->type = $type;
				$fld->max_length = -1;
			}

			$fld->not_null = ($rs->fields[2] != 'YES');
			$fld->primary_key = ($rs->fields[3] == 'PRI');
			$fld->auto_increment = (strpos($rs->fields[5], 'auto_increment') !== false);
			$fld->binary = (strpos($type,'blob') !== false);
			$fld->unsigned = (strpos($type,'unsigned') !== false);
			$fld->zerofill = (strpos($type,'zerofill') !== false);

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
			$rs->moveNext();
		}

		$rs->close();
		return $retarr;
	}


}