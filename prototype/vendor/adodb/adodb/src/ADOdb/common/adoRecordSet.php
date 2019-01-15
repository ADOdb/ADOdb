<?php
namespace ADOdb\common;

use ADOdb;
class adoRecordSet implements \IteratorAggregate
{

	/**
	 * public variables
	 */
	var $dataProvider = "native";
	var $fields = false;	/// holds the current row data
	var $blobSize = 100;	/// any varchar/char field this size or greater is treated as a blob
							/// in other words, we use a text area for editing.
	var $canSeek = false;	/// indicates that seek is supported
	var $sql;				/// sql text
	var $EOF = false;		/// Indicates that the current record position is after the last record in a Recordset object.

	var $emptyTimeStamp = '&nbsp;'; /// what to display when $time==0
	var $emptyDate = '&nbsp;'; /// what to display when $time==0
	var $debug = false;
	var $timeCreated=0;		/// datetime in Unix format rs created -- for cached recordsets

	var $bind = false;		/// used by Fields() to hold array - should be private?
	var $fetchMode;			/// default fetch mode
	var $connection = false; /// the parent connection

	/**
	 *	private variables
	 */
	var $_numOfRows = -1;	/** number of rows, or -1 */
	var $_numOfFields = -1;	/** number of fields in recordset */
	var $_queryID = -1;		/** This variable keeps the result link identifier.	*/
	var $_currentRow = -1;	/** This variable keeps the current row in the Recordset.	*/
	var $_closed = false;	/** has recordset been closed */
	var $_inited = false;	/** Init() should only be called once */
	var $_obj;				/** Used by FetchObj */
	var $_names;			/** Used by FetchObj */

	var $_currentPage = -1;	/** Added by Iván Oliva to implement recordset pagination */
	var $_atFirstPage = false;	/** Added by Iván Oliva to implement recordset pagination */
	var $_atLastPage = false;	/** Added by Iván Oliva to implement recordset pagination */
	var $_lastPageNo = -1;
	var $_maxRecordCount = 0;
	var $datetime = false;

	/**
	 * Constructor
	 *
	 * @param queryID	this is the queryID returned by ADOConnection->_query()
	 *
	 */
	function __construct($queryID) {
		$this->_queryID = $queryID;
	}

	function __destruct() {
		$this->Close();
	}

