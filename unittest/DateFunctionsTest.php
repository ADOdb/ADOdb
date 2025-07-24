<?php
/**
 * Tests cases for date functions of ADODb
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
 * @copyright 2025 Damien Regad, Mark Newnham and the ADOdb community
 */

use PHPUnit\Framework\TestCase;

/**
 * Class DateFunctions.
 *
 * Test cases for ADOdb date functions
 */
class DateFunctionsTest extends TestCase
{

	protected $db;

	public function setUp(): void
	{
		$this->db = $GLOBALS['ADOdbConnection'];
		
	}
	
	
    /**
     * Test for {@see ADOConnection::dbDate())
     *
     */
	public function testDbDate(): void
	{
		$today = date('Y-m-d');
		
		$this->assertSame("'$today'", $this->db->dbDate($today));
	}

	/**
     * Test for {@see ADOConnection::bindDate())
     *
     */
	public function testBindDate(): void
	{
		$today = date('Y-m-d');
		
		$this->assertSame($today, $this->db->bindDate($today));
	}
	
	/**
     * Test for {@see ADOConnection::dbTimestamp())
     *
     */
	public function testDbTimestamp(): void
	{
		$now = date('Y-m-d H:i:s');
		
		$this->assertSame("'$now'", $this->db->dbTimestamp($now));
	}
	
	
	/**
     * Test for {@see ADOConnection::bindTimestamp())
     *
     */
	public function testBindTimestamp(): void
	{
		$now = date('Y-m-d H:i:s');
		
		$this->assertSame("$now", $this->db->bindTimestamp($now));
	}
	
	/**
     * Test for {@see ADOConnection::sysDate)
     *
     */
	public function testSysDate(): void
	{
		$today = date('Y-m-d');
		
		$this->assertSame("$today", $this->db->getOne("SELECT {$this->db->sysDate}"));
	}
	
	/**
     * Test for {@see ADOConnection::)sysTimeStamp
     *
     */
	public function testSysTimestamp(): void
	{
		$now = date('Y-m-d H:i:s');
		/**
     * Test for {@see ADOConnection::dbDate)
     *
     */
		$this->assertSame("$now", $this->db->getOne("SELECT {$this->db->sysTimeStamp}"));
	}

	/**
     * Test for {@see ADOConnection::fmtTimeStamp)
     *
     */	
	public function testFmtTimeStamp(): void
	{
		$today = date('Y-m-d H:i:s');
		
		$this->assertSame($today, $this->db->dbDate($today, $this->db->fmtTimeStamp));
	}
	
	/**
     * Test for {@see ADOConnection::year())
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:year
     */
	public function testYear(): void
	{
		/*
		* Retrieve a record with a known year
		*/
		$sql = "SELECT {$this->db->year('date_field')} FROM testtable_1 WHERE varchar_field='LINE 9'";

		$testResult 	= (string)$this->db->getOne($sql);
		$expectedResult = (string)date('Y', strtotime('1959-08-29'));
		
		$this->assertSame( $expectedResult, $testResult,'Test of year function');
	}
	
	/**
     * Test for {@see ADOConnection::month()
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:month
     *
     */
	public function testMonth(): void
	{
		/*
		* Retrieve a record with a known month
		*/
		$sql = "SELECT {$this->db->month('date_field')} FROM testtable_1 WHERE varchar_field='LINE 9'";

		$testResult 	= (string)$this->db->getOne($sql);
		$expectedResult = (string)date('m', strtotime('1959-08-29'));
		
		$this->assertSame( $expectedResult, $testResult,'Test of month function');
	}
	
	/**
     * Test for {@see ADOConnection::day())
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:day
     *
     */
	public function testDay(): void
	{
		
		/*
		* Set up a test record that has a NULL value
		*/
		$sql = "SELECT {$this->db->day('date_field')} FROM testtable_1 WHERE varchar_field='LINE 9'";

		$testResult 	= (string)$this->db->getOne($sql);
		$expectedResult = (string)date('d', strtotime('1959-08-29'));
		
		$this->assertSame($testResult, $expectedResult, 'Test of day function');
	}
	
	/**
     * Test for {@see ADOConnection::sqlDate())
     *
     */
	public function testSqlDate(): void
	{
		$today = date('m/d/Y');
		$fmt  = 'm/d/Y';
		
		$sql = "SELECT " . $this->db->sqlDate($fmt);

		$this->assertSame($today, $this->db->getOne($sql));
	}
	
	/**
     * Test for {@see ADOConnection::unixDate())
     *
     */
	public function testUnixDate(): void
	{
		$now = time();
		
		$sql = "SELECT " . $this->db->unixDate($now);
	
		$this->assertSame("$now", "{$this->db->getOne($sql)}");
	}
	
	/**
     * Test for {@see ADOConnection::unixTimestamp())
     *
     */
	public function testUnixTimestamp(): void
	{
		
		$now = time();
		$nowStamp = date('Y-m-d H:i:s',$now);
		
		$sql = "SELECT " . $this->db->unixTimestamp($nowStamp);
	
		$this->assertSame("$now",  "{$this->db->getOne($sql)}");
	}
	
	/**
     * Test for {@see ADOConnection::offsetDate())
     *
     */
	public function testOffsetDate(): void
	{
		
		$offset = 7;
		$nowStamp = date('Y-m-d', strtotime('today +7 days'));
		
		$sql = "SELECT " . $this->db->offsetDate($offset);
	
		$this->assertSame("$nowStamp", $this->db->getOne($sql));
	}

}