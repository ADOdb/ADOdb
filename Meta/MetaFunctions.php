<?php

/**
 * MetaFunctions
 *
 */

namespace ADOdb\Meta;

class MetaFunctions {
    
    /** @var string SQL statement to get databases */
	var $metaDatabasesSQL = '';

	/** @var string SQL statement to get database tables */
	var $metaTablesSQL = '';

	/** @var string SQL statement to get table columns. */
	var $metaColumnsSQL;

    var $blobSize = 100;	/// any varchar/char field this size or greater is treated as a blob

    /**
	 * Get the ADOdb metatype.
	 *
	 * Many databases use different names for the same type, so we transform
	 * the native type to our standardised one, which uses 1 character codes.
	 * @see https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:dictionary_index#portable_data_types
	 *
	 * @param string|ADOFieldObject $t  Native type (usually ADOFieldObject->type)
	 *                                  It is also possible to provide an
	 *                                  ADOFieldObject here.
	 * @param int $len The field's maximum length. This is because we treat
	 *                 character fields bigger than a certain size as a 'B' (blob).
	 * @param ADOFieldObject $fieldObj Field object returned by the database driver;
	 *                                 can hold additional info (eg. primary_key for mysql).
	 *
	 * @return string The ADOdb Standard type
	 */
	function metaType($db, $t, $len = -1, $fieldObj = false) {
		if ($t instanceof \ADOFieldObject) {
			$fieldObj = $t;
			$t = $fieldObj->type;
			$len = $fieldObj->max_length;
		}

		// changed in 2.32 to hashing instead of switch stmt for speed...
		static $typeMap = array(
			'VARCHAR' => 'C',
			'VARCHAR2' => 'C',
			'CHAR' => 'C',
			'C' => 'C',
			'STRING' => 'C',
			'NCHAR' => 'C',
			'NVARCHAR' => 'C',
			'VARYING' => 'C',
			'BPCHAR' => 'C',
			'CHARACTER' => 'C',
			'INTERVAL' => 'C',  # Postgres
			'MACADDR' => 'C', # postgres
			'VAR_STRING' => 'C', # mysql
			##
			'LONGCHAR' => 'X',
			'TEXT' => 'X',
			'NTEXT' => 'X',
			'M' => 'X',
			'X' => 'X',
			'CLOB' => 'X',
			'NCLOB' => 'X',
			'LVARCHAR' => 'X',
			##
			'BLOB' => 'B',
			'IMAGE' => 'B',
			'BINARY' => 'B',
			'VARBINARY' => 'B',
			'LONGBINARY' => 'B',
			'B' => 'B',
			##
			'YEAR' => 'D', // mysql
			'DATE' => 'D',
			'D' => 'D',
			##
			'UNIQUEIDENTIFIER' => 'C', # MS SQL Server
			##
			'SMALLDATETIME' => 'T',
			'TIME' => 'T',
			'TIMESTAMP' => 'T',
			'DATETIME' => 'T',
			'DATETIME2' => 'T',
			'TIMESTAMPTZ' => 'T',
			'T' => 'T',
			'TIMESTAMP WITHOUT TIME ZONE' => 'T', // postgresql
			##
			'BOOL' => 'L',
			'BOOLEAN' => 'L',
			'BIT' => 'L',
			'L' => 'L',
			##
			'COUNTER' => 'R',
			'R' => 'R',
			'SERIAL' => 'R', // ifx
			'INT IDENTITY' => 'R',
			##
			'INT' => 'I',
			'INT2' => 'I',
			'INT4' => 'I',
			'INT8' => 'I',
			'INTEGER' => 'I',
			'INTEGER UNSIGNED' => 'I',
			'SHORT' => 'I',
			'TINYINT' => 'I',
			'SMALLINT' => 'I',
			'I' => 'I',
			##
			'LONG' => 'N', // interbase is numeric, oci8 is blob
			'BIGINT' => 'N', // this is bigger than PHP 32-bit integers
			'DECIMAL' => 'N',
			'DEC' => 'N',
			'REAL' => 'N',
			'DOUBLE' => 'N',
			'DOUBLE PRECISION' => 'N',
			'SMALLFLOAT' => 'N',
			'FLOAT' => 'N',
			'NUMBER' => 'N',
			'NUM' => 'N',
			'NUMERIC' => 'N',
			'MONEY' => 'N',

			## informix 9.2
			'SQLINT' => 'I',
			'SQLSERIAL' => 'I',
			'SQLSMINT' => 'I',
			'SQLSMFLOAT' => 'N',
			'SQLFLOAT' => 'N',
			'SQLMONEY' => 'N',
			'SQLDECIMAL' => 'N',
			'SQLDATE' => 'D',
			'SQLVCHAR' => 'C',
			'SQLCHAR' => 'C',
			'SQLDTIME' => 'T',
			'SQLINTERVAL' => 'N',
			'SQLBYTES' => 'B',
			'SQLTEXT' => 'X',
			## informix 10
			"SQLINT8" => 'I8',
			"SQLSERIAL8" => 'I8',
			"SQLNCHAR" => 'C',
			"SQLNVCHAR" => 'C',
			"SQLLVARCHAR" => 'X',
			"SQLBOOL" => 'L'
		);

		$t = strtoupper($t);
		$tmap = (isset($typeMap[$t])) ? $typeMap[$t] : ADODB_DEFAULT_METATYPE;
		switch ($tmap) {
			case 'C':
				// is the char field is too long, return as text field...
				if ($this->blobSize >= 0) {
					if ($len > $this->blobSize) {
						return 'X';
					}
				} else if ($len > 250) {
					return 'X';
				}
				return 'C';

			case 'I':
				if (!empty($fieldObj->primary_key)) {
					return 'R';
				}
				return 'I';

			case false:
				return 'N';

			case 'B':
				if (isset($fieldObj->binary)) {
					return ($fieldObj->binary) ? 'B' : 'X';
				}
				return 'B';

			case 'D':
				if (!empty($db->connection) && !empty($db->connection->datetime)) {
					return 'T';
				}
				return 'D';

			default:
				if ($t == 'LONG' && $db->dataProvider == 'oci8') {
					return 'B';
				}
				return $tmap;
		}
	}

