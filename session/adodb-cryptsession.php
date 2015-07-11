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
/*
V5.20dev  ??-???-2014  (c) 2000-2014 John Lim (jlim#natsoft.com). All rights reserved.
         Contributed by Ross Smith (adodb@netebb.com).
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.
	  Set tabs to 4 for best viewing.
*/
/*
This file is provided for backwards compatibility purposes
*/
if (!defined('ADODB_SESSION')) {
	require_once dirname(__FILE__) . '/adodb-session.php';
}
require_once  ADODB_SESSION . '/adodb-encrypt-md5.php';
ADODB_Session::filter(new ADODB_Encrypt_MD5());
