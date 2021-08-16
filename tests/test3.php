<?php
/**
 * * ADOdb tests.
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

$path = dirname(__FILE__);

include("$path/../adodb-exceptions.inc.php");
include("$path/../adodb.inc.php");

try {
$db = NewADOConnection("oci8");
$db->Connect('','scott','natsoft');
$db->debug=1;

$cnt = $db->GetOne("select count(*) from adoxyz");
$rs = $db->Execute("select * from adoxyz order by id");

$i = 0;
foreach($rs as $k => $v) {
	$i += 1;
	echo $k; adodb_pr($v);
	flush();
}

if ($i != $cnt) die("actual cnt is $i, cnt should be $cnt\n");



$rs = $db->Execute("select bad from badder");

} catch (exception $e) {
	adodb_pr($e);
	$e = adodb_backtrace($e->trace);
}
