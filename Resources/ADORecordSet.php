<?php
/**
 * RecordSet class that represents the dataset returned by the database.
 *
 * To keep memory overhead low, this class holds only the current row in memory.
 * No prefetching of data is done, so the RecordCount() can return -1 (which
 * means recordcount not known).
 */
namespace ADOdb\Resources;

use ADOConnection;
use ADOdb\Resources\ADOdb_Iterator;
use ADOdb\Resources\ADOFieldObject;

class ADORecordSet implements \IteratorAggregate {
	/**
	 * Used for cases when a recordset object is not created by executing a query.
	 */
	const DUMMY_QUERY_ID = -1;

	/**
	 * public variables
	 */
	var $dataProvider = "native";

	/**
	 * @var string Table name (used in _adodb_getupdatesql() and _adodb_getinsertsql())-
	 */
	public $tableName = '';

	/** @var bool|array  */
	var $fields = false;	/// holds the current row data
	var $blobSize = 100;	/// any varchar/char field this size or greater is treated as a blob
							/// in other words, we use a text area for editing.
	var $canSeek = false;	/// indicates that seek is supported
	var $sql;				/// sql text

	var $BOF = false;
	var $EOF = false;		/// Indicates that the current record position is after the last record in a Recordset object.

	var $emptyTimeStamp = '&nbsp;'; /// what to display when $time==0
	var $emptyDate = '&nbsp;'; /// what to display when $time==0
	var $debug = false;
	var $timeCreated=0;		/// datetime in Unix format rs created -- for cached recordsets

	var $bind = false;		/// used by Fields() to hold array - should be private?

	/** @var ADOConnection The parent connection */
	var $connection = false;
	/**
	 *	private variables
	 */
	var $_numOfRows = -1;	/** number of rows, or -1 */
	var $_numOfFields = -1;	/** number of fields in recordset */

	/**
	 * @var resource|int|false result link identifier
	 */
	var $_queryID = self::DUMMY_QUERY_ID;

	var $_currentRow = -1;	/** This variable keeps the current row in the Recordset.	*/
	var $_closed = false;	/** has recordset been closed */
	var $_inited = false;	/** Init() should only be called once */

	// Recordset pagination
	/** @var int Number of rows per page */
	var $rowsPerPage;
	/** @var int Current page number */
	var $_currentPage = -1;
	/** @var bool True if current page is the first page */
	var $_atFirstPage = false;
	/** @var bool True if current page is the last page */
	var $_atLastPage = false;
	/** @var int Last page number */
	var $_lastPageNo = -1;
	/** @var int Total number of rows in recordset */
	var $_maxRecordCount = 0;

	var $datetime = false;

	public $customActualTypes;
	public $customMetaTypes;

	/** @var int Only used in _adodb_getinsertsql() */
	public $insertSig;

	/**
	 * @var ADOFieldObject[] Field metadata cache
	 * @see fieldTypesArray()
	 */
	protected $fieldObjectsCache;

	/**
	 * @var bool True if we have retrieved the fields metadata
	 */
	protected $fieldObjectsRetrieved = false;

	/**
	 * @var array Cross-reference the objects by name for easy access
	 */
	protected $fieldObjectsIndex = array();

	/**
	 * @var bool|int Driver-specific fetch mode
	 */
	var $fetchMode;

	/**
	 * @var int Defines the Fetch Mode for a recordset
	 * See the ADODB_FETCH_* constants
	 */
	public $adodbFetchMode;

	public ?object $metaObject;

	/**
	 * Constructor
	 *
	 * @param resource|int $queryID Query ID returned by ADOConnection->_query()
	 * @param int|bool     $mode    The ADODB_FETCH_MODE value
	 */
	function __construct($queryID, $mode=false) {
		if ($mode === false) {
			global $ADODB_FETCH_MODE;
			$mode = $ADODB_FETCH_MODE;
		}
		$this->adodbFetchMode = $this->fetchMode = $mode;

		$this->_queryID = $queryID;
	}

	function __destruct() {
		$this->Close();
	}

	#[\ReturnTypeWillChange]
	function getIterator() {
		return new ADODB_Iterator($this);
	}

