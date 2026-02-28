<?php
/**
 * Data Dictionary for MySQL.
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
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
 */

// security - hide paths
if (!defined('ADODB_DIR')) die();

class ADODB2_mysql extends ADODB_DataDict {
	var $databaseType = 'mysql';
	var $alterCol = ' MODIFY COLUMN';
	var $alterTableAddIndex = true;
	var $dropTable = 'DROP TABLE IF EXISTS %s'; // requires mysql 3.22 or later

	var $dropIndex = 'DROP INDEX %s ON %s';

	public $blobAllowsNotNull = true;


	/**
	 * Returns the meta type for a given type and length.
	 *
	 * @param mixed  $t        The object to test.
	 * @param int    $len      The length of the field, if applicable.
	 * @param object $fieldobj The field object, if available.
	 *
	 * @return string
	 */
	public function metaType($t, $len = -1, $fieldobj = false)
	{
		if (is_object($t)) {
			$fieldobj = $t;
		} else if (is_string($t) && !$fieldobj){
			if ($this->connection->debug) {
				ADOConnection::outp('Passing a string to metaType is no longer permitted. Pass the field object instead');
			}
			return ADODB_DEFAULT_METATYPE;
		}

		if (is_object($fieldobj)) {
			$t = (int)$fieldobj->type;
			$len = $fieldobj->max_length;
			
		}

		$is_serial = is_object($fieldobj) && $fieldobj->primary_key && $fieldobj->auto_increment;

		$t = strtoupper($t);

		if (array_key_exists($t, $this->connection->customActualTypes)) {
			return $this->connection->customActualTypes[$t];
		}


		/*

0 = MYSQLI_TYPE_DECIMAL
1 = MYSQLI_TYPE_CHAR
1 = MYSQLI_TYPE_TINY
2 = MYSQLI_TYPE_SHORT
3 = MYSQLI_TYPE_LONG
4 = MYSQLI_TYPE_FLOAT
5 = MYSQLI_TYPE_DOUBLE
6 = MYSQLI_TYPE_NULL
7 = MYSQLI_TYPE_TIMESTAMP
8 = MYSQLI_TYPE_LONGLONG
9 = MYSQLI_TYPE_INT24
10 = MYSQLI_TYPE_DATE
11 = MYSQLI_TYPE_TIME
12 = MYSQLI_TYPE_DATETIME
13 = MYSQLI_TYPE_YEAR
14 = MYSQLI_TYPE_NEWDATE
245 = MYSQLI_TYPE_JSON
247 = MYSQLI_TYPE_ENUM
248 = MYSQLI_TYPE_SET
249 = MYSQLI_TYPE_TINY_BLOB
250 = MYSQLI_TYPE_MEDIUM_BLOB
251 = MYSQLI_TYPE_LONG_BLOB
252 = MYSQLI_TYPE_BLOB
253 = MYSQLI_TYPE_VAR_STRING
254 = MYSQLI_TYPE_STRING
255 = MYSQLI_TYPE_GEOMETRY
*/

		if (is_object($fieldobj)) {
			if ($t == 254) {
				if (!$fieldobj->binary) {
					if (in_array($fieldobj->flags, [256, 2048])) {
						return 'E';
					} else {
						return 'C';
					}
				} else {
					return 'C2';
				}
			} elseif ($t == 253) {
				if (!$fieldobj->binary) {
					return 'C';
				} else {
					return 'C2';
				}
			} elseif ($t == 252) {
				if (!$fieldobj->binary) {
					if ($fieldobj->flags == 16) {
						return 'X';
					} else {
						return 'C';
					}
				} else {
					return 'B';
				}
			} elseif (in_array($t, [MYSQLI_TYPE_CHAR, 16])) {
				if (!$fieldobj->binary) {
					return 'L';
				}
			} elseif (in_array($t, [MYSQLI_TYPE_SHORT])) {
				if (!$fieldobj->binary) {
					return 'I2';
				}
			} elseif (in_array($t, [MYSQLI_TYPE_INT24])) {
				if (!$fieldobj->binary) {
					return 'I4';
				}
			} elseif (in_array($t, [MYSQLI_TYPE_LONG])) {
				if (!$fieldobj->binary) {
					return 'I';
				}
			} elseif ($t == MYSQLI_TYPE_LONGLONG) {
				if (!$fieldobj->binary) {
					return 'I8';
				}
			} elseif (in_array($t, [MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE])) {
				if (!$fieldobj->binary) {
					return 'F';
				}
			} elseif ($t == 246) {
				if (!$fieldobj->binary) {
					return 'N';
				}
			} elseif (in_array(
					$t, [
						MYSQLI_TYPE_TIMESTAMP,
						MYSQLI_TYPE_TIME,
						MYSQLI_TYPE_DATETIME,
						27
						]
					)
				) {
				return 'T';
			} elseif (in_array(
						$t, 
						[ MYSQLI_TYPE_DATE,	MYSQLI_TYPE_YEAR ]
						)
					) {
				return 'D';
			}
		}

		return ADODB_DEFAULT_METATYPE;
	}

	/**
	 * Returns the actual type for a given meta type.
	 *
	 * @param string $meta The meta type to convert:
	 *
	 * @param [type] $meta
	 * @return void
	 */
	public function actualType($meta)
	{
		$meta = parent::actualType($meta);

		switch ($meta) {
			case 'C':
				return 'VARCHAR';
			case 'C2':
				return 'NVARCHAR';
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
				return 'BOOLEAN';

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
			case 'E':
				return 'ENUM';

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
