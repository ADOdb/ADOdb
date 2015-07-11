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
V5.20dev  ??-???-2014  (c) 2000-2014 John Lim. All rights reserved.
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.
  Latest version is available at http://adodb.sourceforge.net
  Portable version of oci8 driver, to make it more similar to other database drivers.
  The main differences are
   1. that the OCI_ASSOC names are in lowercase instead of uppercase.
   2. bind variables are mapped using ? instead of :<bindvar>
   Should some emulation of RecordCount() be implemented?
*/
// security - hide paths
if (!defined('ADODB_DIR')) die();
include_once(ADODB_DIR.'/drivers/adodb-oci8.inc.php');

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
class ADODB_oci8po extends ADODB_oci8 {
	var $databaseType = 'oci8po';
	var $dataProvider = 'oci8';
	var $metaColumnsSQL = "select lower(cname),coltype,width, SCALE, PRECISION, NULLS, DEFAULTVAL from col where tname='%s' order by colno"; //changed by smondino@users.sourceforge. net
	var $metaTablesSQL = "select lower(table_name),table_type from cat where table_type in ('TABLE','VIEW')";

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
    function ADODB_oci8po()
	{
		$this->_hasOCIFetchStatement = ADODB_PHPVER >= 0x4200;
		# oci8po does not support adodb extension: adodb_movenext()
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
    function Param($name,$type='C')
	{
		return '?';
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
    function Prepare($sql,$cursor=false)
	{
		$sqlarr = explode('?',$sql);
		$sql = $sqlarr[0];
		for ($i = 1, $max = sizeof($sqlarr); $i < $max; $i++) {
			$sql .=  ':'.($i-1) . $sqlarr[$i];
		}
		return ADODB_oci8::Prepare($sql,$cursor);
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
    function Execute($sql,$inputarr=false)
	{
		return ADOConnection::Execute($sql,$inputarr);
	}
	// emulate handling of parameters ? ?, replacing with :bind0 :bind1

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
    function _query($sql,$inputarr=false)
	{
		if (is_array($inputarr)) {
			$i = 0;
			if (is_array($sql)) {
				foreach($inputarr as $v) {
					$arr['bind'.$i++] = $v;
				}
			} else {
				// Need to identify if the ? is inside a quoted string, and if
				// so not use it as a bind variable
				preg_match_all('/".*\??"|\'.*\?.*?\'/', $sql, $matches);
				foreach($matches[0] as $qmMatch){
					$qmReplace = str_replace('?', '-QUESTIONMARK-', $qmMatch);
					$sql = str_replace($qmMatch, $qmReplace, $sql);
				}
				$sqlarr = explode('?',$sql);
				$sql = $sqlarr[0];
				foreach($inputarr as $k => $v) {
					$sql .=  ":$k" . $sqlarr[++$i];
				}
				$sql = str_replace('-QUESTIONMARK-', '?', $sql);
			}
		}
		return ADODB_oci8::_query($sql,$inputarr);
	}
}
/*--------------------------------------------------------------------------------------
		 Class Name: Recordset
--------------------------------------------------------------------------------------*/

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
class ADORecordset_oci8po extends ADORecordset_oci8 {
	var $databaseType = 'oci8po';

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
    function ADORecordset_oci8po($queryID,$mode=false)
	{
		$this->ADORecordset_oci8($queryID,$mode);
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
    function Fields($colname)
	{
		if ($this->fetchMode & OCI_ASSOC) return $this->fields[$colname];
		if (!$this->bind) {
			$this->bind = array();
			for ($i=0; $i < $this->_numOfFields; $i++) {
				$o = $this->FetchField($i);
				$this->bind[strtoupper($o->name)] = $i;
			}
		}
		 return $this->fields[$this->bind[strtoupper($colname)]];
	}
	// lowercase field names...

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
    function _FetchField($fieldOffset = -1)
	{
		$fld = new ADOFieldObject;
		$fieldOffset += 1;
		$fld->name = OCIcolumnname($this->_queryID, $fieldOffset);
		if (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_LOWER) {
			$fld->name = strtolower($fld->name);
		}
		$fld->type = OCIcolumntype($this->_queryID, $fieldOffset);
		$fld->max_length = OCIcolumnsize($this->_queryID, $fieldOffset);
		if ($fld->type == 'NUMBER') {
			$sc = OCIColumnScale($this->_queryID, $fieldOffset);
			if ($sc == 0) {
				$fld->type = 'INT';
			}
		}
		return $fld;
	}
	// 10% speedup to move MoveNext to child class

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
		if(@OCIfetchinto($this->_queryID,$this->fields,$this->fetchMode)) {
		global $ADODB_ANSI_PADDING_OFF;
			$this->_currentRow++;
			$this->_updatefields();
			if (!empty($ADODB_ANSI_PADDING_OFF)) {
				foreach($this->fields as $k => $v) {
					if (is_string($v)) $this->fields[$k] = rtrim($v);
				}
			}
			return true;
		}
		if (!$this->EOF) {
			$this->EOF = true;
			$this->_currentRow++;
		}
		return false;
	}
	/* Optimize SelectLimit() by using OCIFetch() instead of OCIFetchInto() */

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
    function GetArrayLimit($nrows,$offset=-1)
	{
		if ($offset <= 0) {
			$arr = $this->GetArray($nrows);
			return $arr;
		}
		for ($i=1; $i < $offset; $i++)
			if (!@OCIFetch($this->_queryID)) {
				$arr = array();
				return $arr;
			}
		if (!@OCIfetchinto($this->_queryID,$this->fields,$this->fetchMode)) {
			$arr = array();
			return $arr;
		}
		$this->_updatefields();
		$results = array();
		$cnt = 0;
		while (!$this->EOF && $nrows != $cnt) {
			$results[$cnt++] = $this->fields;
			$this->MoveNext();
		}
		return $results;
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
    function _fetch()
	{
		global $ADODB_ANSI_PADDING_OFF;
		$ret = @OCIfetchinto($this->_queryID,$this->fields,$this->fetchMode);
		if ($ret) {
			$this->_updatefields();
			if (!empty($ADODB_ANSI_PADDING_OFF)) {
				foreach($this->fields as $k => $v) {
					if (is_string($v)) $this->fields[$k] = rtrim($v);
				}
			}
		}
		return $ret;
	}
}