	/* this is experimental - i don't really know what to return... */
	function __toString() {
		include_once(ADODB_DIR.'/toexport.inc.php');
		return _adodb_export($this,',',',',false,true);
	}

	function Init() {
		if ($this->_inited) {
			return;
		}
		$this->_inited = true;
		if ($this->_queryID) {
			@$this->_initRS();
		} else {
			$this->_numOfRows = 0;
			$this->_numOfFields = 0;
		}
		if ($this->_numOfRows != 0 && $this->_numOfFields && $this->_currentRow == -1) {
			$this->_currentRow = 0;
			if ($this->EOF = ($this->_fetch() === false)) {
				$this->_numOfRows = 0; // _numOfRows could be -1
			}
		} else {
			$this->EOF = true;
		}
	}

	/**
	 * Recordset initialization stub
	 */
	protected function _initRS() {}

	/**
	 * Row fetch stub
	 * @return bool
	 */
	protected function _fetch() {}

	/**
	 * Generate a SELECT tag from a recordset, and return the HTML markup.
	 *
	 * If the recordset has 2 columns, we treat the first one as the text to
	 * display to the user, and the second as the return value. Extra columns
	 * are discarded.
	 *
	 * @param string       $name            Name of SELECT tag
	 * @param string|array $defstr          The value to highlight. Use an array for multiple highlight values.
	 * @param bool|string $blank1stItem     True to create an empty item (default), False not to add one;
	 *                                      'string' to set its label and 'value:string' to assign a value to it.
	 * @param bool         $multiple        True for multi-select list
	 * @param int          $size            Number of rows to show (applies to multi-select box only)
	 * @param string       $selectAttr      Additional attributes to defined for SELECT tag,
	 *                                      useful for holding javascript onChange='...' handlers, CSS class, etc.
	 * @param bool         $compareFirstCol When true (default), $defstr is compared against the value (column 2),
	 *                                      while false will compare against the description (column 1).
	 *
	 * @return string HTML
	 */
	function getMenu($name, $defstr = '', $blank1stItem = true, $multiple = false,
					 $size = 0, $selectAttr = '', $compareFirstCol = true)
	{
		global $ADODB_INCLUDED_LIB;
		if (empty($ADODB_INCLUDED_LIB)) {
			include_once(ADODB_DIR.'/adodb-lib.inc.php');
		}
		return _adodb_getmenu($this, $name, $defstr, $blank1stItem, $multiple,
			$size, $selectAttr, $compareFirstCol);
	}

	/**
	 * Generate a SELECT tag with groups from a recordset, and return the HTML markup.
	 *
	 * The recordset must have 3 columns and be ordered by the 3rd column. The
	 * first column contains the text to display to the user, the second is the
	 * return value and the third is the option group. Extra columns are discarded.
	 * Default strings are compared with the SECOND column.
	 *
	 * @param string       $name            Name of SELECT tag
	 * @param string|array $defstr          The value to highlight. Use an array for multiple highlight values.
	 * @param bool|string $blank1stItem     True to create an empty item (default), False not to add one;
	 *                                      'string' to set its label and 'value:string' to assign a value to it.
	 * @param bool         $multiple        True for multi-select list
	 * @param int          $size            Number of rows to show (applies to multi-select box only)
	 * @param string       $selectAttr      Additional attributes to defined for SELECT tag,
	 *                                      useful for holding javascript onChange='...' handlers, CSS class, etc.
	 * @param bool         $compareFirstCol When true (default), $defstr is compared against the value (column 2),
	 *                                      while false will compare against the description (column 1).
	 *
	 * @return string HTML
	 */
	function getMenuGrouped($name, $defstr = '', $blank1stItem = true, $multiple = false,
							$size = 0, $selectAttr = '', $compareFirstCol = true)
	{
		global $ADODB_INCLUDED_LIB;
		if (empty($ADODB_INCLUDED_LIB)) {
			include_once(ADODB_DIR.'/adodb-lib.inc.php');
		}
		return _adodb_getmenu_gp($this, $name, $defstr, $blank1stItem, $multiple,
			$size, $selectAttr, $compareFirstCol);
	}