    /*
https://msdn2.microsoft.com/en-US/ms173763.aspx
https://dev.mysql.com/doc/refman/5.0/en/innodb-transaction-isolation.html
https://www.postgresql.org/docs/8.1/interactive/sql-set-transaction.html
http://www.stanford.edu/dept/itss/docs/oracle/10g/server.101/b10759/statements_10005.htm
*/
	function MetaTransaction($mode,$db) {
		$mode = strtoupper($mode);
		$mode = str_replace('ISOLATION LEVEL ','',$mode);

		switch($mode) {

		case 'READ UNCOMMITTED':
			switch($db) {
			case 'oci8':
			case 'oracle':
				return 'ISOLATION LEVEL READ COMMITTED';
			default:
				return 'ISOLATION LEVEL READ UNCOMMITTED';
			}
			break;

		case 'READ COMMITTED':
				return 'ISOLATION LEVEL READ COMMITTED';
			break;

		case 'REPEATABLE READ':
			switch($db) {
			case 'oci8':
			case 'oracle':
				return 'ISOLATION LEVEL SERIALIZABLE';
			default:
				return 'ISOLATION LEVEL REPEATABLE READ';
			}
			break;

		case 'SERIALIZABLE':
				return 'ISOLATION LEVEL SERIALIZABLE';
			break;

		default:
			return $mode;
		}
	}

    /**
	 * return the databases that the driver can connect to.
	 * Some databases will return an empty array.
	 *
	 * @return array|false an array of database names.
	 */
	function MetaDatabases($db) {
		global $ADODB_FETCH_MODE;

		if ($this->metaDatabasesSQL) {
			$save = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

			if ($db->fetchMode !== false) {
				$savem = $db->SetFetchMode(false);
			}

			$arr = $db->GetCol($this->metaDatabasesSQL);
			if (isset($savem)) {
				$db->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;

			return $arr;
		}

		return false;
	}

	/**
	 * List procedures or functions in an array.
	 * @param procedureNamePattern  a procedure name pattern; must match the procedure name as it is stored in the database
	 * @param catalog a catalog name; must match the catalog name as it is stored in the database;
	 * @param schemaPattern a schema name pattern;
	 *
	 * @return array of procedures on current database.
	 *
	 * Array(
	 *   [name_of_procedure] => Array(
	 *     [type] => PROCEDURE or FUNCTION
	 *     [catalog] => Catalog_name
	 *     [schema] => Schema_name
	 *     [remarks] => explanatory comment on the procedure
	 *   )
	 * )
	 */
	function MetaProcedures($db, $procedureNamePattern = null, $catalog  = null, $schemaPattern  = null) {
		return false;
	}


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
	function MetaTables($db, $ttype=false,$showSchema=false,$mask=false) {
		global $ADODB_FETCH_MODE;

		if ($mask) {
			return false;
		}
		if ($this->metaTablesSQL) {
			$save = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

			if ($db->fetchMode !== false) {
				$savem = $db->SetFetchMode(false);
			}

			$rs = $db->Execute($this->metaTablesSQL);
			if (isset($savem)) {
				$db->SetFetchMode($savem);
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
			return $arr2;
		}
		return false;
	}


	function _findschema(&$table,&$schema) {
		if (!$schema && ($at = strpos($table,'.')) !== false) {
			$schema = substr($table,0,$at);
			$table = substr($table,$at+1);
		}
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
	function MetaColumns($db, $table,$normalize=true) {
		global $ADODB_FETCH_MODE;

		if (!empty($this->metaColumnsSQL)) {
			$schema = false;
			$this->_findschema($table,$schema);

			$save = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
			if ($db->fetchMode !== false) {
				$savem = $db->SetFetchMode(false);
			}
			$rs = $db->Execute(sprintf($db->metaColumnsSQL,($normalize)?strtoupper($table):$table));
			if (isset($savem)) {
				$db->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			if ($rs === false || $rs->EOF) {
				return false;
			}

			$retarr = array();
			while (!$rs->EOF) { //print_r($rs->fields);
				$fld = new \ADOFieldObject();
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
			return $retarr;
		}
		return false;
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
	function MetaIndexes($db, $table, $primary = false, $owner = false) {
		return false;
	}

	/**
	 * List columns names in a table as an array.
	 * @param table	table name to query
	 *
	 * @return  array of column names for current table.
	 */
	function MetaColumnNames($db, $table, $numIndexes=false,$useattnum=false /* only for postgres */) {
		$objarr = $this->MetaColumns($db, $table);
		if (!is_array($objarr)) {
			return false;
		}
		$arr = array();
		if ($numIndexes) {
			$i = 0;
			if ($useattnum) {
				foreach($objarr as $v)
					$arr[$v->attnum] = $v->name;

			} else
				foreach($objarr as $v) $arr[$i++] = $v->name;
		} else
			foreach($objarr as $v) $arr[strtoupper($v->name)] = $v->name;

		return $arr;
	}


}