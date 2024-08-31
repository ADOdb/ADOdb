<?php
/**
 * ADOdb tests - Dictionary Column Attributes parser.
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
 * @copyright 2024 Damien Regad, Mark Newnham and the ADOdb community
 */

require_once '../adodb.inc.php';
require_once '../adodb-datadict.inc.php';

testParseArgs();


/**
 * Test script for Dictionary Column Attributes parser.
 *
 * This test function used to be called lens_ParseTest() and was moved from
 * adodb-datadict.inc.php and adapted to call the parser after it was moved
 * from a standalone function into a private method of ADODB_DataDict class.
 */
function testParseArgs()
{
	$str = <<<EOS
		`zcol ACOL` NUMBER(32,2) DEFAULT 'The \"cow\" (and Jim''s dog) jumps over the moon' PRIMARY,
		INTI INT AUTO DEFAULT 0,
		zcol2\"afs ds";
		EOS;

	$dd = new ADODB_DataDict();

	// Trick to call private method
	$class = new ReflectionClass($dd);
	$fnArgParse = $class->getMethod('parseArgs');
	if (version_compare(PHP_VERSION, '8.1.0', '<')) {
		$fnArgParse->setAccessible(true);
	}
	$result = $fnArgParse->invoke($dd, $str);

	print "<pre>\n";
	print $str . "\n\n";
	print_r($result);
	print "</pre>\n";
}