	/**
	 * Generate a SELECT tag from a recordset, and return the HTML markup.
	 *
	 * Same as GetMenu(), except that default strings are compared with the
	 * FIRST column (the description).
	 *
	 * @param string       $name            Name of SELECT tag
	 * @param string|array $defstr          The value to highlight. Use an array for multiple highlight values.
	 * @param bool|string $blank1stItem     True to create an empty item (default), False not to add one;
	 *                                      'string' to set its label and 'value:string' to assign a value to it.
	 * @param bool         $multiple        True for multi-select list
	 * @param int          $size            Number of rows to show (applies to multi-select box only)
	 * @param string       $selectAttr      Additional attributes to defined for SELECT tag,
	 *                                      useful for holding javascript onChange='...' handlers, CSS class, etc.
	 *
	 * @return string HTML
	 *
	 * @deprecated 5.21.0 Use getMenu() with $compareFirstCol = false instead.
	 */
	function getMenu2($name, $defstr = '', $blank1stItem = true, $multiple = false,
					  $size = 0, $selectAttr = '')
	{
		return $this->getMenu($name, $defstr, $blank1stItem, $multiple,
			$size, $selectAttr,false);
	}

	/**
	 * Generate a SELECT tag with groups from a recordset, and return the HTML markup.
	 *
	 * Same as GetMenuGrouped(), except that default strings are compared with the
	 * FIRST column (the description).
	 *
	 * @param string       $name            Name of SELECT tag
	 * @param string|array $defstr          The value to highlight. Use an array for multiple highlight values.
	 * @param bool|string $blank1stItem     True to create an empty item (default), False not to add one;
	 *                                      'string' to set its label and 'value:string' to assign a value to it.
	 * @param bool         $multiple        True for multi-select list
	 * @param int          $size            Number of rows to show (applies to multi-select box only)
	 * @param string       $selectAttr      Additional attributes to defined for SELECT tag,
	 *                                      useful for holding javascript onChange='...' handlers, CSS class, etc.
	 *
	 * @return string HTML
	 *
	 * @deprecated 5.21.0 Use getMenuGrouped() with $compareFirstCol = false instead.
	 */
	function getMenu3($name, $defstr = '', $blank1stItem = true, $multiple = false,
					  $size = 0, $selectAttr = '')
	{
		return $this->getMenuGrouped($name, $defstr, $blank1stItem, $multiple,
			$size, $selectAttr, false);
	}

	/**
	 * return recordset as a 2-dimensional array.
	 *
	 * @param int $nRows  Number of rows to return. -1 means every row.
	 *
	 * @return array indexed by the rows (0-based) from the recordset
	 */
	function GetArray($nRows = -1) {
		$results = array();
		$cnt = 0;
		while (!$this->EOF && $nRows != $cnt) {
			$results[] = $this->fields;
			$this->MoveNext();
			$cnt++;
		}
		return $results;
	}

	function GetAll($nRows = -1) {
		return $this->GetArray($nRows);
	}

	/**
	 * Checks if there is another available recordset.
	 *
	 * Some databases allow multiple recordsets to be returned.
	 *
	 * @return boolean true if there is a next recordset, or false if no more
	 */
	function NextRecordSet() {
		return false;
	}

	/**
	 * Return recordset as a 2-dimensional array.
	 *
	 * Helper function for ADOConnection->SelectLimit()
	 *
	 * @param int $nrows  Number of rows to return
	 * @param int $offset Starting row (1-based)
	 *
	 * @return array an array indexed by the rows (0-based) from the recordset
	 */
	function getArrayLimit($nrows, $offset=-1) {
		if ($offset <= 0) {
			return $this->GetArray($nrows);
		}

		$this->Move($offset);

		$results = array();
		$cnt = 0;
		while (!$this->EOF && $nrows != $cnt) {
			$results[$cnt++] = $this->fields;
			$this->MoveNext();
		}

		return $results;
	}


	/**
	 * Synonym for GetArray() for compatibility with ADO.
	 *
	 * @param int $nRows Number of rows to return. -1 means every row.
	 *
	 * @return array an array indexed by the rows (0-based) from the recordset
	 */
	function getRows($nRows = -1) {
		return $this->GetArray($nRows);
	}

