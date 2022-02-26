<?php
/**
 * PDO Firebird driver
 *
 * This version has been tested on Firebird 3.0 and 4.0 and PHP 7
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
 * @copyright 2019 Damien Regad, Mark Newnham and the ADOdb community
 */

/**
 * Class ADODB_pdo_firebird
 */
class ADODB_pdo_firebird extends ADODB_pdo
{
	public $dialect = 3;
	
	public $metaTablesSQL = "
	SELECT LOWER(rdb\$relation_name) 
	FROM rdb\$relations 
	WHERE rdb\$relation_name NOT LIKE 'RDB\$%'";
	
	public $metaColumnsSQL = "
	SELECT LOWER(a.rdb\$field_name), a.rdb\$null_flag,
	a.rdb\$default_source, b.rdb\$field_length, b.rdb\$field_scale,
    b.rdb\$field_sub_type, b.rdb\$field_precision, b.rdb\$field_type 
	FROM rdb\$relation_fields a, rdb\$fields b 
	WHERE a.rdb\$field_source = b.rdb\$field_name 
	AND a.rdb\$relation_name = '%s' 
	ORDER BY a.rdb\$field_position ASC";
	
	public $_dropSeqSql = 'DROP SEQUENCE %s';

	var $arrayClass = 'ADORecordSet_array_pdo_firebird';



	function _init($parentDriver){}

	/**
	 * Gets the version iformation from the server
	 *
	 * @return string[]
	 */
	public function serverInfo()
	{
		$arr['dialect'] = $this->dialect;
		switch ($arr['dialect']) {
			case '':
			case '1':
				$s = 'Firebird Dialect 1';
				break;
			case '2':
				$s = 'Firebird Dialect 2';
				break;
			default:
			case '3':
				$s = 'Firebird Dialect 3';
				break;
		}
		$arr['version'] = ADOConnection::_findvers($s);
		$arr['description'] = $s;
		return $arr;
	}

