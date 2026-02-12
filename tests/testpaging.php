<?php

/**
 * ADOdb tests - paging.
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


include_once('../adodb.inc.php');
include_once('../adodb-pager.inc.php');

$driver = 'oci8';
$sql = 'select  ID, firstname as "First Name", lastname as "Last Name" from adoxyz  order  by  id';
//$sql = 'select count(*),firstname from adoxyz group by firstname order by 2 ';
//$sql = 'select distinct firstname, lastname from adoxyz  order  by  firstname';

if ($driver == 'postgres') {
    $db = NewADOConnection('postgres');
    $db->PConnect('localhost', 'tester', 'test', 'test');
}

if ($driver == 'access') {
    $db = NewADOConnection('access');
    $db->PConnect("nwind", "", "", "");
}

if ($driver == 'ibase') {
    $db = NewADOConnection('ibase');
    $db->PConnect("localhost:e:\\firebird\\examples\\employee.gdb", "sysdba", "masterkey", "");
    $sql = 'select distinct firstname, lastname  from adoxyz  order  by  firstname';
}
if ($driver == 'mssql') {
    $db = NewADOConnection('mssql');
    $db->Connect('JAGUAR\vsdotnet', 'adodb', 'natsoft', 'northwind');
}
if ($driver == 'oci8') {
    $db = NewADOConnection('oci8');
    $db->Connect('', 'scott', 'natsoft');

    $sql = "select * from (select  ID, firstname as \"First Name\", lastname as \"Last Name\" from adoxyz
	 order  by  1)";
}

if ($driver == 'access') {
    $db = NewADOConnection('access');
    $db->Connect('nwind');
}

if (empty($driver) or $driver == 'mysql') {
    $db = NewADOConnection('mysql');
    $db->Connect('localhost', 'root', '', 'test');
}

//$db->pageExecuteCountRows = false;

$db->debug = true;

if (0) {
    $rs = $db->Execute($sql);
    include_once('../toexport.inc.php');
    print "<pre>";
    print rs2csv($rs); # return a string

    print '<hr />';
    $rs->MoveFirst(); # note, some databases do not support MoveFirst
    print rs2tab($rs); # return a string

    print '<hr />';
    $rs->MoveFirst();
    rs2tabout($rs); # send to stdout directly
    print "</pre>";
}

$pager = new ADODB_Pager($db, $sql);
$pager->showPageLinks = true;
$pager->linksPerPage = 10;
$pager->cache = 60;
$pager->Render($rows = 7);