	/**
	 * return whole recordset as a 2-dimensional associative array if
	 * there are more than 2 columns. The first column is treated as the
	 * key and is not included in the array. If there is only 2 columns,
	 * it will return a 1 dimensional array of key-value pairs unless
	 * $force_array == true. This recordset method is currently part of
	 * the API, but may not be in later versions of ADOdb. By preference, use
	 * ADOConnection::getAssoc()
	 *
	 * @param bool	$force_array	(optional) Has only meaning if we have 2 data
	 *								columns. If false, a 1 dimensional
	 * 								array is returned, otherwise a 2-dimensional
	 *								array is returned. If this sounds confusing,
	 * 								read the source.
	 *
	 * @param bool	$first2cols 	(optional) Means if there are more than
	 *								2 cols, ignore the remaining cols and
	 * 								instead of returning
	 *								array[col0] => array(remaining cols),
	 *								return array[col0] => col1
	 *
	 * @return array|false
	 */
	function getAssoc($force_array = false, $first2cols = false)
	{
		global $ADODB_FETCH_MODE;

		// Insufficient rows to show data
		if ($this->_numOfFields < 2) {
			return false;
		}

		// Empty recordset
		if (!$this->fields) {
			return array();
		}

		// The number of fields is half the actual returned in BOTH mode
		$numberOfFields = $this->_numOfFields;

		// Get the fetch mode when the call was executed, this may be
		// different from ADODB_FETCH_MODE
		$fetchMode = $this->adodbFetchMode;
		if ($fetchMode == ADODB_FETCH_BOTH || $fetchMode == ADODB_FETCH_DEFAULT) {
			// If we are using BOTH, we present the data as if it were in ASSOC mode.
			// This could be enhanced by adding a BOTH_ASSOC_MODE class property.
			// We build a template of numeric keys. you could improve the speed
			// by caching this, indexed by number of keys.
			$testKeys = array_fill(0, $numberOfFields, 0);
		}

		$showArrayMethod = 0;

		if ($numberOfFields == 2) {
			// Key is always the value of first element
			// Value is always value of second element
			$showArrayMethod = 1;
		}

		if ($force_array) {
			$showArrayMethod = 0;
		}

		if ($first2cols) {
			$showArrayMethod = 1;
		}

		$results = array();

		while (!$this->EOF) {
			$myFields = $this->fields;

			if ($fetchMode == ADODB_FETCH_BOTH || $fetchMode == ADODB_FETCH_DEFAULT) {
				// extract the associative keys
				/** @noinspection PhpUndefinedVariableInspection */
				$myFields = array_diff_key($myFields, $testKeys);
			}

			// Key is value of the first element, the rest is data.
			// The key is not case processed.
			$key = array_shift($myFields);

			switch ($showArrayMethod) {
				case 0:
					if ($fetchMode != ADODB_FETCH_NUM) {
						// The driver should have already handled the key casing, but in case it did not.
						// We will check and force this in later versions of ADOdb
						if (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_UPPER) {
							$myFields = array_change_key_case($myFields, CASE_UPPER);
						}
						elseif (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_LOWER) {
							$myFields = array_change_key_case($myFields, CASE_LOWER);
						}

						// We have already shifted the key off the front, so the rest is the value
						$results[$key] = $myFields;
					} else
						// I want the values in a numeric array, nicely re-indexed from zero
						$results[$key] = array_values($myFields);
					break;

				/** @noinspection PhpConditionAlreadyCheckedInspection */
				case 1:
					// Don't care how long the array is, I just want value of second column,
					// and it doesn't matter whether the array is associative or numeric
					$results[$key] = array_shift($myFields);
					break;
			}

			$this->MoveNext();
		}

		return $results;
	}

	/**
	 *
	 * @param mixed $v		is the character timestamp in YYYY-MM-DD hh:mm:ss format
	 * @param string [$fmt]	is the format to apply to it, using date()
	 *
	 * @return string a timestamp formatted as user desires
	 */
	function UserTimeStamp($v,$fmt='Y-m-d H:i:s') {
		if (is_numeric($v) && strlen($v)<14) {
			return date($fmt,$v);
		}
		$tt = $this->UnixTimeStamp($v);
		// $tt == -1 if pre TIMESTAMP_FIRST_YEAR
		if (($tt === false || $tt == -1) && $v != false) {
			return $v;
		}
		if ($tt === 0) {
			return $this->emptyTimeStamp;
		}
		return date($fmt,$tt);
	}


