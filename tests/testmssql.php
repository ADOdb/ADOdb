<?php

/**
 * Test GetUpdateSQL and GetInsertSQL.
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
include('../tohtml.inc.php');

//==========================
// This code tests an insert



$conn = ADONewConnection("mssql");  // create a connection
$conn->Connect('127.0.0.1', 'adodb', 'natsoft', 'northwind') or die('Fail');

$conn->debug = 1;
$query = 'select * from products';
$conn->SetFetchMode(ADODB_FETCH_ASSOC);
$rs = $conn->Execute($query);
echo "<pre>";
while (!$rs->EOF) {
    $output[] = $rs->fields;
    var_dump($rs->fields);
    $rs->MoveNext();
    print "<p>";
}
die();


$p = $conn->Prepare('insert into products (productname,unitprice,dcreated) values (?,?,?)');
echo "<pre>";
print_r($p);

$conn->debug = 1;
$conn->Execute($p, array('John' . rand(),33.3,$conn->DBDate(time())));

$p = $conn->Prepare('select * from products where productname like ?');
$arr = $conn->getarray($p, array('V%'));
print_r($arr);
die();

//$conn = ADONewConnection("mssql");
//$conn->Connect('mangrove','sa','natsoft','ai');

//$conn->Connect('mangrove','sa','natsoft','ai');
$conn->debug = 1;
$conn->Execute('delete from blobtest');

$conn->Execute('insert into blobtest (id) values(1)');
$conn->UpdateBlobFile('blobtest', 'b1', '../cute_icons_for_site/adodb.gif', 'id=1');
$rs = $conn->Execute('select b1 from blobtest where id=1');

$output = "c:\\temp\\test_out-" . date('H-i-s') . ".gif";
print "Saving file <b>$output</b>, size=" . strlen($rs->fields[0]) . "<p>";
$fd = fopen($output, "wb");
fwrite($fd, $rs->fields[0]);
fclose($fd);

print " <a href=file://$output>View Image</a>";
//$rs = $conn->Execute('SELECT id,SUBSTRING(b1, 1, 10) FROM blobtest');
//rs2html($rs);
