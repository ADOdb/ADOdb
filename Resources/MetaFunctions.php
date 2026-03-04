<?php

/**
 * MetaFunctions
 *
 */

namespace ADOdb\Resources;

use ADOConnection;
use \ADOdb\Resources\ADOFieldObject;

class MetaFunctions
{
    /**
     * any varchar/char field this size or greater is treated as a blob
     *
     * @var integer
     */
    protected int $blobSize = 2000;

    /** @var string SQL statement to get databases */
	var $metaDatabasesSQL = '';

	/** @var string SQL statement to get database tables */
	var $metaTablesSQL = '';

	/** @var string SQL statement to get table columns. */
	var $metaColumnsSQL;

    /**
     * Get the ADOdb metatype.
     *
     * Many databases use different names for the same type, so we transform
     * the native type to our standardised one, which uses 1 character codes.
     * @see https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:dictionary_index#portable_data_types
     *
     * @param ADOConnection  $db       database connection
     * @param ADOFieldObject $fieldObj Field object returned by the database driver
     *
     * @return string The ADOdb Standard type
     */
    public function metaType(
        object $db, 
        ADOFieldObject $fieldObj
    ): string {

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

        $t = strtoupper($fieldObj->type);
        $tmap = (isset($typeMap[$t])) ? $typeMap[$t] : ADODB_DEFAULT_METATYPE;
        switch ($tmap) {
            case 'C':
                // is the char field is too long, return as text field...
                if ($fieldObj->max_length >= 0) {
                    if ($fieldObj->max_length > $this->blobSize) {
                        return 'X';
                    }
                } elseif ($fieldObj->max_length > 250) {
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

    /**
     * Returns the actual type for a given meta type.
     *
     * @param object $db   The database connection
     * @param string        $meta The meta type to convert:
     *
     * - C:  varchar
     * - X:  CLOB (character large object) or
     *       largest varchar size if CLOB is not supported
     * - C2: Multibyte varchar
     * - X2: Multibyte CLOB
     * - B:  BLOB (binary large object)
     * - D:  Date
     * - T:  Date-time
     * - L:  Integer field suitable for storing booleans (0 or 1)
     * - I:  Integer
     * - F:  Floating point number
     * - N:  Numeric or decimal number
     *
     * @return string The actual type corresponding to the meta type.
     */
    function actualType(object $db,  string $meta): string
    {
        $meta = strtoupper($meta);

        // Add support for custom meta types. We do this
        // first, that allows us to override existing types
        if (isset($db->customMetaTypes[$meta])) {
            return $db->customMetaTypes[$meta]['actual'];
        }

        return $meta;
    }

    /**
	 * Returns an array of table names and/or views in the database.
	 *
	 * @param string|bool $ttype Can be either `TABLE`, `VIEW`, or false.
	 *   - If false, both views and tables are returned.
	 *   - `TABLE` (or `T`) returns only tables
	 *   - `VIEW` (or `V` returns only views
	 * @param string|bool $showSchema Prepends the schema/user to the table name,
	 *                                eg. USER.TABLE
	 * @param string|bool $mask Input mask - not supported by all drivers
	 *
	 * @return array|false Tables/Views for current database.
	 */
	function metaTables(object $db,  ?string $ttype=null, ?string $showSchema=null, ?string $mask=null) {
		global $ADODB_FETCH_MODE;

		if (!$this->metaTablesSQL) {
			return false;
		}

		if ($mask) {
			return false;
		}

		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		if ($db->fetchMode !== false) {
			$savem = $db->setFetchMode(false);
		}

		$rs = $db->execute($this->metaTablesSQL);

		if (isset($savem)) {
			$db->setFetchMode($savem);
		}
		$ADODB_FETCH_MODE = $save;

		if ($rs === false) {
			return false;
		}

		$res = $rs->getArray();

		// Filter result to keep only the selected type
		if ($res && $ttype && isset($res[0][1])) {
			$ttype = strtoupper($ttype[0]);
			$res = array_filter($res,
				/**
				 * @param array $table metaTablesSQL query result row.
				 *
				 * @return bool true if $ttype matches the table's type.
				 */
				function (array $table) use ($ttype): bool {
					return $table[1][0] == $ttype;
				}
			);
		}

		return array_column($res, 0);
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
	function metaColumns(object $db,  string $table, bool $normalize=true) {
		
        global $ADODB_FETCH_MODE;

		print "\n************************88\n";
		print $this->metaColumnsSQL;

		if (!empty($this->metaColumnsSQL)) {
			$schema = false;
			$this->_findschema($table,$schema);

			$save = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
			if ($db->fetchMode !== false) {
				$savem = $db->setFetchMode(false);
			}
			$rs = $db->execute(sprintf($this->metaColumnsSQL,($normalize)?strtoupper($table):$table));
			if (isset($savem)) {
				$db->setFetchMode($savem);
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
	function MetaIndexes(object $db,  string $table, bool $primary = false, ?string $owner = null) {
		return false;
	}

	/**
	 * List columns names in a table as an array
	 * 
	 * @param string $table	     table name to query
	 * @param bool   $numIndexes return numeric keys
	 * @param bool   $useattnum  discarded in base class
	 *
	 * @return false|array of column names for current table.
	 */
	public function MetaColumnNames(
		object $db, 
        string $table, 
		bool $numIndexes=false, 
		bool $useattnum=false
	) : mixed {
		
		$objarr = $this->MetaColumns($db, $table);
		if (!is_array($objarr)) {
			return false;
		}

		if ($useattnum) {
			/*
			* Assume we want a numeric array to
			* match the postgres option
			*/
			$numIndexes = true;
		}

		$columnNames = [];
		foreach($objarr as $v) {
			$columnNames[strtoupper($v->name)] = $v->name;
		}

		if ($numIndexes) {
			return array_values($columnNames);
		}

		return $columnNames;
	}
    
    function MetaError($err=false) {
		include_once(ADODB_DIR."/adodb-error.inc.php");
		if ($err === false) {
			$err = $this->ErrorNo();
		}
		return adodb_error($this->dataProvider,$this->databaseType,$err);
	}

	function MetaErrorMsg($errno) {
		include_once(ADODB_DIR."/adodb-error.inc.php");
		return adodb_errormsg($errno);
	}

	/**
	 * @returns an array with the primary key columns in it.
	 */
	function MetaPrimaryKeys(object $db,  string $table, ?string $owner=null) {
	// owner not used in base class - see oci8
		$p = array();
		$objs = $this->MetaColumns($db, $table);
		if ($objs) {
			foreach($objs as $v) {
				if (!empty($v->primary_key)) {
					$p[] = $v->name;
				}
			}
		}
		if (sizeof($p)) {
			return $p;
		}

		return false;
	}

	/**
	 * Returns a list of Foreign Keys associated with a specific table.
	 *
	 * If there are no foreign keys then the function returns false.
	 *
	 * @param string $table       The name of the table to get the foreign keys for.
	 * @param string $owner       Table owner/schema.
	 * @param bool   $upper       If true, only matches the table with the uppercase name.
	 * @param bool   $associative Returns the result in associative mode;
	 *                            if ADODB_FETCH_MODE is already associative, then
	 *                            this parameter is discarded.
	 *
	 * @return string[]|false An array where keys are tables, and values are foreign keys;
	 *                        false if no foreign keys could be found.
	 */
	function metaForeignKeys(ADOConnection $d, string $table, string $owner = '', bool $upper = false, bool $associative = false) {
		return false;
	}

    /**
	 * return the databases that the driver can connect to.
	 * Some databases will return an empty array.
	 *
	 * @return array|false an array of database names.
	 */
	function MetaDatabases(object $db) {
		
        global $ADODB_FETCH_MODE;

		if ($this->metaDatabasesSQL) {
			$save = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

			if ($db->fetchMode !== false) {
				$savem = $db->setFetchMode(false);
			}

			$arr = $db->GetCol($this->metaDatabasesSQL);
			if (isset($savem)) {
				$db->setFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;

			return $arr;
		}

		return false;
	}

}
