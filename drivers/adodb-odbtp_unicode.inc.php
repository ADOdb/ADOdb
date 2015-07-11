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
  the BSD license will take precedence. See License.txt.
  Set tabs to 4 for best viewing.
  Latest version is available at http://adodb.sourceforge.net
*/
// Code contributed by "Robert Twitty" <rtwitty#neutron.ushmm.org>
// security - hide paths
if (!defined('ADODB_DIR')) die();
/*
    Because the ODBTP server sends and reads UNICODE text data using UTF-8
    encoding, the following HTML meta tag must be included within the HTML
    head section of every HTML form and script page:
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    Also, all SQL query strings must be submitted as UTF-8 encoded text.
*/
if (!defined('_ADODB_ODBTP_LAYER')) {
	include(ADODB_DIR."/drivers/adodb-odbtp.inc.php");
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
class ADODB_odbtp_unicode extends ADODB_odbtp {
	var $databaseType = 'odbtp';
	var $_useUnicodeSQL = true;

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
    function ADODB_odbtp_unicode()
	{
		$this->ADODB_odbtp();
	}
}
