<?php

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
