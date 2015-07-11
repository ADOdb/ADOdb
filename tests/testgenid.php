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
	V4.50 6 July 2004
	Run multiple copies of this php script at the same time
	to test unique generation of id's in multiuser mode
*/
include_once('../adodb.inc.php');
$testaccess = true;
include_once('testdatabases.inc.php');

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
function testdb(&$db,$createtab="create table ADOXYZ (id int, firstname char(24), lastname char(24), created date)")
{
	$table = 'adodbseq';
	$db->Execute("drop table $table");
	//$db->debug=true;
	$ctr = 5000;
	$lastnum = 0;
	while (--$ctr >= 0) {
		$num = $db->GenID($table);
		if ($num === false) {
			print "GenID returned false";
			break;
		}
		if ($lastnum + 1 == $num) print " $num ";
		else {
			print " <font color=red>$num</font> ";
			flush();
		}
		$lastnum = $num;
	}
}
