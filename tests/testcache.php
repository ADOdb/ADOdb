<html>
<body>
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

$ADODB_CACHE_DIR = dirname(tempnam('/tmp',''));
include("../adodb.inc.php");

if (isset($access)) {
	$db=ADONewConnection('access');
	$db->PConnect('nwind');
} else {
	$db = ADONewConnection('mysql');
	$db->PConnect('mangrove','root','','xphplens');
}
if (isset($cache)) $rs = $db->CacheExecute(120,'select * from products');
else $rs = $db->Execute('select * from products');

$arr = $rs->GetArray();
print sizeof($arr);