	/**
	 * Returns the tables in the database.
	 *
	 * @param mixed $ttype
	 * @param bool  $showSchema
	 * @param mixed $mask
	 *
	 * @return    string[]
	 */
	public function metaTables($ttype = false, $showSchema = false, $mask = false)
	{
		$ret = ADOConnection::MetaTables($ttype, $showSchema);

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
	public function metaColumns($table, $normalize = true)
	{
		global $ADODB_FETCH_MODE;

		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

		$this->setFetchMode(ADODB_FETCH_NUM);
		$rs = $this->execute(sprintf($this->metaColumnsSQL, strtoupper($table)));

		$ADODB_FETCH_MODE = $save;
		$this->setFetchMode($save);
		if ($rs === false) {
			return false;
		}

		$retarr = array();
		$dialect3 = $this->dialect == 3;
		while (!$rs->EOF) { //print_r($rs->fields);
			$fld = new ADOFieldObject();
			$fld->name = trim($rs->fields[0]);
			$this->_ConvertFieldType($fld, $rs->fields[7], $rs->fields[3], $rs->fields[4], $rs->fields[5],
				$rs->fields[6], $dialect3);
			if (isset($rs->fields[1]) && $rs->fields[1]) {
				$fld->not_null = true;
			}
			if (isset($rs->fields[2])) {

				$fld->has_default = true;
				$d = substr($rs->fields[2], strlen('default '));
				switch ($fld->type) {
					case 'smallint':
					case 'integer':
						$fld->default_value = (int)$d;
						break;
					case 'char':
					case 'blob':
					case 'text':
					case 'varchar':
						$fld->default_value = (string)substr($d, 1, strlen($d) - 2);
						break;
					case 'double':
					case 'float':
						$fld->default_value = (float)$d;
						break;
					default:
						$fld->default_value = $d;
						break;
				}
			}
			if ((isset($rs->fields[5])) && ($fld->type == 'blob')) {
				$fld->sub_type = $rs->fields[5];
			} else {
				$fld->sub_type = null;
			}
			if ($ADODB_FETCH_MODE == ADODB_FETCH_NUM) {
				$retarr[] = $fld;
			} else {
				$retarr[strtoupper($fld->name)] = $fld;
			}

			$rs->MoveNext();
		}
		$rs->Close();
		if (empty($retarr)) {
			return false;
		} else {
			return $retarr;
		}
	}

	/**
	 * List indexes on a table as an array.
	 * @param table  table name to query
	 * @param primary true to only show primary keys. Not actually used for most databases
	 *
	 * @return array of indexes on current table. Each element represents an index, and is itself an associative array.
	 *
	 * Array(
	 *   [name_of_index] => Array(
	 *     [unique] => true or false
	 *     [columns] => Array(
	 *       [0] => firstname
	 *       [1] => lastname
	 *     )
	 *   )
	 * )
	 */
	public function metaIndexes($table, $primary = false, $owner = false)
	{
		// save old fetch mode
		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		if ($this->fetchMode !== false) {
			$savem = $this->SetFetchMode(false);
		}
		$table = strtoupper($table);
		$sql = "SELECT * FROM RDB\$INDICES WHERE RDB\$RELATION_NAME = '" . $table . "'";
		if (!$primary) {
			$sql .= " AND RDB\$INDEX_NAME NOT LIKE 'RDB\$%'";
		} else {
			$sql .= " AND RDB\$INDEX_NAME NOT LIKE 'RDB\$FOREIGN%'";
		}

		// get index details
		$rs = $this->Execute($sql);
		if (!is_object($rs)) {
			// restore fetchmode
			if (isset($savem)) {
				$this->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			return false;
		}

		$indexes = array();
		while ($row = $rs->FetchRow()) {
			$index = $row[0];
			if (!isset($indexes[$index])) {
				if (is_null($row[3])) {
					$row[3] = 0;
				}
				$indexes[$index] = array(
					'unique' => ($row[3] == 1),
					'columns' => array()
				);
			}
			$sql = "SELECT * FROM RDB\$INDEX_SEGMENTS WHERE RDB\$INDEX_NAME = '" . $index . "' ORDER BY RDB\$FIELD_POSITION ASC";
			$rs1 = $this->Execute($sql);
			while ($row1 = $rs1->FetchRow()) {
				$indexes[$index]['columns'][$row1[2]] = $row1[1];
			}
		}
		// restore fetchmode
		if (isset($savem)) {
			$this->SetFetchMode($savem);
		}
		$ADODB_FETCH_MODE = $save;

		return $indexes;
	}

	/**
	 * Return a list of Primary Keys for a specified table
	 *
	 * We don't use db2_statistics as the function does not seem to play
	 * well with mixed case table names
	 *
	 * @param string   $table
	 * @param bool     $primary    (optional) only return primary keys
	 * @param bool     $owner      (optional) not used in this driver
	 *
	 * @return string[]    Array of indexes
	 */
	public function metaPrimaryKeys($table, $owner_notused = false, $internalKey = false)
	{
		if ($internalKey) {
			return array('RDB$DB_KEY');
		}

		$table = strtoupper($table);

		$sql = 'SELECT S.RDB$FIELD_NAME AFIELDNAME
	FROM RDB$INDICES I JOIN RDB$INDEX_SEGMENTS S ON I.RDB$INDEX_NAME=S.RDB$INDEX_NAME
	WHERE I.RDB$RELATION_NAME=\'' . $table . '\' and I.RDB$INDEX_NAME like \'RDB$PRIMARY%\'
	ORDER BY I.RDB$INDEX_NAME,S.RDB$FIELD_POSITION';

		$a = $this->GetCol($sql, false, true);
		if ($a && sizeof($a) > 0) {
			return $a;
		}
		return false;
	}

	/**
	 * Creates a sequence starting at the required number
	 * 
	 * @param	string	$seqname
	 * @param	int		$startID
	 * 
	 * @return bool success
	 */
	public function createSequence($seqname = 'adodbseq', $startID = 1)
	{
		$ok = $this->execute("CREATE SEQUENCE $seqname");
		if (!$ok) {
			return false;
		}

		return $this->execute("ALTER SEQUENCE $seqname RESTART WITH " . ($startID - 1));
	}

	
	/**
	 * Generates a sequence id and stores it in $this->genID.
	 *
	 * GenID is only available if $this->hasGenID = true;
	 *
	 * @param string $seqname Name of sequence to use
	 * @param int    $startID If sequence does not exist, start at this ID
	 *
	 * @return int Sequence id, 0 if not supported
	 */
	public function genId($seqname = 'adodbseq', $startID = 1)
	{
		$getnext = ("SELECT Gen_ID($seqname,1) FROM RDB\$DATABASE");
		$rs = @$this->execute($getnext);
		if (!$rs) {
			$this->execute(("CREATE SEQUENCE $seqname"));
			$this->execute("ALTER SEQUENCE $seqname RESTART WITH " . ($startID - 1) . ';');
			$rs = $this->execute($getnext);
		}
		if ($rs && !$rs->EOF) {
			$this->genID = (integer)reset($rs->fields);
		} else {
			$this->genID = 0; // false
		}

		if ($rs) {
			$rs->Close();
		}

		return $this->genID;
	}

	/**
	 * Select a limited number of rows.
	 *
	 * @param string     $sql
	 * @param int        $offset     Row to start calculations from (1-based)
	 * @param int        $nrows      Number of rows to get
	 * @param array|bool $inputarr   Array of bind variables
	 * @param int        $secs2cache Private parameter only used by jlim
	 *
	 * @return ADORecordSet The recordset ($rs->databaseType == 'array')
	 */
	public function selectLimit($sql, $nrows = -1, $offset = -1, $inputarr = false, $secs = 0)
	{
		$nrows = (integer)$nrows;
		$offset = (integer)$offset;
		$str = 'SELECT ';
		if ($nrows >= 0) {
			$str .= "FIRST $nrows ";
		}
		$str .= ($offset >= 0) ? "SKIP $offset " : '';

		$sql = preg_replace('/^[ \t]*select/i', $str, $sql);
		if ($secs) {
			$rs = $this->cacheExecute($secs, $sql, $inputarr);
		} else {
			$rs = $this->execute($sql, $inputarr);
		}

		return $rs;
	}

	/**
	 * Sets the appropriate type into the $fld variable
	 *
	 * @param ADOFieldObject $fld By reference
	 * @param int            $ftype
	 * @param int            $flen
	 * @param int            $fscale
	 * @param int            $fsubtype
	 * @param int            $fprecision
	 * @param bool           $dialect3
	 */
	private function _convertFieldType(&$fld, $ftype, $flen, $fscale, $fsubtype, $fprecision, $dialect3)
	{
		$fscale = abs($fscale);
		$fld->max_length = $flen;
		$fld->scale = null;
		switch ($ftype) {
			case 7:
			case 8:
				if ($dialect3) {
					switch ($fsubtype) {
						case 0:
							$fld->type = ($ftype == 7 ? 'smallint' : 'integer');
							break;
						case 1:
							$fld->type = 'numeric';
							$fld->max_length = $fprecision;
							$fld->scale = $fscale;
							break;
						case 2:
							$fld->type = 'decimal';
							$fld->max_length = $fprecision;
							$fld->scale = $fscale;
							break;
					} // switch
				} else {
					if ($fscale != 0) {
						$fld->type = 'decimal';
						$fld->scale = $fscale;
						$fld->max_length = ($ftype == 7 ? 4 : 9);
					} else {
						$fld->type = ($ftype == 7 ? 'smallint' : 'integer');
					}
				}
				break;
			case 16:
				if ($dialect3) {
					switch ($fsubtype) {
						case 0:
							$fld->type = 'decimal';
							$fld->max_length = 18;
							$fld->scale = 0;
							break;
						case 1:
							$fld->type = 'numeric';
							$fld->max_length = $fprecision;
							$fld->scale = $fscale;
							break;
						case 2:
							$fld->type = 'decimal';
							$fld->max_length = $fprecision;
							$fld->scale = $fscale;
							break;
					} // switch
				}
				break;
			case 10:
				$fld->type = 'float';
				break;
			case 14:
				$fld->type = 'char';
				break;
			case 27:
				if ($fscale != 0) {
					$fld->type = 'decimal';
					$fld->max_length = 15;
					$fld->scale = 5;
				} else {
					$fld->type = 'double';
				}
				break;
			case 35:
				if ($dialect3) {
					$fld->type = 'timestamp';
				} else {
					$fld->type = 'date';
				}
				break;
			case 12:
				$fld->type = 'date';
				break;
			case 13:
				$fld->type = 'time';
				break;
			case 37:
				$fld->type = 'varchar';
				break;
			case 40:
				$fld->type = 'cstring';
				break;
			case 261:
				$fld->type = 'blob';
				$fld->max_length = -1;
				break;
		} // switch
	}
}
