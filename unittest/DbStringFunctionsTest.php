<?php
/**
 * Tests cases for DB Independent String
 functions of ADODb
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @authot Mark Newnham
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
 * @copyright 2025 Damien Regad, Mark Newnham and the ADOdb community
 */

use PHPUnit\Framework\TestCase;

/**
 * Class MetaFunctionsTest
 *
 * Test cases for for ADOdb MetaFunctions
 */
class DbStringFunctionsTest extends TestCase
{
	protected $db;
	protected $adoDriver;

	public function setUp(): void
	{
		$this->db        = $GLOBALS['ADOdbConnection'];
		$this->adoDriver = $GLOBALS['ADOdriver'];
		
	}
	
	public function tearDown(): void
	{
		
	}
	
	
	/**
     * Test for {@see ADODConnection::qstr()]
     *
	*/
	public function testQstr(): void
	{
		/*
		* The expected result is db dependent
		*/
		
		$resultsArray = array(
			'mysqli'=>"'Famed author James O\\'Sullivan'",
			'sqlite3'=>"'Famed author James O''Sullivan'",
			'pdo-mysql'=>"'Famed author James O\\'Sullivan'",
			'pdo-sqlite'=>"'Famed author James O''Sullivan'",
			'postgres9'=>"'Famed author James O''Sullivan'",
			'pdo-pgsql'=>"'Famed author James O''Sullivan'",
			'mssqlnative'=>"'Famed author James O''Sullivan'"
		);
		
		
		
		$result = $this->db->qstr("Famed author James O'Sullivan");
		
				
		$this->assertSame($resultsArray[$this->adoDriver], $result, 'Test Of qStr');
			
	}
	
	/**
     * Test for {@see ADODConnection::addq()]
     *
	*/
	public function testAddq(): void
	{
		/*
		* The expected result is db dependent
		*/
		
		$resultsArray = array(
			'mysqli'=>"Famed author James O\\'Sullivan",
			'sqlite3'=>"Famed author James O''Sullivan",
			'pdo-mysql'=>"Famed author James O\\'Sullivan",
			'pdo-sqlite'=>"Famed author James O''Sullivan",
			'pdo-sqlite'=>"Famed author James O''Sullivan",
			'postgres9'=>"Famed author James O\\'Sullivan",
			'pdo-pgsql'=>"Famed author James O\\'Sullivan",
			'mssqlnative'=>"Famed author James O\\'Sullivan"
		);
		
		
		
		$result = $this->db->addq("Famed author James O'Sullivan");
		
				
		$this->assertSame($resultsArray[$this->adoDriver], $result, 'Test Of qStr');
			
	}
	
	/**
     * Test for {@see ADODConnection::concat()]
     *
	*/
	public function testConcat(): void
	{
		$expectedValue = 'LINE 1|LINE 1';
		
		$field = $this->db->Concat('varchar_field',"'|'",'varchar_field');
		
		$sql = "SELECT $field FROM testtable_1 WHERE varchar_field='LINE 1'";
		$result = $this->db->getOne($sql);
		
		$this->assertSame($expectedValue, $result, '3 value concat');
			
	}

	/**
     * Test for {@see ADODConnection::concat()]
     *
	*/
	public function testIfNull(): void
	{

		/*
		* Set up a test record that has a NULL value
		*/
		$sql = "UPDATE testtable_1 SET date_field = null WHERE varchar_field='LINE 1'";

		$this->db->Execute($sql);

		/*
		* Now get a wierd value back from the ifnull function
		*/
		$sql = "SELECT IFNULL(date_field,'1970-01-01') FROM testtable_1 WHERE varchar_field='LINE 1'";	
		$result = $this->db->getOne($sql);		
		$this->assertSame('1970-01-01', $result,'Test of ifnull function');
			
	}

}