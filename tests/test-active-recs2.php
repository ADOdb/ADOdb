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
error_reporting(E_ALL);
include('../adodb.inc.php');
include('../adodb-active-record.inc.php');
###########################
$ADODB_ACTIVE_CACHESECS = 36;
$DBMS = @$_GET['db'];
$DBMS = 'mysql';
if ($DBMS == 'mysql') {
	$db = NewADOConnection('mysql://root@localhost/northwind');
} else if ($DBMS == 'postgres') {
	$db = NewADOConnection('postgres');
	$db->Connect("localhost","tester","test","test");
} else
	$db = NewADOConnection('oci8://scott:natsoft@/');
$arr = $db->ServerInfo();
echo "<h3>$db->dataProvider: {$arr['description']}</h3>";
$arr = $db->GetActiveRecords('products',' productid<10');
adodb_pr($arr);
ADOdb_Active_Record::SetDatabaseAdapter($db);
if  (!$db)  die('failed');
$rec = new ADODB_Active_Record('photos');
$rec = new ADODB_Active_Record('products');
adodb_pr($rec->getAttributeNames());
echo "<hr>";
$rec->load('productid=2');
adodb_pr($rec);
$db->debug=1;
$rec->productname = 'Changie Chan'.rand();
$rec->insert();
$rec->update();
$rec->productname = 'Changie Chan 99';
$rec->replace();
$rec2 = new ADODB_Active_Record('products');
$rec->load('productid=3');
$rec->save();
$rec = new ADODB_Active_record('products');
$rec->productname = 'John ActiveRec';
$rec->notes = 22;
#$rec->productid=0;
$rec->discontinued=1;
$rec->Save();
$rec->supplierid=33;
$rec->Save();
$rec->discontinued=0;
$rec->Save();
$rec->Delete();
echo "<p>Affected Rows after delete=".$db->Affected_Rows()."</p>";
