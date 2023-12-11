<?php
/**
 * Tests cases for adodb-lib.inc.php
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

use PHPUnit\Framework\TestCase;

require_once dirname(__FILE__) . '/../adodb.inc.php';
require_once dirname(__FILE__) . '/../adodb-lib.inc.php';

/**
 * Class LibTest.
 *
 * Test cases for adodb-lib.inc.php
 */
class LibTest extends TestCase
{
	/** @var ADOConnection Database connection for tests */
	private $db;

	public function setUp(): void
	{
		$this->db = ADONewConnection('mysqli');
	}

	/**
	 * Test for {@see adodb_strip_order_by()}
	 *
	 * @dataProvider providerStripOrderBy
	 */
	public function testStripOrderBy($sql, $stripped): void
	{
		$this->assertSame($stripped, adodb_strip_order_by($sql));
	}

	/**
	 * Data provider for {@see testStripOrderBy()}
	 *
	 * @return array [SQL statement, SQL with ORDER BY clause stripped]
	 */
	public function providerStripOrderBy(): array
	{
		return [
			'No order by clause' => [
				"SELECT name FROM table",
				"SELECT name FROM table"
			],
			'Simple order by clause' => [
				"SELECT name FROM table ORDER BY name",
				"SELECT name FROM table"
			],
			'Order by clause descending' => [
				"SELECT name FROM table ORDER BY name DESC",
				"SELECT name FROM table"
			],
			'Order by clause with limit' => [
				"SELECT name FROM table ORDER BY name LIMIT 5",
				"SELECT name FROM table LIMIT 5"
			],
			'Ordered Subquery with outer order by' => [
				"SELECT * FROM table WHERE name IN (SELECT TOP 5 name FROM table_b ORDER by name) ORDER BY name DESC",
				"SELECT * FROM table WHERE name IN (SELECT TOP 5 name FROM table_b ORDER by name)"
			],
			'Ordered Subquery without outer order by' => [
				"SELECT * FROM table WHERE name IN (SELECT TOP 5 name FROM table_b ORDER by name)",
				"SELECT * FROM table WHERE name IN (SELECT TOP 5 name FROM table_b ORDER by name)"
			],
		];
	}

	/**
	 * Test for {@see _adodb_quote_fieldname()}
	 *
	 * @dataProvider quoteProvider
	 */
	public function testQuoteFieldNames($method, $field, $expected)
	{
		global $ADODB_QUOTE_FIELDNAMES;
		$ADODB_QUOTE_FIELDNAMES = $method;
		$this->assertSame($expected, _adodb_quote_fieldname($this->db, $field));
	}

	/**
	 * Data provider for {@see testQuoteFieldNames()}
	 * @return array
	 */
	public function quoteProvider()
	{
		return [
			'No quoting, single-word field name' => [false, 'Field', 'FIELD'],
			'No quoting, field name with space' => [false, 'Field Name', '`FIELD NAME`'],
			'Quoting `true`' => [true, 'Field', '`FIELD`'],
			'Quoting `UPPER`' => ['UPPER', 'Field', '`FIELD`'],
			'Quoting `LOWER`' => ['LOWER', 'Field', '`field`'],
			'Quoting `NATIVE`' => ['NATIVE', 'Field', '`Field`'],
			'Quoting `BRACKETS`' => ['BRACKETS', 'Field', '[FIELD]'],
			'Unknown value defaults to UPPER' => ['XXX', 'Field', '`FIELD`'],
		];
	}

}
