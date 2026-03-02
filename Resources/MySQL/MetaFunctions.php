<?php

/**
 * Metafunctions loadable class
 *
 */

namespace ADOdb\Resources\MySQL;

require_once ADODB_DIR . '/Resources/MetaFunctions.php';
require_once ADODB_DIR . '/Resources/ADOFieldObject.php';

use ADOConnection;
use \ADOdb\Resources\ADOFieldObject;

class MetaFunctions extends \ADOdb\Resources\MetaFunctions
{
    

	var $hasInsertID = true;
	var $hasAffectedRows = true;
	var $metaTablesSQL = /** @lang text */
		"SELECT
			TABLE_NAME,
			CASE WHEN TABLE_TYPE = 'VIEW' THEN 'V' ELSE 'T' END
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_SCHEMA=";
	var $metaColumnsSQL = "SHOW COLUMNS FROM `%s`";
	var $fmtTimeStamp = "'Y-m-d H:i:s'";
	var $hasLimit = true;
	var $hasMoveFirst = true;
	var $hasGenID = true;
	var $isoDates = true; // accepts dates in ISO format
	var $sysDate = '(CURDATE())';
	var $sysTimeStamp = '(NOW())';
	var $hasTransactions = true;
	var $forceNewConnect = false;
	var $poorAffectedRows = true;
	var $clientFlags = 0;
	var $substr = "substring";
	var $port = 3306; //Default to 3306 to fix HHVM bug
	var $socket = ''; //Default to empty string to fix HHVM bug
	var $_bindInputArray = false;
	var $nameQuote = '`';		/// string to use to quote identifiers and names
    /**
     * Get the ADOdb metatype.
     *
     * Many databases use different names for the same type, so we transform
     * the native type to our standardised one, which uses 1 character codes.
     * @see https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:dictionary_index#portable_data_types
     *
     * @param ADOConnection  $db       database connection
     * @param ADOfieldObject $fieldObj Field object returned by the database driver
     *
     * @return string The ADOdb Standard type
     */
     public function metaType(
        object $db, 
        ADOFieldObject $fieldObj
    ): string {

        $t = (int)$fieldObj->type;
        $len = $fieldObj->max_length;

        $is_serial = is_object($fieldObj) && $fieldObj->primary_key && $fieldObj->auto_increment;

        $t = strtoupper($t);

        if (array_key_exists($t, $db->customActualTypes)) {
            return $db->customActualTypes[$t];
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


        if ($t == 254) {
            if (!$fieldObj->binary) {
                if (in_array($fieldObj->flags, [256, 2048])) {
                    return 'E';
                } else {
                    return 'C';
                }
            } else {
                return 'C2';
            }
        } elseif ($t == 253) {
            if (!$fieldObj->binary) {
                return 'C';
            } else {
                return 'C2';
            }
        } elseif ($t == 252) {
            if (!$fieldObj->binary) {
                if ($fieldObj->flags == 16) {
                    return 'X';
                } else {
                    return 'C';
                }
            } else {
                return 'B';
            }
        } elseif (in_array($t, [MYSQLI_TYPE_CHAR, 16])) {
            if (!$fieldObj->binary) {
                return 'L';
            }
        } elseif (in_array($t, [MYSQLI_TYPE_SHORT])) {
            if (!$fieldObj->binary) {
                return 'I2';
            }
        } elseif (in_array($t, [MYSQLI_TYPE_INT24])) {
            if (!$fieldObj->binary) {
                return 'I4';
            }
        } elseif (in_array($t, [MYSQLI_TYPE_LONG])) {
            if (!$fieldObj->binary) {
                return 'I';
            }
        } elseif ($t == MYSQLI_TYPE_LONGLONG) {
            if (!$fieldObj->binary) {
                return 'I8';
            }
        } elseif (in_array($t, [MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE])) {
            if (!$fieldObj->binary) {
                return 'F';
            }
        } elseif ($t == 246) {
            if (!$fieldObj->binary) {
                return 'N';
            }
        } elseif (
            in_array(
                $t,
                [
                    MYSQLI_TYPE_TIMESTAMP,
                    MYSQLI_TYPE_TIME,
                    MYSQLI_TYPE_DATETIME,
                    27
                    ]
            )
        ) {
            return 'T';
        } elseif (
            in_array(
                $t,
                [ MYSQLI_TYPE_DATE, MYSQLI_TYPE_YEAR ]
            )
        ) {
            return 'D';
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
    public function actualType(object $db,  string $meta): string
    {
        $meta = strtoupper($meta);

        /*
        * Add support for custom meta types. We do this
        * first, that allows us to override existing types
        */
        if (isset($db->customMetaTypes[$meta])) {
            return $db->customMetaTypes[$meta]['actual'];
        }

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

    /**
	 * Retrieves a list of tables based on given criteria
	 *
	 * @param string|bool $ttype (Optional) Table type = 'TABLE', 'VIEW' or false=both (default)
	 * @param string|bool $showSchema (Optional) schema name, false = current schema (default)
	 * @param string|bool $mask (Optional) filters the table by name
	 *
	 * @return array list of tables
	 */
    function metaTables(object $db,  ?string $ttype=null, ?string $showSchema=null, ?string $mask=null) {
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
		$ret = parent::metaTables($db, $ttype,$showSchema);

		$this->metaTablesSQL = $save;
		return $ret;
	}

    /**
	 * Return an array of information about a table's columns.
	 *
	 * @param string $table The name of the table to get the column info for.
	 * @param bool $normalize (Optional) Unused.
	 *
	 * @return ADOFieldObject[]|bool An array of info for each column, or false if it could not determine the info.
	 */
	function MetaColumns(object $db,  string $table, bool $normalize = true)
	{
		if (!$this->metaColumnsSQL)
			return false;

		$metaTable = $this->metaTables($db, 'T', false, $table);
        if (!$metaTable) {
            return false;
        }

        global $ADODB_FETCH_MODE;
		
        $baseMode = $db->fetchMode ? $db->fetchMode : $ADODB_FETCH_MODE;


        $saveModes = [
            $ADODB_FETCH_MODE,
            $db->fetchMode
        ];
        
        //$save = $ADODB_FETCH_MODE;
		//$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		//if ($db->fetchMode !== false)
		$db->SetFetchMode(ADODB_FETCH_NUM);
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
			$rs = $db->Execute(sprintf($this->metaColumnsSQL,$table));
		}

		//if (isset($savem)) $db->SetFetchMode($savem);
		$ADODB_FETCH_MODE = $saveModes[0];
		$db->fetchMode    = $saveModes[1];

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

			if ($baseMode == ADODB_FETCH_NUM) {
				$retarr[] = $fld;
			} else {
				$retarr[strtoupper($fld->name)] = $fld;
			}
			$rs->moveNext();
		}

		$rs->close();
		return $retarr;
	}

    /**
	 * Get a list of indexes on the specified table.
	 *
	 * @param string $table The name of the table to get indexes for.
	 * @param bool $primary (Optional) Whether or not to include the primary key.
	 * @param bool $owner (Optional) Unused.
	 *
	 * @return array|bool An array of the indexes, or false if the query to get the indexes failed.
	 */
	function MetaIndexes(object $db,  string $table, bool $primary = false, ?string $owner = null) {

		// save old fetch mode
		global $ADODB_FETCH_MODE;

		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		if ($db->fetchMode !== FALSE) {
			$savem = $db->setFetchMode(FALSE);
		}

		// get index details
		$rs = $db->execute(sprintf('SHOW INDEXES FROM `%s`',$table));

		// restore fetchmode
		if (isset($savem)) {
			$db->setFetchMode($savem);
		}
		$ADODB_FETCH_MODE = $save;

		if (!is_object($rs)) {
			return false;
		}

		$indexes = array ();

		// parse index data into array
		while ($row = $rs->fetchRow()) {
			if (!$primary AND $row[2] == 'PRIMARY') {
				continue;
			}

			if (!isset($indexes[$row[2]])) {
				$indexes[$row[2]] = array(
					'unique' => ($row[1] == 0),
					'columns' => array()
				);
			}

			$indexes[$row[2]]['columns'][$row[3] - 1] = $row[4];
		}

		// sort columns by order in the index
		foreach ( array_keys ($indexes) as $index )
		{
			ksort ($indexes[$index]['columns']);
		}

		return $indexes;
	}

}
