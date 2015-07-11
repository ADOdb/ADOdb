<?php
/** 
* This is the short description placeholder for the generic file docblock 
* 
* This is the long description placeholder for the generic file docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @category   FIXME
* @package    ADODB 
* @author     John Lim 
* @copyright  2014-      The ADODB project 
* @copyright  2000-2014 John Lim 
* @license    BSD License    (Primary) 
* @license    Lesser GPL License    (Secondary) 
* @version    5.21.0 
* 
* @adodb-filecheck-status: FIXME
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
 */
error_reporting(E_ALL);
$path = dirname(__FILE__);
include("$path/../adodb-exceptions.inc.php");
include("$path/../adodb.inc.php");
try {
$db = NewADOConnection("oci8");
$db->Connect('','scott','natsoft');
$db->debug=1;
$cnt = $db->GetOne("select count(*) from adoxyz");
$rs = $db->Execute("select * from adoxyz order by id");
$i = 0;
foreach($rs as $k => $v) {
	$i += 1;
	echo $k; adodb_pr($v);
	flush();
}
if ($i != $cnt) die("actual cnt is $i, cnt should be $cnt\n");
$rs = $db->Execute("select bad from badder");
} catch (exception $e) {
	adodb_pr($e);
	$e = adodb_backtrace($e->trace);
}
