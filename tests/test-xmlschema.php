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
include_once("../adodb.inc.php");
include_once("../adodb-xmlschema03.inc.php");

// To build the schema, start by creating a normal ADOdb connection:
$db = ADONewConnection('mysql');
$db->Connect('localhost', 'root', '', 'test') || die('fail connect1');

// To create a schema object and build the query array.
$schema = new adoSchema($db);

// To upgrade an existing schema object, use the following
// To upgrade an existing database to the provided schema,
// uncomment the following line:
#$schema->upgradeSchema();

print "<b>SQL to build xmlschema.xml</b>:\n<pre>";
// Build the SQL array
$sql = $schema->ParseSchema("xmlschema.xml");

var_dump($sql);
print "</pre>\n";

// Execute the SQL on the database
//$result = $schema->ExecuteSchema( $sql );

// Finally, clean up after the XML parser
// (PHP won't do this for you!)
//$schema->Destroy();



print "<b>SQL to build xmlschema-mssql.xml</b>:\n<pre>";

$db2 = ADONewConnection('mssql');
$db2->Connect('', 'adodb', 'natsoft', 'northwind') || die("Fail 2");

$db2->Execute("drop table simple_table");

$schema = new adoSchema($db2);
$sql = $schema->ParseSchema("xmlschema-mssql.xml");

print_r($sql);
print "</pre>\n";

$db2->debug = 1;

foreach ($sql as $s) {
    $db2->Execute($s);
}
