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
/**
 * @version V5.20dev  ??-???-2014  (c) 2000-2014 John Lim (jlim#natsoft.com). All rights reserved.
 * Released under both BSD license and Lesser GPL library license.
 * Whenever there is any discrepancy between the two licenses,
 * the BSD license will take precedence.
 *
 * Set tabs to 4 for best viewing.
 *
 * Latest version is available at http://php.weblogs.com
 *
 * Oracle 8.0.5 driver
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
class ADODB_oci805 extends ADODB_oci8 {
	var $databaseType = "oci805";
	var $connectSID = true;

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
    function ADODB_oci805()
	{
		$this->ADODB_oci8();
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
    function SelectLimit($sql,$nrows=-1,$offset=-1, $inputarr=false,$secs2cache=0)
	{
		// seems that oracle only supports 1 hint comment in 8i
		if (strpos($sql,'/*+') !== false)
			$sql = str_replace('/*+ ','/*+FIRST_ROWS ',$sql);
		else
			$sql = preg_replace('/^[ \t\n]*select/i','SELECT /*+FIRST_ROWS*/',$sql);
		/*
			The following is only available from 8.1.5 because order by in inline views not
			available before then...
			http://www.jlcomp.demon.co.uk/faq/top_sql.html
		if ($nrows > 0) {
			if ($offset > 0) $nrows += $offset;
			$sql = "select * from ($sql) where rownum <= $nrows";
			$nrows = -1;
		}
		*/
		return ADOConnection::SelectLimit($sql,$nrows,$offset,$inputarr,$secs2cache);
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
class ADORecordset_oci805 extends ADORecordset_oci8 {
	var $databaseType = "oci805";

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
    function ADORecordset_oci805($id,$mode=false)
	{
		$this->ADORecordset_oci8($id,$mode);
	}
}
