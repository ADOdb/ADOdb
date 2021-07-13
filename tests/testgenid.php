<?php
/**
 * ADOdb tests - ID generation.
 *
 * Run multiple copies of this php script at the same time
 * to test unique generation of id's in multiuser mode
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

include_once('../adodb.inc.php');
$testaccess = true;
include_once('testdatabases.inc.php');

function testdb(&$db,$createtab="create table ADOXYZ (id int, firstname char(24), lastname char(24), created date)")
{
	$table = 'adodbseq';

	$db->Execute("drop table $table");
	//$db->debug=true;

	$ctr = 5000;
	$lastnum = 0;

	while (--$ctr >= 0) {
		$num = $db->GenID($table);
		if ($num === false) {
			print "GenID returned false";
			break;
		}
		if ($lastnum + 1 == $num) print " $num ";
		else {
			print " <font color=red>$num</font> ";
			flush();
		}
		$lastnum = $num;
	}
}
