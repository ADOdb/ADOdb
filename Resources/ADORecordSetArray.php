<?php
//==============================================================================================
// CLASS ADORecordSet_array
//==============================================================================================

namespace ADOdb\Resources;

use ADOdb\Resources\ADORecordSet;
use ADOdb\Resources\ADOFieldObject;

/**
 * This class encapsulates the concept of a recordset created in memory
 * as an array. This is useful for the creation of cached recordsets.
 *
 * Note that the constructor is different from the standard ADORecordSet
 */
class ADORecordSetArray extends \ADOdb\Resources\ADORecordSet
{
    var $databaseType = 'array';

    var $_array;	// holds the 2-dimensional data array
    var $_types;	// the array of types of each column (C B I L M)
    var $_colnames;	// names of each column in array
    var $_skiprow1;	// skip 1st row because it holds column names
    var $_fieldobjects; // holds array of field objects
    var $canSeek = true;
    var $affectedrows = false;
    var $insertid = false;
    var $sql = '';
    var $compat = false;

    /**
     * Constructor
     *
     * The parameters passed to this recordset are always fake because
     * this class does not use the queryID
     *
     * @param resource|int $queryID Ignored
     * @param int|bool     $mode    The ADODB_FETCH_MODE value
     */
    function __construct($queryID, $mode=false) {
        parent::__construct(self::DUMMY_QUERY_ID, $mode);

        // fetch() on EOF does not delete $this->fields
        global $ADODB_COMPAT_FETCH;
        $this->compat = !empty($ADODB_COMPAT_FETCH);
    }

    /**
     * Setup the array.
     *
     * @param array		is a 2-dimensional array holding the data.
     *			The first row should hold the column names
        *			unless parameter $colnames is used.
        * @param typearr	holds an array of types. These are the same types
        *			used in MetaTypes (C,B,L,I,N).
        * @param string[]|false [$colnames]	array of column names. If set, then the first row of
        *			$array should not hold the column names.
        */
    function InitArray($array,$typearr,$colnames=false) {
        $this->_array = $array;
        $this->_types = $typearr;
        if ($colnames) {
            $this->_skiprow1 = false;
            $this->_colnames = $colnames;
        } else {
            $this->_skiprow1 = true;
            $this->_colnames = $array[0];
        }
        $this->Init();
    }
    /**
     * Setup the Array and datatype file objects
     *
     * @param array $array    2-dimensional array holding the data
     *			The first row should hold the column names
        *			unless parameter $colnames is used.
        * @param array $fieldarr Array of ADOFieldObject's.
        */
    function InitArrayFields(&$array,&$fieldarr) {
        $this->_array = $array;
        $this->_skiprow1= false;
        if ($fieldarr) {
            $this->_fieldobjects = $fieldarr;
        }
        $this->Init();
    }

    /**
     * @param int [$nRows]
     * @return array
     */
    function GetArray($nRows=-1) {
        if ($nRows == -1 && $this->_currentRow <= 0 && !$this->_skiprow1) {
            return $this->_array;
        } else {
            return $this->GetArray($nRows);
        }
    }

    function _initrs() {
        $this->_numOfRows =  sizeof($this->_array);
        if ($this->_skiprow1) {
            $this->_numOfRows -= 1;
        }

        $this->_numOfFields = (isset($this->_fieldobjects))
            ? sizeof($this->_fieldobjects)
            : sizeof($this->_types);
    }

    /**
     * Use associative array to get fields array
     *
     * @param string $colname
     * @return mixed
     */
    function Fields($colname) {
        $mode = isset($this->adodbFetchMode) ? $this->adodbFetchMode : $this->fetchMode;

        if ($mode & ADODB_FETCH_ASSOC) {
            if (!isset($this->fields[$colname]) && !is_null($this->fields[$colname])) {
                $colname = strtolower($colname);
            }
            return $this->fields[$colname];
        }
        if (!$this->bind) {
            $this->bind = array();
            for ($i=0; $i < $this->_numOfFields; $i++) {
                $o = $this->FetchField($i);
                $this->bind[strtoupper($o->name)] = $i;
            }
        }
        return $this->fields[$this->bind[strtoupper($colname)]];
    }

    /**
     * @param int $fieldOffset The required offset
     *
     * @return false|\ADOFieldObject
     */
    function FetchField($fieldOffset = -1) {
        if (isset($this->_fieldobjects)) {
            if (array_key_exists($fieldOffset, $this->_fieldobjects)) {
                return $this->_fieldobjects[$fieldOffset];
            } else {
                return false;
            }
        }

        if (!array_key_exists($fieldOffset, $this->_colnames)) {
            return false;
        }
        $o =  new ADOFieldObject();
        $o->name = $this->_colnames[$fieldOffset];
        $o->type =  $this->_types[$fieldOffset];
        $o->max_length = -1; // length not known

        return $o;
    }

    /**
     * @param int $row
     * @return bool
     */
    function _seek($row) {
        if (sizeof($this->_array) && 0 <= $row && $row < $this->_numOfRows) {
            $this->_currentRow = $row;
            if ($this->_skiprow1) {
                $row += 1;
            }
            $this->fields = $this->_array[$row];
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    function MoveNext() {
        if (!$this->EOF) {
            $this->_currentRow++;

            $pos = $this->_currentRow;

            if ($this->_numOfRows <= $pos) {
                if (!$this->compat) {
                    $this->fields = false;
                }
            } else {
                if ($this->_skiprow1) {
                    $pos += 1;
                }
                $this->fields = $this->_array[$pos];
                return true;
            }
            $this->EOF = true;
        }

        return false;
    }

    /**
     * @return bool
     */
    function _fetch() {
        $pos = $this->_currentRow;

        if ($this->_numOfRows <= $pos) {
            if (!$this->compat) {
                $this->fields = false;
            }
            return false;
        }
        if ($this->_skiprow1) {
            $pos += 1;
        }
        $this->fields = $this->_array[$pos];
        return true;
    }

    function _close() {
        return true;
    }

} // ADORecordSet_array