	/**
	 * @param mixed $v		is the character date in YYYY-MM-DD format, returned by database
	 * @param string $fmt	is the format to apply to it, using date()
	 *
	 * @return string a date formatted as user desires
	 */
	function UserDate($v,$fmt='Y-m-d') {
		$tt = $this->UnixDate($v);
		// $tt == -1 if pre TIMESTAMP_FIRST_YEAR
		if (($tt === false || $tt == -1) && $v != false) {
			return $v;
		} else if ($tt == 0) {
			return $this->emptyDate;
		} else if ($tt == -1) {
			// pre-TIMESTAMP_FIRST_YEAR
		}
		return date($fmt,$tt);
	}


	/**
	 * @param mixed $v is a date string in YYYY-MM-DD format
	 *
	 * @return string date in unix timestamp format, or 0 if before TIMESTAMP_FIRST_YEAR, or false if invalid date format
	 */
	static function UnixDate($v) {
		return ADOConnection::UnixDate($v);
	}


	/**
	 * @param string|object $v is a timestamp string in YYYY-MM-DD HH-NN-SS format
	 *
	 * @return mixed date in unix timestamp format, or 0 if before TIMESTAMP_FIRST_YEAR, or false if invalid date format
	 */
	static function UnixTimeStamp($v) {
		return ADOConnection::UnixTimeStamp($v);
	}


	/**
	 * PEAR DB Compat - do not use internally
	 */
	function Free() {
		return $this->Close();
	}


	/**
	 * PEAR DB compat, number of rows
	 *
	 * @return int
	 */
	function NumRows() {
		return $this->_numOfRows;
	}


	/**
	 * PEAR DB compat, number of cols
	 *
	 * @return int
	 */
	function NumCols() {
		return $this->_numOfFields;
	}

	/**
	 * Fetch a row, returning false if no more rows.
	 * This is PEAR DB compat mode.
	 *
	 * @return mixed[]|false false or array containing the current record
	 */
	function FetchRow() {
		if ($this->EOF) {
			return false;
		}
		$arr = $this->fields;
		$this->_currentRow++;
		if (!$this->_fetch()) {
			$this->EOF = true;
		}
		return $arr;
	}


	/**
	 * Fetch a row, returning PEAR_Error if no more rows.
	 * This is PEAR DB compat mode.
	 *
	 * @param mixed[]|false $arr
	 *
	 * @return mixed DB_OK or error object
	 */
	function FetchInto(&$arr) {
		if ($this->EOF) {
			return (defined('PEAR_ERROR_RETURN')) ? new PEAR_Error('EOF',-1): false;
		}
		$arr = $this->fields;
		$this->MoveNext();
		return 1; // DB_OK
	}


	/**
	 * Move to the first row in the recordset. Many databases do NOT support this.
	 *
	 * @return bool true or false
	 */
	function MoveFirst() {
		if ($this->_currentRow == 0) {
			return true;
		}
		return $this->Move(0);
	}


	/**
	 * Move to the last row in the recordset.
	 *
	 * @return bool true or false
	 */
	function MoveLast() {
		if ($this->_numOfRows >= 0) {
			return $this->Move($this->_numOfRows-1);
		}
		if ($this->EOF) {
			return false;
		}
		while (!$this->EOF) {
			$f = $this->fields;
			$this->MoveNext();
		}
		$this->fields = $f;
		$this->EOF = false;
		return true;
	}


	/**
	 * Move to next record in the recordset.
	 *
	 * @return bool true if there still rows available, or false if there are no more rows (EOF).
	 */
	function MoveNext() {
		if (!$this->EOF) {
			$this->_currentRow++;
			if ($this->_fetch()) {
				return true;
			}
		}
		$this->EOF = true;
		/* -- tested error handling when scrolling cursor -- seems useless.
		$conn = $this->connection;
		if ($conn && $conn->raiseErrorFn && ($errno = $conn->ErrorNo())) {
			$fn = $conn->raiseErrorFn;
			$fn($conn->databaseType,'MOVENEXT',$errno,$conn->ErrorMsg().' ('.$this->sql.')',$conn->host,$conn->database);
		}
		*/
		return false;
	}


