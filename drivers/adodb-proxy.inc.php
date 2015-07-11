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
  Synonym for csv driver.
*/
// security - hide paths
if (!defined('ADODB_DIR')) die();
if (! defined("_ADODB_PROXY_LAYER")) {
	 define("_ADODB_PROXY_LAYER", 1 );
	 include(ADODB_DIR."/drivers/adodb-csv.inc.php");
	class ADODB_proxy extends ADODB_csv {
		var $databaseType = 'proxy';
		var $databaseProvider = 'csv';
	}
	class ADORecordset_proxy extends ADORecordset_csv {
	var $databaseType = "proxy";
		function ADORecordset_proxy($id,$mode=false)
		{
			$this->ADORecordset($id,$mode);
		}
	};
} // define
