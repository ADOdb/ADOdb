<?php
/**
 * Class ADORecordSet_mysqli
 */
namespace ADOdb\Resources\MySQL;

use ADOdb\Resources\ADOFieldObject;

require_once ADODB_DIR . '/Resources/ADORecordSet.php';

class ADORecordSet extends \ADOdb\Resources\ADORecordSet
{

	var $databaseType = "mysqli";
	var $canSeek = true;

	/** @var ADODB_mysqli The parent connection */
	var $connection = false;

	/** @var mysqli_result result link identifier */
	var $_queryID;

	function __construct($queryID, $mode = false)
	{
		parent::__construct($queryID, $mode);

		switch ($this->adodbFetchMode) {
			case ADODB_FETCH_NUM:
				$this->fetchMode = MYSQLI_NUM;
				break;
			case ADODB_FETCH_ASSOC:
				$this->fetchMode = MYSQLI_ASSOC;
				break;
			case ADODB_FETCH_DEFAULT:
			case ADODB_FETCH_BOTH:
			default:
				$this->fetchMode = MYSQLI_BOTH;
				break;
		}
	}

	function _initrs()
	{
	global $ADODB_COUNTRECS;

		$this->_numOfRows = $ADODB_COUNTRECS ? @mysqli_num_rows($this->_queryID) : -1;
		$this->_numOfFields = @mysqli_num_fields($this->_queryID);
	}

/*
1      = MYSQLI_NOT_NULL_FLAG
2      = MYSQLI_PRI_KEY_FLAG
4      = MYSQLI_UNIQUE_KEY_FLAG
8      = MYSQLI_MULTIPLE_KEY_FLAG
16     = MYSQLI_BLOB_FLAG
32     = MYSQLI_UNSIGNED_FLAG
64     = MYSQLI_ZEROFILL_FLAG
128    = MYSQLI_BINARY_FLAG
256    = MYSQLI_ENUM_FLAG
512    = MYSQLI_AUTO_INCREMENT_FLAG
1024   = MYSQLI_TIMESTAMP_FLAG
2048   = MYSQLI_SET_FLAG
32768  = MYSQLI_NUM_FLAG
16384  = MYSQLI_PART_KEY_FLAG
32768  = MYSQLI_GROUP_FLAG
65536  = MYSQLI_UNIQUE_FLAG
131072 = MYSQLI_BINCMP_FLAG
*/

	/**
	 * Returns raw, database specific information about a field.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:recordset:fetchfield
	 *
	 * @param int $fieldOffset (Optional) The field number to get information for.
	 *
	 * @return ADOFieldObject|bool
	 */
	function FetchField($fieldOffset = -1)
	{
		if ($fieldOffset < -1 || $fieldOffset >= $this->_numOfFields) {
			if ($this->connection->debug) {
				ADOConnection::outp("FetchField: field offset out of range: $fieldOffset");
			}
			return false;
		}
		$fieldnr = $fieldOffset;
		if ($fieldOffset != -1) {
			@mysqli_field_seek($this->_queryID, $fieldnr);
		}
		$o = @mysqli_fetch_field($this->_queryID);
		if (!$o) return false;

		//Fix for HHVM
		if ( !isset($o->flags) ) {
			$o->flags = 0;
		}
		/* Properties of an ADOFieldObject as set by MetaColumns */
		$o->primary_key = $o->flags & MYSQLI_PRI_KEY_FLAG;
		$o->not_null = $o->flags & MYSQLI_NOT_NULL_FLAG;
		$o->auto_increment = $o->flags & MYSQLI_AUTO_INCREMENT_FLAG;
		$o->binary = $o->flags & MYSQLI_BINARY_FLAG;
		// $o->blob = $o->flags & MYSQLI_BLOB_FLAG; /* not returned by MetaColumns */
		$o->unsigned = $o->flags & MYSQLI_UNSIGNED_FLAG;

		/*
		* Trivial method to cast class to ADOfieldObject
		*/
		$a = new ADOFieldObject;
		foreach (get_object_vars($o) as $key => $name)
			$a->$key = $name;
		return $a;
	}

