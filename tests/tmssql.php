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
ini_set('mssql.datetimeconvert', 0);

function tmssql()
{
    print "<h3>mssql</h3>";
    $db = mssql_connect('JAGUAR\vsdotnet', 'adodb', 'natsoft') or die('No Connection');
    mssql_select_db('northwind', $db);

    $rs = mssql_query('select getdate() as date', $db);
    $o = mssql_fetch_row($rs);
    print_r($o);
    mssql_free_result($rs);

    print "<p>Delete</p>";
    flush();
    $rs2 = mssql_query('delete from adoxyz', $db);
    $p = mssql_num_rows($rs2);
    mssql_free_result($rs2);
}

function tpear()
{
    include_once('DB.php');

    print "<h3>PEAR</h3>";
    $username = 'adodb';
    $password = 'natsoft';
    $hostname = 'JAGUAR\vsdotnet';
    $databasename = 'northwind';

    $dsn = "mssql://$username:$password@$hostname/$databasename";
    $conn = DB::connect($dsn);
    print "date=" . $conn->GetOne('select getdate()') . "<br>";
    @$conn->query('create table tester (id integer)');
    print "<p>Delete</p>";
    flush();
    $rs = $conn->query('delete from tester');
    print "date=" . $conn->GetOne('select getdate()') . "<br>";
}

function tadodb()
{
    include_once('../adodb.inc.php');

    print "<h3>ADOdb</h3>";
    $conn = NewADOConnection('mssql');
    $conn->Connect('JAGUAR\vsdotnet', 'adodb', 'natsoft', 'northwind');
//  $conn->debug=1;
    print "date=" . $conn->GetOne('select getdate()') . "<br>";
    $conn->Execute('create table tester (id integer)');
    print "<p>Delete</p>";
    flush();
    $rs = $conn->Execute('delete from tester');
    print "date=" . $conn->GetOne('select getdate()') . "<br>";
}


$ACCEPTIP = '127.0.0.1';

$remote = $_SERVER["REMOTE_ADDR"];

if (!empty($ACCEPTIP)) {
    if ($remote != '127.0.0.1' && $remote != $ACCEPTIP) {
        die("Unauthorised client: '$remote'");
    }
}

?>
<a href=tmssql.php?do=tmssql>mssql</a>
<a href=tmssql.php?do=tpear>pear</a>
<a href=tmssql.php?do=tadodb>adodb</a>
<?php
if (!empty($_GET['do'])) {
    $do = $_GET['do'];
    switch ($do) {
        case 'tpear':
        case 'tadodb':
        case 'tmssql':
            $do();
    }
}
