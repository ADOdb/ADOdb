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

include_once('../adodb-perf.inc.php');

error_reporting(E_ALL);
session_start();

if (isset($_GET)) {
    foreach ($_GET as $k => $v) {
        if (strncmp($k, 'test', 4) == 0) {
            $_SESSION['_db'] = $k;
        }
    }
}

if (isset($_SESSION['_db'])) {
    $_db = $_SESSION['_db'];
    $_GET[$_db] = 1;
    $$_db = 1;
}

echo "<h1>Performance Monitoring</h1>";
include_once('testdatabases.inc.php');


function testdb($db)
{
    if (!$db) {
        return;
    }
    echo "<font size=1>";
    print_r($db->ServerInfo());
    echo " user=" . $db->user . "</font>";

    $perf = NewPerfMonitor($db);

    # unit tests
    if (0) {
        //$DB->debug=1;
        echo "Data Cache Size=" . $perf->DBParameter('data cache size') . '<p>';
        echo $perf->HealthCheck();
        echo($perf->SuspiciousSQL());
        echo($perf->ExpensiveSQL());
        echo($perf->InvalidSQL());
        echo $perf->Tables();

        echo "<pre>";
        echo $perf->HealthCheckCLI();
        $perf->Poll(3);
        die();
    }

    if ($perf) {
        $perf->UI(3);
    }
}