	/**
	 * Random access to a specific row in the recordset. Some databases do not support
	 * access to previous rows in the databases (no scrolling backwards).
	 *
	 * @param int $rowNumber is the row to move to (0-based)
	 *
	 * @return bool true if there still rows available, or false if there are no more rows (EOF).
	 */
	function Move($rowNumber = 0) {

		/*
		* Is the recordset already in BOF or EOF state?
		*/
		if ($this->BOF) {
			$currentRow = -1;
		} elseif ($this->EOF) {
			$currentRow = $this->_numOfRows + 1;
		} else {
			$currentRow = $this->_currentRow;
		}

		if ($rowNumber == $currentRow
			|| ($this->EOF && $rowNumber > $currentRow)
			|| ($this->BOF && $rowNumber < $currentRow)
		) {
			/*
			* Ensure the correct EOF state is retained and
			* return appropriate status
			*/
			if ($this->EOF || $this->BOF) {
				$this->_currentRow = false;
				return false;
			}

			return true;
		}

		$this->EOF = false;
		$this->BOF = false;

		if ($rowNumber >= $this->_numOfRows) {
			$this->EOF         = true;
			$this->fields      = false;
			$this->_currentRow = false;
			return false;
		}

		if ($rowNumber < 0) {
			$this->BOF    = true;
			$this->fields = false;
			$this->_currentRow = false;
			return false;
		}

		if ($this->canSeek) {
			/*
			* Database supports cursor movement to arbitrary record
			* number
			*/
			if ($this->_seek($rowNumber)) {
				$this->_currentRow = $rowNumber;
				/*
				* now use a native function to retrieve a record
				* at that point
				*/
				if ($this->_fetch()) {
					return true;
				}
			} else {
				$this->EOF = true;
				return false;
			}
		} else {
			if ($rowNumber < $this->_currentRow) {
				/*
				* If canseek is not supported, then the system
				* cannot go backwards
				*/
				return false;
			}
			while (! $this->EOF && $this->_currentRow < $rowNumber) {
				$this->_currentRow++;

				if (!$this->_fetch()) {
					$this->EOF = true;
				}
			}
			return !($this->EOF);
		}

		$this->fields = false;
		$this->EOF = true;
		return false;
	}

	/**
	 * Adjusts the result pointer to an arbitrary row in the result.
	 *
	 * @param int $row The row to seek to.
	 *
	 * @return bool False if the recordset contains no rows, otherwise true.
	 */
	function _seek($row) {}

	/**
	 * Get the value of a field in the current row by column name.
	 * Will not work if ADODB_FETCH_MODE is set to ADODB_FETCH_NUM.
	 *
	 * @param string $colname is the field to access
	 *
	 * @return mixed the value of $colname column
	 */
	function Fields($colname) {
		return $this->fields[$colname];
	}

	/**
	 * Defines the function to use for table fields case conversion
	 * depending on ADODB_ASSOC_CASE
	 *
	 * @param int [$case]
	 *
	 * @return string strtolower/strtoupper or false if no conversion needed
	 */
	protected function AssocCaseConvertFunction($case = ADODB_ASSOC_CASE) {
		switch($case) {
			case ADODB_ASSOC_CASE_UPPER:
				return 'strtoupper';
			case ADODB_ASSOC_CASE_LOWER:
				return 'strtolower';
			case ADODB_ASSOC_CASE_NATIVE:
			default:
				return false;
		}
	}

	/**
	 * Builds the bind array associating keys to recordset fields
	 *
	 * @param int [$upper] Case for the array keys, defaults to uppercase
	 *                   (see ADODB_ASSOC_CASE_xxx constants)
	 */
	function GetAssocKeys($upper = ADODB_ASSOC_CASE) {
		if ($this->bind) {
			return;
		}
		$this->bind = array();

		// Define case conversion function for ASSOC fetch mode
		$fn_change_case = $this->AssocCaseConvertFunction($upper);

		// Build the bind array
		for ($i=0; $i < $this->_numOfFields; $i++) {
			$o = $this->FetchField($i);

			// Set the array's key
			if(is_numeric($o->name)) {
				// Just use the field ID
				$key = $i;
			}
			elseif( $fn_change_case ) {
				// Convert the key's case
				$key = $fn_change_case($o->name);
			}
			else {
				$key = $o->name;
			}

			$this->bind[$key] = $i;
		}
	}