	function getIterator() {
		return new ADODB_Iterator($this);
	}

	
	function Init() {
		if ($this->_inited) {
			return;
		}
		$this->_inited = true;
		if ($this->_queryID) {
			@$this->_initrs();
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
	 * Generate a SELECT tag string from a recordset, and return the string.
	 * If the recordset has 2 cols, we treat the 1st col as the containing
	 * the text to display to the user, and 2nd col as the return value. Default
	 * strings are compared with the FIRST column.
	 *
	 * @param name			name of SELECT tag
	 * @param [defstr]		the value to hilite. Use an array for multiple hilites for listbox.
	 * @param [blank1stItem]	true to leave the 1st item in list empty
	 * @param [multiple]		true for listbox, false for popup
	 * @param [size]		#rows to show for listbox. not used by popup
	 * @param [selectAttr]		additional attributes to defined for SELECT tag.
	 *				useful for holding javascript onChange='...' handlers.
	 & @param [compareFields0]	when we have 2 cols in recordset, we compare the defstr with
	 *				column 0 (1st col) if this is true. This is not documented.
	 *
	 * @return HTML
	 *
	 * changes by glen.davies@cce.ac.nz to support multiple hilited items
	 */
	function GetMenu($name,$defstr='',$blank1stItem=true,$multiple=false,
			$size=0, $selectAttr='',$compareFields0=true)
	{
		global $ADODB_INCLUDED_LIB;
		if (empty($ADODB_INCLUDED_LIB)) {
			include_once(ADODB_DIR.'/adodb-lib.inc.php');
		}
		return _adodb_getmenu($this, $name,$defstr,$blank1stItem,$multiple,
			$size, $selectAttr,$compareFields0);
	}



	/**
	 * Generate a SELECT tag string from a recordset, and return the string.
	 * If the recordset has 2 cols, we treat the 1st col as the containing
	 * the text to display to the user, and 2nd col as the return value. Default
	 * strings are compared with the SECOND column.
	 *
	 */
	function GetMenu2($name,$defstr='',$blank1stItem=true,$multiple=false,$size=0, $selectAttr='') {
		return $this->GetMenu($name,$defstr,$blank1stItem,$multiple,
			$size, $selectAttr,false);
	}

	/*
		Grouped Menu
	*/
	function GetMenu3($name,$defstr='',$blank1stItem=true,$multiple=false,
			$size=0, $selectAttr='')
	{
		global $ADODB_INCLUDED_LIB;
		if (empty($ADODB_INCLUDED_LIB)) {
			include_once(ADODB_DIR.'/adodb-lib.inc.php');
		}
		return _adodb_getmenu_gp($this, $name,$defstr,$blank1stItem,$multiple,
			$size, $selectAttr,false);
	}

	/**
	 * return recordset as a 2-dimensional array.
	 *
	 * @param [nRows]  is the number of rows to return. -1 means every row.
	 *
	 * @return an array indexed by the rows (0-based) from the recordset
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
		$arr = $this->GetArray($nRows);
		return $arr;
	}

	/*
	* Some databases allow multiple recordsets to be returned. This function
	* will return true if there is a next recordset, or false if no more.
	*/
	function NextRecordSet() {
		return false;
	}

	/**
	 * return recordset as a 2-dimensional array.
	 * Helper function for ADOConnection->SelectLimit()
	 *
	 * @param offset	is the row to start calculations from (1-based)
	 * @param [nrows]	is the number of rows to return
	 *
	 * @return an array indexed by the rows (0-based) from the recordset
	 */
	function GetArrayLimit($nrows,$offset=-1) {
		if ($offset <= 0) {
			$arr = $this->GetArray($nrows);
			return $arr;
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
	 * @param [nRows]  is the number of rows to return. -1 means every row.
	 *
	 * @return an array indexed by the rows (0-based) from the recordset
	 */
	function GetRows($nRows = -1) {
		$arr = $this->GetArray($nRows);
		return $arr;
	}

	/**
	 * return whole recordset as a 2-dimensional associative array if
	 * there are more than 2 columns. The first column is treated as the
	 * key and is not included in the array. If there is only 2 columns,
	 * it will return a 1 dimensional array of key-value pairs unless
	 * $force_array == true. This recordset method is currently part of
	 * the API, but may not be in later versions of ADOdb. By preference, use
	 * ADOconnnection::getAssoc()
	 *
	 * @param bool	$force_array	(optional) Has only meaning if we have 2 data
	 *								columns. If false, a 1 dimensional
	 * 								array is returned, otherwise a 2 dimensional
	 *								array is returned. If this sounds confusing,
	 * 								read the source.
	 *
	 * @param bool	$first2cols 	(optional) Means if there are more than
	 *								2 cols, ignore the remaining cols and
	 * 								instead of returning
	 *								array[col0] => array(remaining cols),
	 *								return array[col0] => col1
	 *
	 * @return mixed
	 *
	 */
	function getAssoc($force_array = false, $first2cols = false)
	{
		
		global $ADODB_FETCH_MODE;
		/*
		* Insufficient rows to show data
		*/
		if ($this->_numOfFields < 2)
			  return;

		/*
		* Empty recordset
		*/
		if (!$this->fields) {
			return array();
		}

		$numberOfFields = $this->_numOfFields;
		$fetchMode      = $ADODB_FETCH_MODE;

		if ($fetchMode == ADODB_FETCH_BOTH)
		{
			/*
			* build a template of numeric keys. you could improve the
			* speed by caching this, indexed by number of keys
			*/
			$testKeys = array_fill(0,$numberOfFields,0);

			/*
			* We use the associative method if ADODB_FETCH_BOTH
			*/
			$fetchMode = ADODB_FETCH_ASSOC;
		}

		$showArrayMethod = 0;

		if ($numberOfFields == 2)
			/*
			* Key is always value of first element
			* Value is alway value of second element
			*/
			$showArrayMethod = 1;

		if ($force_array)
			$showArrayMethod = 0;

		if ($first2cols)
			$showArrayMethod = 1;

		$results  = array();

		while (!$this->EOF){

			$myFields = $this->fields;

			if ($fetchMode == ADODB_FETCH_BOTH)
			{
				/*
				* extract the associative keys
				*/
				$myFields = array_diff_key($myFields,$testKeys);
			}

			/*
			* key is value of first element, rest is data,
			* The key is not case processed
			*/
			$key = array_shift($myFields);
			
			switch ($showArrayMethod)
			{
			case 0:

				if ($fetchMode == ADODB_FETCH_ASSOC)
				{
					/*
					* The driver should have already handled the key
					* casing, but in case it did not. We will check and force
					* this in later versions of ADOdb
					*/
					if (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_UPPER)
						$myFields = array_change_key_case($myFields,CASE_UPPER);

					elseif (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_LOWER)
						$myFields = array_change_key_case($myFields,CASE_LOWER);

					/*
					* We have already shifted the key off
					* the front, so the rest is the value
					*/
					$results[$key] = $myFields;

				}
				else
					/*
					 * I want the values in a numeric array,
					 * nicely re-indexed from zero
					 */
					$results[$key] = array_values($myFields);
				break;

			case 1:

				/*
				 * Don't care how long the array is,
				 * I just want value of second column, and it doesn't
				 * matter whether the array is associative or numeric
				 */
				$results[$key] = array_shift($myFields);
				break;
			}

			$this->MoveNext();
		}
		/*
		 * Done
		 */
		return $results;
	}

	/**
	 *
	 * @param v		is the character timestamp in YYYY-MM-DD hh:mm:ss format
	 * @param fmt	is the format to apply to it, using date()
	 *
	 * @return a timestamp formated as user desires
	 */
	function UserTimeStamp($v,$fmt='Y-m-d H:i:s') {
		if (is_numeric($v) && strlen($v)<14) {
			return adodb_date($fmt,$v);
		}
		$tt = $this->UnixTimeStamp($v);
		// $tt == -1 if pre TIMESTAMP_FIRST_YEAR
		if (($tt === false || $tt == -1) && $v != false) {
			return $v;
		}
		if ($tt === 0) {
			return $this->emptyTimeStamp;
		}
		return adodb_date($fmt,$tt);
	}


	/**
	 * @param v		is the character date in YYYY-MM-DD format, returned by database
	 * @param fmt	is the format to apply to it, using date()
	 *
	 * @return a date formated as user desires
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
		return adodb_date($fmt,$tt);
	}


	/**
	 * @param $v is a date string in YYYY-MM-DD format
	 *
	 * @return date in unix timestamp format, or 0 if before TIMESTAMP_FIRST_YEAR, or false if invalid date format
	 */
	static function UnixDate($v) {
		return ADOConnection::UnixDate($v);
	}


	/**
	 * @param $v is a timestamp string in YYYY-MM-DD HH-NN-SS format
	 *
	 * @return date in unix timestamp format, or 0 if before TIMESTAMP_FIRST_YEAR, or false if invalid date format
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
	*/
	function NumRows() {
		return $this->_numOfRows;
	}


	/**
	* PEAR DB compat, number of cols
	*/
	function NumCols() {
		return $this->_numOfFields;
	}

	/**
	* Fetch a row, returning false if no more rows.
	* This is PEAR DB compat mode.
	*
	* @return false or array containing the current record
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
	* @return DB_OK or error object
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
	 * @return true or false
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
	 * @return true or false
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
	 * @return true if there still rows available, or false if there are no more rows (EOF).
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
	 * @param rowNumber is the row to move to (0-based)
	 *
	 * @return true if there still rows available, or false if there are no more rows (EOF).
	 */
	function Move($rowNumber = 0) {
		$this->EOF = false;
		if ($rowNumber == $this->_currentRow) {
			return true;
		}
		if ($rowNumber >= $this->_numOfRows) {
			if ($this->_numOfRows != -1) {
				$rowNumber = $this->_numOfRows-2;
			}
		}

		if ($rowNumber < 0) {
			$this->EOF = true;
			return false;
		}

		if ($this->canSeek) {
			if ($this->_seek($rowNumber)) {
				$this->_currentRow = $rowNumber;
				if ($this->_fetch()) {
					return true;
				}
			} else {
				$this->EOF = true;
				return false;
			}
		} else {
			if ($rowNumber < $this->_currentRow) {
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
	 * Get the value of a field in the current row by column name.
	 * Will not work if ADODB_FETCH_MODE is set to ADODB_FETCH_NUM.
	 *
	 * @param colname  is the field to access
	 *
	 * @return the value of $colname column
	 */
	function Fields($colname) {
		return $this->fields[$colname];
	}

	/**
	 * Defines the function to use for table fields case conversion
	 * depending on ADODB_ASSOC_CASE
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
	 * @param int $upper Case for the array keys, defaults to uppercase
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
	 * @return true or false
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
	 * synonyms RecordCount and RowCount
	 *
	 * @return the number of rows or -1 if this is not supported
	 */
	function RecordCount() {
		return $this->_numOfRows;
	}


	/*
	* If we are using PageExecute(), this will return the maximum possible rows
	* that can be returned when paging a recordset.
	*/
	function MaxRecordCount() {
		return ($this->_maxRecordCount) ? $this->_maxRecordCount : $this->RecordCount();
	}

	/**
	 * synonyms RecordCount and RowCount
	 *
	 * @return the number of rows or -1 if this is not supported
	 */
	function RowCount() {
		return $this->_numOfRows;
	}


	 /**
	 * Portable RecordCount. Pablo Roca <pabloroca@mvps.org>
	 *
	 * @return  the number of records from a previous SELECT. All databases support this.
	 *
	 * But aware possible problems in multiuser environments. For better speed the table
	 * must be indexed by the condition. Heavy test this before deploying.
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
	 * Get the ADOFieldObject of a specific column.
	 *
	 * @param fieldoffset	is the column position to access(0-based).
	 *
	 * @return the ADOFieldObject for that column, or false.
	 */
	function FetchField($fieldoffset = -1) {
		// must be defined by child class

		return false;
	}

	/**
	 * Get the ADOFieldObjects of all columns in an array.
	 *
	 */
	function FieldTypesArray() {
		static $arr = array();
		if (empty($arr)) {
			for ($i=0, $max=$this->_numOfFields; $i < $max; $i++) {
				$arr[] = $this->FetchField($i);
			}
		}
		return $arr;
	}

	/**
	* Return the fields array of the current row as an object for convenience.
	* The default case is lowercase field names.
	*
	* @return the object with the properties set to the fields of the current row
	*/
	function FetchObj() {
		$o = $this->FetchObject(false);
		return $o;
	}

	/**
	* Return the fields array of the current row as an object for convenience.
	* The default case is uppercase.
	*
	* @param $isupper to set the object property names to uppercase
	*
	* @return the object with the properties set to the fields of the current row
	*/
	function FetchObject($isupper=true) {
		if (empty($this->_obj)) {
			$this->_obj = new ADOFetchObj();
			$this->_names = array();
			for ($i=0; $i <$this->_numOfFields; $i++) {
				$f = $this->FetchField($i);
				$this->_names[] = $f->name;
			}
		}
		$i = 0;
		if (PHP_VERSION >= 5) {
			$o = clone($this->_obj);
		} else {
			$o = $this->_obj;
		}

		for ($i=0; $i <$this->_numOfFields; $i++) {
			$name = $this->_names[$i];
			if ($isupper) {
				$n = strtoupper($name);
			} else {
				$n = $name;
			}

			$o->$n = $this->Fields($name);
		}
		return $o;
	}

	/**
	* Return the fields array of the current row as an object for convenience.
	* The default is lower-case field names.
	*
	* @return the object with the properties set to the fields of the current row,
	*	or false if EOF
	*
	* Fixed bug reported by tim@orotech.net
	*/
	function FetchNextObj() {
		$o = $this->FetchNextObject(false);
		return $o;
	}


	/**
	* Return the fields array of the current row as an object for convenience.
	* The default is upper case field names.
	*
	* @param $isupper to set the object property names to uppercase
	*
	* @return the object with the properties set to the fields of the current row,
	*	or false if EOF
	*
	* Fixed bug reported by tim@orotech.net
	*/
	function FetchNextObject($isupper=true) {
		$o = false;
		if ($this->_numOfRows != 0 && !$this->EOF) {
			$o = $this->FetchObject($isupper);
			$this->_currentRow++;
			if ($this->_fetch()) {
				return $o;
			}
		}
		$this->EOF = true;
		return $o;
	}

	/**
	 * Get the metatype of the column. This is used for formatting. This is because
	 * many databases use different names for the same type, so we transform the original
	 * type to our standardised version which uses 1 character codes:
	 *
	 * @param t  is the type passed in. Normally is ADOFieldObject->type.
	 * @param len is the maximum length of that field. This is because we treat character
	 *	fields bigger than a certain size as a 'B' (blob).
	 * @param fieldobj is the field object returned by the database driver. Can hold
	 *	additional info (eg. primary_key for mysql).
	 *
	 * @return the general type of the data:
	 *	C for character < 250 chars
	 *	X for teXt (>= 250 chars)
	 *	B for Binary
	 *	N for numeric or floating point
	 *	D for date
	 *	T for timestamp
	 *	L for logical/Boolean
	 *	I for integer
	 *	R for autoincrement counter/integer
	 *
	 *
	*/
	function MetaType($t,$len=-1,$fieldobj=false) {
		if (is_object($t)) {
			$fieldobj = $t;
			$t = $fieldobj->type;
			$len = $fieldobj->max_length;
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


		$tmap = false;
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
				if (!empty($fieldobj->primary_key)) {
					return 'R';
				}
				return 'I';

			case false:
				return 'N';

			case 'B':
				if (isset($fieldobj->binary)) {
					return ($fieldobj->binary) ? 'B' : 'X';
				}
				return 'B';

			case 'D':
				if (!empty($this->connection) && !empty($this->connection->datetime)) {
					return 'T';
				}
				return 'D';

			default:
				if ($t == 'LONG' && $this->dataProvider == 'oci8') {
					return 'B';
				}
				return $tmap;
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
	 */
	function AbsolutePage($page=-1) {
		if ($page != -1) {
			$this->_currentPage = $page;
		}
		return $this->_currentPage;
	}

	/**
	 * set/returns the status of the atFirstPage flag when paginating
	 */
	function AtFirstPage($status=false) {
		if ($status != false) {
			$this->_atFirstPage = $status;
		}
		return $this->_atFirstPage;
	}

	function LastPageNo($page = false) {
		if ($page != false) {
			$this->_lastPageNo = $page;
		}
		return $this->_lastPageNo;
	}

	/**
	 * set/returns the status of the atLastPage flag when paginating
	 */
	function AtLastPage($status=false) {
		if ($status != false) {
			$this->_atLastPage = $status;
		}
		return $this->_atLastPage;
	}

} // end class ADORecordSet