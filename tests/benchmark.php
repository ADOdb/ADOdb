<html>
<head>
    <title>ADODB Benchmarks</title>
</head>

<body>
<?php

/**
 * Benchmarking
 *
 * Benchmark code to test the speed to the ADODB library with different databases.
 * This is a simplistic benchmark to be used as the basis for further testing.
 * It should not be used as proof of the superiority of one database over the other.
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

$testmssql = true;
//$testvfp = true;
$testoracle = true;
$testado = true;
$testibase = true;
$testaccess = true;
$testmysql = true;
$testsqlite = true;
;

set_time_limit(240); // increase timeout

include("../tohtml.inc.php");
include("../adodb.inc.php");

function testdb(&$db, $createtab = "create table ADOXYZ (id int, firstname char(24), lastname char(24), created date)")
{
    global $ADODB_version,$ADODB_FETCH_MODE;

    adodb_backtrace();

    $max = 100;
    $sql = 'select * from ADOXYZ';
    $ADODB_FETCH_MODE = ADODB_FETCH_NUM;

    //print "<h3>ADODB Version: $ADODB_version Host: <i>$db->host</i> &nbsp; Database: <i>$db->database</i></h3>";

    // perform query once to cache results so we are only testing throughput
    $rs = $db->Execute($sql);
    if (!$rs) {
        print "Error in recordset<p>";
        return;
    }
    $arr = $rs->GetArray();
    //$db->debug = true;
    global $ADODB_COUNTRECS;
    $ADODB_COUNTRECS = false;
    $start = microtime();
    for ($i = 0; $i < $max; $i++) {
        $rs = $db->Execute($sql);
        $arr = $rs->GetArray();
       //        print $arr[0][1];
    }
    $end =  microtime();
    $start = explode(' ', $start);
    $end = explode(' ', $end);

    //print_r($start);
    //print_r($end);

      //  print_r($arr);
    $total = $end[0] + trim($end[1]) - $start[0] - trim($start[1]);
    printf("<p>seconds = %8.2f for %d iterations each with %d records</p>", $total, $max, sizeof($arr));
    flush();


        //$db->Close();
}
include("testdatabases.inc.php");

?>


</body>
</html>