	/**
	 * Use associative array to get fields array for databases that do not support
	 * associative arrays. Submitted by Paolo S. Asioli paolo.asioli#libero.it
	 *
	 * @param int $upper Case for the array keys, defaults to uppercase
	 *                   (see ADODB_ASSOC_CASE_xxx constants)
	 * @return array
	 */
	function GetRowAssoc($upper = ADODB_ASSOC_CASE) {
		$record = array();
		$this->GetAssocKeys($upper);

		foreach($this->bind as $k => $v) {
			if( array_key_exists( $v, $this->fields ) ) {
				$record[$k] = $this->fields[$v];
			} elseif( array_key_exists( $k, $this->fields ) ) {
				$record[$k] = $this->fields[$k];
			} else {
				# This should not happen... trigger error ?
				$record[$k] = null;
			}
		}
		return $record;
	}

	/**
	 * Clean up recordset
	 *
	 * @return bool
	 */
	function Close() {
		// free connection object - this seems to globally free the object
		// and not merely the reference, so don't do this...
		// $this->connection = false;
		if (!$this->_closed) {
			$this->_closed = true;
			return $this->_close();
		} else
			return true;
	}

	/**
	 * Number of rows in recordset.
	 *
	 * @return int Number of rows or -1 if this is not supported
	 */
	function recordCount() {
		return $this->_numOfRows;
	}

	/**
	 * If we are using PageExecute(), this will return the maximum possible rows
	 * that can be returned when paging a recordset.
	 *
	 * @return int
	 */
	function MaxRecordCount() {
		return ($this->_maxRecordCount) ? $this->_maxRecordCount : $this->recordCount();
	}

	/**
	 * Number of rows in recordset.
	 * Alias for {@see recordCount()}
	 *
	 * @return int Number of rows or -1 if this is not supported
	 */
	function rowCount() {
		return $this->recordCount();
	}

	/**
	 * Portable RecordCount.
	 *
	 * Be aware of possible problems in multiuser environments.
	 * For better speed the table must be indexed by the condition.
	 * Heavy test this before deploying.
	 *
	 * @param string $table
	 * @param string $condition
	 *
	 * @return int Number of records from a previous SELECT. All databases support this.
	 *
	 * @author Pablo Roca <pabloroca@mvps.org>
	 */
	function PO_RecordCount($table="", $condition="") {

		$lnumrows = $this->_numOfRows;
		// the database doesn't support native recordcount, so we do a workaround
		if ($lnumrows == -1 && $this->connection) {
			IF ($table) {
				if ($condition) {
					$condition = " WHERE " . $condition;
				}
				$resultrows = $this->connection->Execute("SELECT COUNT(*) FROM $table $condition");
				if ($resultrows) {
					$lnumrows = reset($resultrows->fields);
				}
			}
		}
		return $lnumrows;
	}


	/**
	 * @return the current row in the recordset. If at EOF, will return the last row. 0-based.
	 */
	function CurrentRow() {
		return $this->_currentRow;
	}

	/**
	 * synonym for CurrentRow -- for ADO compat
	 *
	 * @return the current row in the recordset. If at EOF, will return the last row. 0-based.
	 */
	function AbsolutePosition() {
		return $this->_currentRow;
	}

	/**
	 * @return the number of columns in the recordset. Some databases will set this to 0
	 * if no records are returned, others will return the number of columns in the query.
	 */
	function FieldCount() {
		return $this->_numOfFields;
	}

	/**
	 * Get a Field's metadata from database.
	 *
	 * Must be defined by child class.
	 *
	 * @param int $fieldOffset Optional field offset
	 *
	 * @return ADOFieldObject|false
	 */
	function fetchField($fieldOffset)
	{
		return false;
	}

