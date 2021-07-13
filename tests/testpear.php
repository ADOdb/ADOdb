<?php
/**
 * ADOdb tests - PEAR DB.
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

include_once('../adodb-pear.inc.php');
$username = 'root';
$password = '';
$hostname = 'localhost';
$databasename = 'xphplens';
$driver = 'mysql';

$dsn = "$driver://$username:$password@$hostname/$databasename";

$db = DB::Connect($dsn);
$db->setFetchMode(ADODB_FETCH_ASSOC);
$rs = $db->Query('select firstname,lastname from adoxyz');
$cnt = 0;
while ($arr = $rs->FetchRow()) {
	print_r($arr);
	print "<br>";
	$cnt += 1;
}

if ($cnt != 50) print "<b>Error in \$cnt = $cnt</b>";
