<?php
/** 
* This is the short description placeholder for the generic file docblock 
* 
* This is the long description placeholder for the generic file docblock
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @author     John Lim 
* @copyright  2014-      The ADODB project 
* @copyright  2000-2014 John Lim 
* @license    BSD License    (Primary) 
* @license    Lesser GPL License    (Secondary) 
* @version    5.21.0 
* @package    ADODB 
* @category   FIXME 
* 
* @adodb-filecheck-status: FIXME
* @adodb-driver-status: FIXME;
* @adodb-codesniffer-status: FIXME
* @adodb-documentor-status: FIXME
* 
*/ 
/*
V5.20dev  ??-???-2014  (c) 2000-2014 John Lim (jlim#natsoft.com). All rights reserved.
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.
  Set tabs to 4.
  Currently unsupported: MetaDatabases, MetaTables and MetaColumns, and also inputarr in Execute.
  Native types have been converted to MetaTypes.
  Transactions not supported yet.
  Limitation of url length. For IIS, see MaxClientRequestBuffer registry value.
	  http://support.microsoft.com/default.aspx?scid=kb;en-us;260694
*/
// security - hide paths
if (!defined('ADODB_DIR')) die();
if (! defined("_ADODB_CSV_LAYER")) {
 define("_ADODB_CSV_LAYER", 1 );
include_once(ADODB_DIR.'/adodb-csvlib.inc.php');

/** 
* This is the short description placeholder for the class docblock 
*  
* This is the long description placeholder for the class docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @version 5.21.0 
* 
* @adodb-class-status FIXME
*/
class ADODB_csv extends ADOConnection {
	var $databaseType = 'csv';
	var $databaseProvider = 'csv';
	var $hasInsertID = true;
	var $hasAffectedRows = true;
	var $fmtTimeStamp = "'Y-m-d H:i:s'";
	var $_affectedrows=0;
	var $_insertid=0;
	var $_url;
	var $replaceQuote = "''"; // string to use to replace quotes
	var $hasTransactions = false;
	var $_errorNo = false;

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function ADODB_csv()
	{
	}

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function _insertid()
	{
			return $this->_insertid;
	}

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function _affectedrows()
	{
			return $this->_affectedrows;
	}
  	function MetaDatabases()
	{
		return false;
	}
	// returns true or false

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function _connect($argHostname, $argUsername, $argPassword, $argDatabasename)
	{
		if (strtolower(substr($argHostname,0,7)) !== 'http://') return false;
		$this->_url = $argHostname;
		return true;
	}
	// returns true or false

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function _pconnect($argHostname, $argUsername, $argPassword, $argDatabasename)
	{
		if (strtolower(substr($argHostname,0,7)) !== 'http://') return false;
		$this->_url = $argHostname;
		return true;
	}
 	function MetaColumns($table, $normalize=true)
	{
		return false;
	}
	// parameters use PostgreSQL convention, not MySQL

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function SelectLimit($sql,$nrows=-1,$offset=-1)
	{
	global $ADODB_FETCH_MODE;
		$url = $this->_url.'?sql='.urlencode($sql)."&nrows=$nrows&fetch=".
			(($this->fetchMode !== false)?$this->fetchMode : $ADODB_FETCH_MODE).
			"&offset=$offset";
		$err = false;
		$rs = csv2rs($url,$err,false);
		if ($this->debug) print "$url<br><i>$err</i><br>";
		$at = strpos($err,'::::');
		if ($at === false) {
			$this->_errorMsg = $err;
			$this->_errorNo = (integer)$err;
		} else {
			$this->_errorMsg = substr($err,$at+4,1024);
			$this->_errorNo = -9999;
		}
		if ($this->_errorNo)
			if ($fn = $this->raiseErrorFn) {
				$fn($this->databaseType,'EXECUTE',$this->ErrorNo(),$this->ErrorMsg(),$sql,'');
			}
		if (is_object($rs)) {
			$rs->databaseType='csv';
			$rs->fetchMode = ($this->fetchMode !== false) ?  $this->fetchMode : $ADODB_FETCH_MODE;
			$rs->connection = $this;
		}
		return $rs;
	}
	// returns queryID or false

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function _Execute($sql,$inputarr=false)
	{
	global $ADODB_FETCH_MODE;
		if (!$this->_bindInputArray && $inputarr) {
			$sqlarr = explode('?',$sql);
			$sql = '';
			$i = 0;
			foreach($inputarr as $v) {
				$sql .= $sqlarr[$i];
				if (gettype($v) == 'string')
					$sql .= $this->qstr($v);
				else if ($v === null)
					$sql .= 'NULL';
				else
					$sql .= $v;
				$i += 1;
			}
			$sql .= $sqlarr[$i];
			if ($i+1 != sizeof($sqlarr))
				print "Input Array does not match ?: ".htmlspecialchars($sql);
			$inputarr = false;
		}
		$url =  $this->_url.'?sql='.urlencode($sql)."&fetch=".
			(($this->fetchMode !== false)?$this->fetchMode : $ADODB_FETCH_MODE);
		$err = false;
		$rs = csv2rs($url,$err,false);
		if ($this->debug) print urldecode($url)."<br><i>$err</i><br>";
		$at = strpos($err,'::::');
		if ($at === false) {
			$this->_errorMsg = $err;
			$this->_errorNo = (integer)$err;
		} else {
			$this->_errorMsg = substr($err,$at+4,1024);
			$this->_errorNo = -9999;
		}
		if ($this->_errorNo)
			if ($fn = $this->raiseErrorFn) {
				$fn($this->databaseType,'EXECUTE',$this->ErrorNo(),$this->ErrorMsg(),$sql,$inputarr);
			}
		if (is_object($rs)) {
			$rs->fetchMode = ($this->fetchMode !== false) ?  $this->fetchMode : $ADODB_FETCH_MODE;
			$this->_affectedrows = $rs->affectedrows;
			$this->_insertid = $rs->insertid;
			$rs->databaseType='csv';
			$rs->connection = $this;
		}
		return $rs;
	}
	/*	Returns: the last error message from previous database operation	*/

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function ErrorMsg()
	{
			return $this->_errorMsg;
	}
	/*	Returns: the last error number from previous database operation	*/

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function ErrorNo()
	{
		return $this->_errorNo;
	}
	// returns true or false

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function _close()
	{
		return true;
	}
} // class

/** 
* This is the short description placeholder for the class docblock 
*  
* This is the long description placeholder for the class docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @version 5.21.0 
* 
* @adodb-class-status FIXME
*/
class ADORecordset_csv extends ADORecordset {

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function ADORecordset_csv($id,$mode=false)
	{
		$this->ADORecordset($id,$mode);
	}

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function _close()
	{
		return true;
	}
}
} // define
