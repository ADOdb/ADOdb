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
 * Requires PHP4.01pl2 or later because it uses include_once
*/
/*
	Filter all fields and all rows in a recordset and returns the
	processed recordset. We scroll to the beginning of the new recordset
	after processing.
	We pass a recordset and function name to RSFilter($rs,'rowfunc');
	and the function will be called multiple times, once
	for each row in the recordset. The function will be passed
	an array containing one row repeatedly.
	Example:
	// ucwords() every element in the recordset

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
    function do_ucwords(&$arr,$rs)
	{
		foreach($arr as $k => $v) {
			$arr[$k] = ucwords($v);
		}
	}
	$rs = RSFilter($rs,'do_ucwords');
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
function RSFilter($rs,$fn)
{
	if ($rs->databaseType != 'array') {
		if (!$rs->connection) return false;
		$rs = $rs->connection->_rs2rs($rs);
	}
	$rows = $rs->RecordCount();
	for ($i=0; $i < $rows; $i++) {
		if (is_array ($fn)) {
        	$obj = $fn[0];
        	$method = $fn[1];
        	$obj->$method ($rs->_array[$i],$rs);
      } else {
			$fn($rs->_array[$i],$rs);
      }
	}
	if (!$rs->EOF) {
		$rs->_currentRow = 0;
		$rs->fields = $rs->_array[0];
	}
	return $rs;
}
