<?php

/**
 * ADOdb tests.
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @package ADOdb
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v2.1 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-2.1-or-later
 *
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
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
} elseif ($DBMS == 'postgres') {
    $db = NewADOConnection('postgres');
    $db->Connect("localhost", "tester", "test", "test");
} else {
    $db = NewADOConnection('oci8://scott:natsoft@/');
}


$arr = $db->ServerInfo();
echo "<h3>$db->dataProvider: {$arr['description']}</h3>";

$arr = $db->GetActiveRecords('products', ' productid<10');
adodb_pr($arr);

ADOdb_Active_Record::SetDatabaseAdapter($db);
if (!$db) {
    die('failed');
}




$rec = new ADODB_Active_Record('photos');

$rec = new ADODB_Active_Record('products');


adodb_pr($rec->getAttributeNames());

echo "<hr>";


$rec->load('productid=2');
adodb_pr($rec);

$db->debug = 1;


$rec->productname = 'Changie Chan' . rand();

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
$rec->discontinued = 1;
$rec->Save();
$rec->supplierid = 33;
$rec->Save();
$rec->discontinued = 0;
$rec->Save();
$rec->Delete();

echo "<p>Affected Rows after delete=" . $db->Affected_Rows() . "</p>";
