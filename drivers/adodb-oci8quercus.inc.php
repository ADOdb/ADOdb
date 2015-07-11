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
class ADODB_oci8quercus extends ADODB_oci8 {
	var $databaseType = 'oci8quercus';
	var $dataProvider = 'oci8';

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
    function ADODB_oci8quercus()
	{
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
class ADORecordset_oci8quercus extends ADORecordset_oci8 {
	var $databaseType = 'oci8quercus';

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
    function ADORecordset_oci8quercus($queryID,$mode=false)
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
    function _FetchField($fieldOffset = -1)
	{
	global $QUERCUS;
		$fld = new ADOFieldObject;
		if (!empty($QUERCUS)) {
			$fld->name = oci_field_name($this->_queryID, $fieldOffset);
			$fld->type = oci_field_type($this->_queryID, $fieldOffset);
			$fld->max_length = oci_field_size($this->_queryID, $fieldOffset);
			//if ($fld->name == 'VAL6_NUM_12_4') $fld->type = 'NUMBER';
			switch($fld->type) {
				case 'string': $fld->type = 'VARCHAR'; break;
				case 'real': $fld->type = 'NUMBER'; break;
			}
		} else {
			$fieldOffset += 1;
			$fld->name = oci_field_name($this->_queryID, $fieldOffset);
			$fld->type = oci_field_type($this->_queryID, $fieldOffset);
			$fld->max_length = oci_field_size($this->_queryID, $fieldOffset);
		}
	 	switch($fld->type) {
		case 'NUMBER':
	 		$p = oci_field_precision($this->_queryID, $fieldOffset);
			$sc = oci_field_scale($this->_queryID, $fieldOffset);
			if ($p != 0 && $sc == 0) $fld->type = 'INT';
			$fld->scale = $p;
			break;
	 	case 'CLOB':
		case 'NCLOB':
		case 'BLOB':
			$fld->max_length = -1;
			break;
		}
		return $fld;
	}
}
