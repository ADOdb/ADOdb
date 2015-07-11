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
  Set tabs to 8.
  MySQL code that supports transactions. For MySQL 3.23 or later.
  Code from James Poon <jpoon88@yahoo.com>
  Requires mysql client. Works on Windows and Unix.
*/
// security - hide paths
if (!defined('ADODB_DIR')) die();
include_once(ADODB_DIR."/drivers/adodb-mysql.inc.php");

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
class ADODB_mysqlt extends ADODB_mysql {
	var $databaseType = 'mysqlt';
	var $ansiOuter = true; // for Version 3.23.17 or later
	var $hasTransactions = true;
	var $autoRollback = true; // apparently mysql does not autorollback properly

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
    function ADODB_mysqlt()
	{
	global $ADODB_EXTENSION; if ($ADODB_EXTENSION) $this->rsPrefix .= 'ext_';
	}
	/* set transaction mode
	SET [GLOBAL | SESSION] TRANSACTION ISOLATION LEVEL
{ READ UNCOMMITTED | READ COMMITTED | REPEATABLE READ | SERIALIZABLE }
	*/

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
    function SetTransactionMode( $transaction_mode )
	{
		$this->_transmode  = $transaction_mode;
		if (empty($transaction_mode)) {
			$this->Execute('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
			return;
		}
		if (!stristr($transaction_mode,'isolation')) $transaction_mode = 'ISOLATION LEVEL '.$transaction_mode;
		$this->Execute("SET SESSION TRANSACTION ".$transaction_mode);
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
    function BeginTrans()
	{
		if ($this->transOff) return true;
		$this->transCnt += 1;
		$this->Execute('SET AUTOCOMMIT=0');
		$this->Execute('BEGIN');
		return true;
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
    function CommitTrans($ok=true)
	{
		if ($this->transOff) return true;
		if (!$ok) return $this->RollbackTrans();
		if ($this->transCnt) $this->transCnt -= 1;
		$ok = $this->Execute('COMMIT');
		$this->Execute('SET AUTOCOMMIT=1');
		return $ok ? true : false;
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
    function RollbackTrans()
	{
		if ($this->transOff) return true;
		if ($this->transCnt) $this->transCnt -= 1;
		$ok = $this->Execute('ROLLBACK');
		$this->Execute('SET AUTOCOMMIT=1');
		return $ok ? true : false;
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
    function RowLock($tables,$where='',$col='1 as adodbignore')
	{
		if ($this->transCnt==0) $this->BeginTrans();
		if ($where) $where = ' where '.$where;
		$rs = $this->Execute("select $col from $tables $where for update");
		return !empty($rs);
	}
}

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
class ADORecordSet_mysqlt extends ADORecordSet_mysql{
	var $databaseType = "mysqlt";

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
    function ADORecordSet_mysqlt($queryID,$mode=false)
	{
		if ($mode === false) {
			global $ADODB_FETCH_MODE;
			$mode = $ADODB_FETCH_MODE;
		}
		switch ($mode)
		{
		case ADODB_FETCH_NUM: $this->fetchMode = MYSQL_NUM; break;
		case ADODB_FETCH_ASSOC:$this->fetchMode = MYSQL_ASSOC; break;
		case ADODB_FETCH_DEFAULT:
		case ADODB_FETCH_BOTH:
		default: $this->fetchMode = MYSQL_BOTH; break;
		}
		$this->adodbFetchMode = $mode;
		$this->ADORecordSet($queryID);
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
    function MoveNext()
	{
		if (@$this->fields = mysql_fetch_array($this->_queryID,$this->fetchMode)) {
			$this->_currentRow += 1;
			return true;
		}
		if (!$this->EOF) {
			$this->_currentRow += 1;
			$this->EOF = true;
		}
		return false;
	}
}

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
class ADORecordSet_ext_mysqlt extends ADORecordSet_mysqlt {

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
    function ADORecordSet_ext_mysqlt($queryID,$mode=false)
	{
		if ($mode === false) {
			global $ADODB_FETCH_MODE;
			$mode = $ADODB_FETCH_MODE;
		}
		switch ($mode)
		{
		case ADODB_FETCH_NUM: $this->fetchMode = MYSQL_NUM; break;
		case ADODB_FETCH_ASSOC:$this->fetchMode = MYSQL_ASSOC; break;
		case ADODB_FETCH_DEFAULT:
		case ADODB_FETCH_BOTH:
		default:
			$this->fetchMode = MYSQL_BOTH; break;
		}
		$this->adodbFetchMode = $mode;
		$this->ADORecordSet($queryID);
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
    function MoveNext()
	{
		return adodb_movenext($this);
	}
}
