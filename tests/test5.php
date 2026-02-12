<?php

/**
 * * ADOdb tests - Select an empty record from the database.
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

include('../adodb.inc.php');
include('../tohtml.inc.php');

include('../adodb-errorpear.inc.php');

if (0) {
    $conn = ADONewConnection('mysql');
    $conn->debug = 1;
    $conn->PConnect("localhost", "root", "", "xphplens");
    print $conn->databaseType . ':' . $conn->GenID() . '<br>';
}

if (0) {
    $conn = ADONewConnection("oci8");  // create a connection
    $conn->debug = 1;
    $conn->PConnect("falcon", "scott", "tiger", "juris8.ecosystem.natsoft.com.my"); // connect to MySQL, testdb
    print $conn->databaseType . ':' . $conn->GenID();
}

if (0) {
    $conn = ADONewConnection("ibase");  // create a connection
    $conn->debug = 1;
    $conn->Connect("localhost:c:\\Interbase\\Examples\\Database\\employee.gdb", "sysdba", "masterkey", ""); // connect to MySQL, testdb
    print $conn->databaseType . ':' . $conn->GenID() . '<br>';
}

if (0) {
    $conn = ADONewConnection('postgres');
    $conn->debug = 1;
    @$conn->PConnect("susetikus", "tester", "test", "test");
    print $conn->databaseType . ':' . $conn->GenID() . '<br>';
}