	/**
	 * Reads a row in associative mode if the recordset fetch mode is numeric.
	 * Using this function when the fetch mode is set to ADODB_FETCH_ASSOC may produce unpredictable results.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:getrowassoc
	 *
	 * @param int $upper Indicates whether the keys of the recordset should be upper case or lower case.
	 *
	 * @return array|bool
	 */
	function GetRowAssoc($upper = ADODB_ASSOC_CASE)
	{
		if ($this->fetchMode == MYSQLI_ASSOC && $upper == ADODB_ASSOC_CASE_LOWER) {
			return $this->fields;
		}
		return parent::getRowAssoc($upper);
	}

	/**
	 * Returns a single field in a single row of the current recordset.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:recordset:fields
	 *
	 * @param string $colname The name of the field to retrieve.
	 *
	 * @return mixed
	 */
	function Fields($colname)
	{
		if ($this->fetchMode != MYSQLI_NUM) {
			return @$this->fields[$colname];
		}

		if (!$this->bind) {
			$this->bind = array();
			for ($i = 0; $i < $this->_numOfFields; $i++) {
				$o = $this->fetchField($i);
				$this->bind[strtoupper($o->name)] = $i;
			}
		}
		return $this->fields[$this->bind[strtoupper($colname)]];
	}

	/**
	 * Adjusts the result pointer to an arbitrary row in the result.
	 *
	 * @param int $row The row to seek to.
	 *
	 * @return bool False if the recordset contains no rows, otherwise true.
	 */
	function _seek($row)
	{
		if ($this->_numOfRows == 0 || $row < 0) {
			return false;
		}

		mysqli_data_seek($this->_queryID, $row);
		$this->EOF = false;
		return true;
	}

	/**
	 * In databases that allow accessing of recordsets, retrieves the next set.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:recordset:nextrecordset
	 *
	 * @return bool
	 */
	function NextRecordSet()
	{
		global $ADODB_COUNTRECS;

		mysqli_free_result($this->_queryID);
		$this->_queryID = -1;
		// Move to the next recordset, or return false if there is none. In a stored proc
		// call, mysqli_next_result returns true for the last "recordset", but mysqli_store_result
		// returns false. I think this is because the last "recordset" is actually just the
		// return value of the stored proc (ie the number of rows affected).
		if (!mysqli_next_result($this->connection->_connectionID)) {
			return false;
		}

		// CD: There is no $this->_connectionID variable, at least in the ADO version I'm using
		$this->_queryID = ($ADODB_COUNTRECS) ? @mysqli_store_result($this->connection->_connectionID)
			: @mysqli_use_result($this->connection->_connectionID);

		if (!$this->_queryID) {
			return false;
		}

		$this->_inited     = false;
		$this->bind        = false;
		$this->_currentRow = -1;
		$this->init();
		return true;
	}

	/**
	 * Moves the cursor to the next record of the recordset from the current position.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:movenext
	 *
	 * @return bool False if there are no more records to move on to, otherwise true.
	 */
	function MoveNext()
	{
		if ($this->EOF) return false;
		$this->_currentRow++;
		$this->fields = @mysqli_fetch_array($this->_queryID,$this->fetchMode);

		if (is_array($this->fields)) {
			$this->_updatefields();
			return true;
		}
		$this->EOF = true;
		return false;
	}

	/**
	 * Attempt to fetch a result row using the current fetch mode and return whether or not this was successful.
	 *
	 * @return bool True if row was fetched successfully, otherwise false.
	 */
	function _fetch()
	{
		$this->fields = mysqli_fetch_array($this->_queryID,$this->fetchMode);
		$this->_updatefields();
		return is_array($this->fields);
	}

	/**
	 * Frees the memory associated with a result.
	 *
	 * @return void
	 */
	function _close()
	{
		//if results are attached to this pointer from Stored Procedure calls, the next standard query will die 2014
		//only a problem with persistent connections

		if (isset($this->connection->_connectionID) && $this->connection->_connectionID) {
			while (mysqli_more_results($this->connection->_connectionID)) {
				mysqli_next_result($this->connection->_connectionID);
			}
		}

		if ($this->_queryID instanceof mysqli_result) {
			mysqli_free_result($this->_queryID);
		}
		$this->_queryID = false;
	}

} // rs class
