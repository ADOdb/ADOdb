<?php
/**
 * Tests cases for the mssqlnative driver of ADOdb.
 * Try to write database-agnostic tests where possible.
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
 * Class MetaFunctionsTest
 *
 * Test cases for for ADOdb MetaFunctions
 */
class MssqlnativeDriverTest extends TestCase
{
    protected ?object $db;
    protected ?string $adoDriver;
    protected ?object $dataDictionary;

    protected bool $skipFollowingTests = false;

    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setup(): void
    {

        $this->db        = &$GLOBALS['ADOdbConnection'];
        $this->adoDriver = $GLOBALS['ADOdriver'];

        if ($this->adoDriver !== 'mssqlnative') {
            $this->skipFollowingTests = true;
            $this->markTestSkipped(
                'This test is only applicable for the mssqlnative driver'
            );
        }
        
    }
    
    /**
     * Tear down the test environment
     *
     * @return void
     */
    public function tearDown(): void
    {
        
    }

    /**
     * Test the SQLDate function. Cloned from the original test_mssqlnative.php
     * 
     * @param string $dateFormat The date to test
     * @param string $field      The field to test
     * @param string $region     The region to test
     * @param string $result     The expected result
     * 
     * @dataProvider providerSqlDate
     * 
     * @return void
     */
    public function testSqlDate(string $dateFormat, string $field, string $region,string $result) :void {
        
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping testSqlDate as it is not applicable for the current driver');
        }

        $sql = "SELECT testdate, {$this->db->sqlDate($dateFormat,$field)} $region, null 
                  FROM (
                SELECT CONVERT(DATETIME,'2016-12-17 18:55:30.590' ,121) testdate,
                       CONVERT(DATETIME,'2016-01-01 18:55:30.590' ,121) testdatesmall,
                null nulldate
                ) q ";
        
        $res = $this->db->GetRow($sql);
       
        $this->assertEquals(
            $res['region'], 
            $result, 
            'SQL Date format for region ' . $region . ' should match expected format'
        );
    }   

    /**
     * Data provider for testSqlDate
     *
     * @return array
     */
    public function providerSQLDate() : array
    {
        return [
              
            ["d/m/Y", "testdate" ," FR4","17/12/2016"],
            ["d/m/y", "testdate" ," FR4b", "17/12/2016",],
            ["d/m/Y", "NULL", "nullFR4", null],
            ["m/d/Y", "testdate" , " US4", "12/17/2016"],
            ["m/d/y", "testdate" , " US4b", "12/17/2016"],
            ["m-d-Y", "testdate" , " USD4", "17-12-2016"],
            ["m-d-y", "testdate" , " USD4b", "17-12-2016"],
            ["Y.m.d", "testdate" , " ANSI4", "2016.12.17"],
            ["d.m.Y", "testdate" , " GE4", "17.12.2016"],
            ["d.m.y", "testdate" , " GE4b", "17.12.2016"],
            ["d-m-Y", "testdate" , " IT4", "17-12-2016"],
            ["d-m-y", "testdate" , " IT4b", "17-12-2016"],
            ["Y/m/d", "testdate" , " Japan4", "2016/12/17"],
            ["y/m/d", "testdate" , " Japan4b", "2016/12/17"],
            ["H:i:s", "testdate" ,  " timeonly","18:55:30"],
            ["d m Y",  "testdate" ," Space4","17 12 2016"],  // Is done by former method
            ["d m Y",  "NULL" ," nullSpace4","null"],
            ["m-d-Y","testdatesmall"," nowUSdash4","01-01-2016"]
        ];
    }
}