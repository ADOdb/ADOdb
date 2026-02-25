<?php

/**
 * Datadict
 *
 */

namespace ADOdb\DataDictionary\MySQLi;

$path = ADODB_DIR."/DataDictionary/DataDict.php";
print "\n$path\n";
include_once($path);

class DataDict extends \ADOdb\DataDictionary\DataDict {

	var $databaseType = 'mysql';
	var $alterCol = ' MODIFY COLUMN';
	var $alterTableAddIndex = true;
	var $dropTable = 'DROP TABLE IF EXISTS %s'; // requires mysql 3.22 or later

	var $dropIndex = 'DROP INDEX %s ON %s';

	public $blobAllowsNotNull = true;

	function __construct()
	{
		return parent::__construct();
	}


	function metaType($t, $len=-1, $fieldobj=false)
	{


		if (is_object($t)) {
			$fieldobj = $t;
			$t = $fieldobj->type;
			$len = $fieldobj->max_length;
		}
		$is_serial = is_object($fieldobj) && $fieldobj->primary_key && $fieldobj->auto_increment;

		$len = -1; // mysql max_length is not accurate

		$t = strtoupper($t);

		if (array_key_exists($t, $this->connection->customActualTypes)) {
			return $this->connection->customActualTypes[$t];
		}

		switch ($t) {
			case 'STRING':
			case 'CHAR':
			case 'VARCHAR':
			case 'TINYBLOB':
			case 'TINYTEXT':
			case 'ENUM':
			/** @noinspection PhpMissingBreakStatementInspection */
			case 'SET':
				if ($len <= $this->blobSize) {
					return 'C';
				}
				// Fall through

			case 'TEXT':
			case 'LONGTEXT':
			case 'MEDIUMTEXT':
				return 'X';

			// php_mysql extension always returns 'blob' even if 'text'
			// so we have to check whether binary...
			case 'IMAGE':
			case 'LONGBLOB':
			case 'BLOB':
			case 'MEDIUMBLOB':
				return !empty($fieldobj->binary) ? 'B' : 'X';

			case 'YEAR':
			case 'DATE':
				return 'D';

			case 'TIME':
			case 'DATETIME':
			case 'TIMESTAMP':
				return 'T';

			case 'FLOAT':
			case 'DOUBLE':
				return 'F';

			case 'INT':
			case 'INTEGER':
				return $is_serial ? 'R' : 'I';
			case 'TINYINT':
				return $is_serial ? 'R' : 'I1';
			case 'SMALLINT':
				return $is_serial ? 'R' : 'I2';
			case 'MEDIUMINT':
				return $is_serial ? 'R' : 'I4';
			case 'BIGINT':
				return $is_serial ? 'R' : 'I8';
			default:

				return ADODB_DEFAULT_METATYPE;
		}
	}

	function actualType($meta)
	{
		$meta = parent::actualType($meta);

		switch ($meta) {
			case 'C':
			case 'C2':
				return 'VARCHAR';
			case 'XL':
			case 'X2':
				return 'LONGTEXT';
			case 'X':
				return 'TEXT';

			case 'B':
				return 'LONGBLOB';

			case 'D':
				return 'DATE';
			case 'TS':
			case 'T':
				return 'DATETIME';
			case 'L':
				return 'TINYINT';

			case 'R':
			case 'I4':
			case 'I':
				return 'INTEGER';
			/** @noinspection PhpDuplicateSwitchCaseBodyInspection */
			case 'I1':
				return 'TINYINT';
			case 'I2':
				return 'SMALLINT';
			case 'I8':
				return 'BIGINT';

			case 'F':
				return 'DOUBLE';
			case 'N':
				return 'NUMERIC';

			default:
				return $meta;
		}
	}

	function _createSuffix($fname, &$ftype, $fnotnull, $fdefault, $fautoinc, $fconstraint, $funsigned, $fprimary, &$pkey)
	{
		$suffix = '';
		if ($funsigned) {
			$suffix .= ' UNSIGNED';
		}
		if ($fnotnull) {
			$suffix .= ' NOT NULL';
		}
		if (strlen($fdefault)) {
			$suffix .= " DEFAULT $fdefault";
		}
		if ($fautoinc) {
			$suffix .= ' AUTO_INCREMENT';
		}
		if ($fconstraint) {
			$suffix .= ' ' . $fconstraint;
		}
		return $suffix;
	}

	/*
	CREATE [TEMPORARY] TABLE [IF NOT EXISTS] tbl_name [(create_definition,...)]
		[table_options] [select_statement]
		create_definition:
		col_name type [NOT NULL | NULL] [DEFAULT default_value] [AUTO_INCREMENT]
		[PRIMARY KEY] [reference_definition]
		or PRIMARY KEY (index_col_name,...)
		or KEY [index_name] (index_col_name,...)
		or INDEX [index_name] (index_col_name,...)
		or UNIQUE [INDEX] [index_name] (index_col_name,...)
		or FULLTEXT [INDEX] [index_name] (index_col_name,...)
		or [CONSTRAINT symbol] FOREIGN KEY [index_name] (index_col_name,...)
		[reference_definition]
		or CHECK (expr)
	*/

	/*
	CREATE [UNIQUE|FULLTEXT] INDEX index_name
		ON tbl_name (col_name[(length)],... )
	*/

	function _indexSQL($idxname, $tabname, $flds, $idxoptions)
	{
		$sql = array();

		if (isset($idxoptions['REPLACE']) || isset($idxoptions['DROP'])) {
			if ($this->alterTableAddIndex) {
				$sql[] = "ALTER TABLE $tabname DROP INDEX $idxname";
			} else {
				$sql[] = sprintf($this->dropIndex, $idxname, $tabname);
			}

			if (isset($idxoptions['DROP'])) {
				return $sql;
			}
		}

		if (empty($flds)) {
			return $sql;
		}

		if (isset($idxoptions['FULLTEXT'])) {
			$unique = ' FULLTEXT';
		} elseif (isset($idxoptions['UNIQUE'])) {
			$unique = ' UNIQUE';
		} else {
			$unique = '';
		}

		if (is_array($flds)) {
			$flds = implode(', ', $flds);
		}

		if ($this->alterTableAddIndex) {
			$s = "ALTER TABLE $tabname ADD $unique INDEX $idxname ";
		} else {
			$s = 'CREATE' . $unique . ' INDEX ' . $idxname . ' ON ' . $tabname;
		}

		$s .= ' (' . $flds . ')';

		if (isset($idxoptions[$this->upperName])) {
			$s .= $idxoptions[$this->upperName];
		}

		$sql[] = $s;

		return $sql;
	}

	/**
	 * Rename one column.
	 *
	 * MySQL < 8.0 does not support the standard `RENAME COLUMN` SQL syntax,
	 * so the $flds parameter must be provided.
	 *
	 * @param string $tabname   Table name.
	 * @param string $oldcolumn Column to be renamed.
	 * @param string $newcolumn New column name.
	 * @param string $flds      Complete column definition string like for {@see addColumnSQL};
	 *                          This is currently only used by MySQL < 8.0. Defaults to ''.
	 *
	 * @return array SQL statements.
	 */
	function renameColumnSQL($tabname, $oldcolumn, $newcolumn, $flds='')
	{
		$version = $this->connection->ServerInfo();

		if (version_compare($version['version'], '8.0', '<')) {
			$this->renameColumn = 'ALTER TABLE %s CHANGE COLUMN %s %s %s';
		} else {
			$flds = '';
		}

		return parent::renameColumnSQL($tabname, $oldcolumn, $newcolumn, $flds);
	}

}