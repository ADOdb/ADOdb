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
include_once('../adodb.inc.php');
$rs = new ADORecordSet_array();
$array = array(
array ('Name', 'Age'),
array ('John', '12'),
array ('Jill', '8'),
array ('Bill', '49')
);
$typearr = array('C','I');
$rs->InitArray($array,$typearr);
while (!$rs->EOF) {
	print_r($rs->fields);echo "<br>";
	$rs->MoveNext();
}
echo "<hr /> 1 Seek<br>";
$rs->Move(1);
while (!$rs->EOF) {
	print_r($rs->fields);echo "<br>";
	$rs->MoveNext();
}
echo "<hr /> 2 Seek<br>";
$rs->Move(2);
while (!$rs->EOF) {
	print_r($rs->fields);echo "<br>";
	$rs->MoveNext();
}
echo "<hr /> 3 Seek<br>";
$rs->Move(3);
while (!$rs->EOF) {
	print_r($rs->fields);echo "<br>";
	$rs->MoveNext();
}
die();
