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

    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->db = $GLOBALS['ADOdbConnection'];
        
    }
    
    /**
     * Test for {@see ADOConnection::dbDate())
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:dbdate
     * 
     * @return void
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
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:binddate
     * 
     * @return void
     */
    public function testBindDate(): void
    {
        $today = date('Y-m-d');
        
        $this->assertSame($today, $this->db->bindDate($today));
    }
    
    /**
     * Test for {@see ADOConnection::dbTimestamp())
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:dbtimestamp
     * 
     * @return void
     *
     */
    public function testDbTimestamp(): void
    {
        $now = date('Y-m-d H:i:s');
        
        $this->assertSame(
            "'$now'", 
            $this->db->dbTimestamp($now), 
            'dbTimestamp should return a quoted timestamp'
        );
    }
    
    /**
     * Test for {@see ADOConnection::bindTimestamp())
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:bindtimestamp
     * 
     * @return void
     */
    public function testBindTimestamp(): void
    {
        $now = date('Y-m-d H:i:s');
        
        $this->assertSame(
            "$now", 
            $this->db->bindTimestamp($now), 
            'bindTimestamp should return a timestamp without quotes'
        );
    }
    
    /**
     * Test for {@see ADOConnection::sysDate)
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:sysdate
     *
     * @return void
     */
    public function testSysDate(): void
    {

        $today = date('Y-m-d');
        
        $sql = "SELECT {$this->db->sysDate}";

        $this->assertSame(
            "$today", 
            $this->db->getOne($sql),
            'sysDate should return today\'s date based on the server\'s timezone'
        );
    }
    
    /**
     * Test for {@see ADOConnection::)sysTimeStamp
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:systimestamp
     * 
     * @return void
     */
    public function testSysTimestamp(): void
    {
        $now = date('Y-m-d H:i:s');
        
        $sysnow = $this->db->getOne("SELECT {$this->db->sysTimeStamp}");

        $this->assertSame(
            $now, 
            $sysnow, 
            'sysTimeStamp should return the current timestamp based on the server\'s timezone'
        );
    }

    /**
     * Test for {@see ADOConnection::fmtTimeStamp)
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:fmttimestamp
     * 
     * @return void
     */
    public function testFmtTimeStamp(): void
    {
       
       
        $today          = time();
        $expectedResult = date('Y-m-d H:i:s');
        
        $testResult = $this->db->dbDate(
            $today, 
            $this->db->fmtTimeStamp
        );

        $this->assertSame(
            $expectedResult,
            $testResult,
            'fmtTimeStamp should return a timestamp in the format set in the fmtTimeStamp property'
        );
    }
    
    /**
     * Test for {@see ADOConnection::year())
     *  
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:year
     *
     * @return void
     */
    public function testYear(): void
    {
        /*
        * Retrieve a record with a known year
        */
        $sql = "SELECT {$this->db->year('date_field')} FROM testtable_1 WHERE varchar_field='LINE 9'";

        $testResult     = (string)$this->db->getOne($sql);
        $expectedResult = (string)date('Y', strtotime('1959-08-29'));
        
        $this->assertSame( 
            $expectedResult, 
            $testResult,
            'Test of year function'
        );
    }
    
    /**
     * Test for {@see ADOConnection::month()
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:month
     *
     * @return void
     */
    public function testMonth(): void
    {
        /*
        * Retrieve a record with a known month
        */
        $sql = "SELECT {$this->db->month('date_field')}
                  FROM testtable_1 
                 WHERE varchar_field='LINE 9'";

        $testResult     = (string)$this->db->getOne($sql);
        $expectedResult = (string)date('m', strtotime('1959-08-29'));
        
        $this->assertSame(
            $expectedResult,
            $testResult,
            'Test of month function'
        );
    }
    
    /**
     * Test for {@see ADOConnection::day())
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:day
     *
     * @return void
     */
    public function testDay(): void
    {
        
        /*
        * Set up a test record that has a NULL value
        */
        $sql = "SELECT {$this->db->day('date_field')} 
                  FROM testtable_1 
                 WHERE varchar_field='LINE 9'";

        $testResult 	= (string)$this->db->getOne($sql);
        $expectedResult = (string)date('d', strtotime('1959-08-29'));
        
        $this->assertSame(
            $testResult, 
            $expectedResult, 
            'Test of day function'
        );
    }
    
    /**
     * Test for {@see ADOConnection::sqlDate())
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:sqldate
     * 
     * @return void
     */
    public function testSqlDate(): void
    {
        $today = date('m/d/Y');
        $fmt   = 'm/d/Y';
        
        $sql = "SELECT " . $this->db->sqlDate($fmt);

        $this->assertSame(
            $today, 
            $this->db->getOne($sql), 
            'sqlDate should return the date in the format set in the first parameter'
        );
        
        $fmt = 'd/m/Y';

        $sql = sprint(
            "SELECT %s 
               FROM testtable_1 
              WHERE varchar_field='LINE 9'", 
            $this->db->sqlDate($fmt, 'date_field')
        );

        $testResult = '29/08/1959';

        $this->assertSame(
            $testResult, 
            $this->db->getOne($sql), 
            'sqlDate should return the date in the format set in the first parameter based on the date_field column'
        );

        
    }
    
    /**
     * Test for {@see ADOConnection::unixDate())
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:unixdate
     *
     * @return void
     */
    public function testUnixDate(): void
    {
        $now = time();
        
        $sql = "SELECT " . $this->db->unixDate($now);
    
        $this->assertSame(
            "$now",
            "{$this->db->getOne($sql)}"
        );
    }
    
    /**
     * Test for {@see ADOConnection::unixTimestamp())
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:unixtimestamp
     * 
     * @return void
     */
    public function testUnixTimestamp(): void
    {
        
        $now      = time();
        $nowStamp = date('Y-m-d H:i:s', $now);
        
        $sql = sprintf('SELECT %s', $this->db->unixTimestamp($nowStamp));
        $this->assertSame(
            "$now",
            "{$this->db->getOne($sql)}"
        );
    }
    
    /**
     * Test for {@see ADOConnection::offsetDate())
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:offsetdate
     * 
     * @return void
     */
    public function testOffsetDate(): void
    {
        
        $offset = 7;
        $nowStamp = date('Y-m-d', strtotime('today +7 days'));
        
        $sql = "SELECT " . $this->db->offsetDate($offset);
    
        $this->assertSame(
            "$nowStamp", 
            $this->db->getOne($sql), 
            'Offset date should return the date 1 week in the future'
        );
    
        $offset = -7;
        $nowStamp = date('Y-m-d', strtotime('today -7 days'));
        
        $sql = "SELECT " . $this->db->offsetDate($offset);
    
        $this->assertSame(
            "$nowStamp", 
            $this->db->getOne($sql), 
            'Offset date should return the date 1 week in the past'
        );
    }

}