	/**
	 * Get Field metadata for all the recordset's columns in an array.
	 *
	 * @return ADOFieldObject[]
	 */
	function fieldTypesArray() {
		if (empty($this->fieldObjectsCache)) {
			for ($i = 0; $i < $this->_numOfFields; $i++) {
				$this->fieldObjectsCache[] = $this->fetchField($i);
			}
		}
		return $this->fieldObjectsCache;
	}

	/**
	 * Return the current row as an object for convenience.
	 *
	 * @return ADOFetchObj The object with properties set to the current row's fields.
	 */
	function fetchObj() {
		return $this->fetchObject(false);
	}

	/**
	 * Return the current row as an object for convenience.
	 *
	 * Field names are converted to uppercase by default.
	 *
	 * @param bool $isUpper True to convert field names to uppercase.
	 *
	 * @return bool|ADOFetchObj The object with properties set to the current row's fields.
	 */
	function fetchObject($isUpper = true) {

		if (!$this->fields) {
			/*
			* past EOF
			*/
			return false;
		}

		$casing = $isUpper ? CASE_UPPER : CASE_LOWER;

		$fields = array_change_key_case($this->fields, $casing);

		return new ADOFetchObj($fields);
	}

	/**
	 * Return the current row as an object for convenience and move to next row.
	 *
	 * @return ADOFetchObj|false The object with properties set to the current row's fields.
	 *                           or false if EOF.
	 */
	function fetchNextObj() {
		return $this->fetchNextObject(false);
	}


	/**
	 * Return the current row as an object for convenience and move to next row.
	 *
	 * Field names are converted to uppercase by default.
	 *
	 * @param bool $isUpper True to convert field names to uppercase.
	 *
	 * @return ADOFetchObj|false The object with properties set to the current row's fields.
	 *                           or false if EOF.
	 */
	function fetchNextObject($isUpper=true) {
		$o = false;
		if ($this->_numOfRows != 0 && !$this->EOF) {
			$o = $this->fetchObject($isUpper);
			$this->_currentRow++;
			if ($this->_fetch()) {
				return $o;
			}
		}
		$this->EOF = true;
		return $o;
	}

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
	function metaType($t, $len = -1, $fieldObj = false) {
		
		if (is_object($this->connection)) {
			if (is_object($this->connection->metaObject)) {
				if (!is_object($t) && !$fieldObj) {
					if ($this->debug) {
						ADOConnection::outp('Metatype no longer supports passing the field as a string');
					}
					return false;
				} else if (!is_object($t) && is_object($fieldObj)) {
					$t = $fieldObj;
				}

				return $this->connection->metaObject->metaType($this, $t);
			}
		}
	
	}

	/**
	 * Convert case of field names associative array, if needed
	 * @return void
	 */
	protected function _updatefields()
	{
		if( empty($this->fields)) {
			return;
		}

		// Determine case conversion function
		$fn_change_case = $this->AssocCaseConvertFunction();
		if(!$fn_change_case) {
			// No conversion needed
			return;
		}

		$arr = array();

		// Change the case
		foreach($this->fields as $k => $v) {
			if (!is_integer($k)) {
				$k = $fn_change_case($k);
			}
			$arr[$k] = $v;
		}
		$this->fields = $arr;
	}

	function _close() {}

	/**
	 * set/returns the current recordset page when paginating
	 * @param int $page
	 * @return int
	 */
	function absolutePage($page=-1) {
		if ($page != -1) {
			$this->_currentPage = $page;
		}
		return $this->_currentPage;
	}

	/**
	 * set/returns the status of the atFirstPage flag when paginating
	 * @param bool $status
	 * @return bool
	 */
	function AtFirstPage($status=false) {
		if ($status != false) {
			$this->_atFirstPage = $status;
		}
		return $this->_atFirstPage;
	}

	/**
	 * @param bool $page
	 * @return bool
	 */
	function LastPageNo($page = false) {
		if ($page != false) {
			$this->_lastPageNo = $page;
		}
		return $this->_lastPageNo;
	}

	/**
	 * set/returns the status of the atLastPage flag when paginating
	 * @param bool $status
	 * @return bool
	 */
	function AtLastPage($status=false) {
		if ($status != false) {
			$this->_atLastPage = $status;
		}
		return $this->_atLastPage;
	}

}
