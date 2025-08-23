<?php
/**
 * Tests cases for DB Independent String functions of ADODb
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @package   ADOdb
 * @author    Mark Newnham <author@email.com>
 * @copyright 2025 Damien Regad, Mark Newnham and the ADOdb community
 * @license   BSD-3-Clause
 * @license   LGPL-2.1-or-later
 * @link      https://adodb.org Project's web site and documentation
 * @link      https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v2.1 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
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

    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setup(): void
    {
        $this->db        = &$GLOBALS['ADOdbConnection'];
        $this->adoDriver = $GLOBALS['ADOdriver'];
        
    }
    
    /**
     * Test for {@see ADODConnection::qstr()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:qstr
     *
     * @return void
     */
    public function testQstr(): void
    {
        /*
        * The expected result is db dependent, so we will
        * inser the string into the empty_field column
        * and see if it fails to insert or not.
        */
        $testString = "Famed author James O'Sullivan";

        /*
        * Blank out the empty_field column first to ensure that
        * the total number of rows updated is correct
        */
        $SQL = "UPDATE testtable_3 SET empty_field = null";
        
        $this->db->startTrans();
        $this->db->Execute($SQL);
        $this->db->CompleteTrans();
        $SQL = "UPDATE testtable_3 SET empty_field = {$this->db->qstr($testString)}";
        
        $this->db->startTrans();
        $this->db->Execute($SQL);
        $this->db->CompleteTrans();

        $expectedValue = 11;
        $actualValue = $this->db->Affected_Rows();

        // We should have updated 11 rows
        $this->assertSame(
            $expectedValue,
            $actualValue, 
            'All rows should have been updated with the test string'
        );

        // Now we will check the value in the empty_field column
        $sql = "SELECT empty_field FROM testtable_3";

        $this->db->startTrans();
        $returnValue = $this->db->getOne($sql);
        $this->db->CompleteTrans();
        
        $testResult = preg_match('/^(Famed author James O)[\\\'](\'Sullivan)$/', $returnValue);
                
        $this->assertSame(
            true, 
            $testResult, 
            'Qstr should have returned a string with the apostrophe escaped'
        );
            
    }
    
    /**
     * Test for {@see ADODConnection::addq()}
     * 
     * @link   https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:addq
     * @return void
     */
    public function testAddq(): void
    {
        
        /*
        * The expected result is db dependent, so we will
        * insert the string into the empty_field column
        * and see if it fails to insert or not.
        */
        $testString = "Famed author James O'Sullivan";
        $p1 = $this->db->param('p1');
        $bind = array(
            'p1' => $this->db->addQ($testString)
        );

        $SQL = "UPDATE testtable_3 SET empty_field = $p1";
        
        $this->db->Execute($SQL, $bind);

        // We should have updated 11 rows
        $this->assertSame(
            11, 
            $this->db->Affected_Rows(), 
            'All rows should have been updated with the test string'
        );

        // Now we will check the value in the empty_field column
        $sql = "SELECT empty_field FROM testtable_1";

        $returnValue = $this->db->getOne($sql);

        // Now we will check the value in the empty_field column
        $sql = "SELECT empty_field FROM testtable_3";

        $returnValue = $this->db->getOne($sql);

        $testResult = preg_match('/^(Famed author James O)[\\\'](\'Sullivan)$/', $returnValue);
                
        $this->assertSame(
            true, 
            $testResult, 
            'addQ should have returned a string with the apostrophe escaped'
        );
            
    }
    
    /**
     * Test for {@see ADODConnection::concat()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:concat
     * 
     * @dataProvider providerTestConcat
     * 
     * @return void
     */
    public function testConcat(int $fetchMode, string $firstColumn, string $secondColumn): void
    {
        
        /*
        * Find a record that has a varchar_field value
        */

        $this->db->setFetchMode($fetchMode);

        $sql = "SELECT number_run_field, varchar_field 
                  FROM testtable_1 
                 WHERE varchar_field IS NOT NULL";


        $row = $this->db->getRow($sql);

        $expectedValue = sprintf(
            '%s|%s',
            $row[$secondColumn],
            $row[$secondColumn]
        );

        $field = $this->db->Concat('varchar_field', "'|'", 'varchar_field');
        
        $sql = "SELECT $field 
                  FROM testtable_1 
                 WHERE number_run_field={$row[$firstColumn]}";


        $result = $this->db->getOne($sql);

        $this->assertSame(
            $expectedValue,
            $result,
            sprintf('3 value concat should return %s', $expectedValue)
        );

        $this->db->setFetchMode(ADODB_FETCH_ASSOC);
            
    }

    /**
     * Data provider for {@see testConcat()}
     *
     * @return array [int $fetchmode, string $number_run_column, string $varchar_column]
     */
    public function providerTestConcat(): array
    {
        
        switch (ADODB_ASSOC_CASE) {
            case ADODB_ASSOC_CASE_UPPER:
            
            return [
                'FETCH_ASSOC,ASSOC_CASE_UPPER' => 
                array(
                    ADODB_FETCH_ASSOC, 
                    'NUMBER_RUN_FIELD',
                    'VARCHAR_FIELD',
                ),
                'FETCH_NUM,ASSOC_CASE_UPPER' =>
                array(
                    0=>ADODB_FETCH_NUM, 
                    1=>"0",
                    2=>"1"
                
                )
            ];
            break;
            
            case ADODB_ASSOC_CASE_LOWER:
            default:
            
            return [
                'FETCH_ASSOC,ASSOC_CASE_LOWER' => [
                    ADODB_FETCH_ASSOC, 
                    'number_run_field',
                    'varchar_field',
                ],
                'FETCH_NUM,ASSOC_CASE_UPPER' => [
                    0=>ADODB_FETCH_NUM, 
                    1=>"0",
                    2=>"1"
                ]
            ];
 
            break;

        }
       
    }

    /**
     * Test for {@see ADODConnection::ifNull()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:ifnull
     * 
     * @dataProvider providerTestIfnull
     * 
     * @return void
     */
    public function testIfNull(int $fetchMode, string $firstColumn, string $secondColumn): void
    {

        

        $this->db->setFetchMode($fetchMode);

        $sql = "SELECT number_run_field, decimal_field 
                  FROM testtable_1 
                 WHERE date_field IS NOT NULL";

        $row = $this->db->getRow($sql);
       
        /*
        * Set up a test record that has a NULL value
        */
        $sql = "UPDATE testtable_1 
                   SET decimal_field = null 
                 WHERE number_run_field={$row[$firstColumn]}";

       
        $this->db->startTrans();
        $this->db->Execute($sql);
        $this->db->CompleteTrans();
        /*
        * Now get a weird value back from the ifnull function
        */
       
        $sql = "SELECT {$this->db->ifNull('decimal_field', 8675304)} 
                  FROM testtable_1 
                 WHERE number_run_field={$row[$firstColumn]}";

        $expectedResult = (float)$this->db->getOne($sql);
        
        $this->assertEquals(
            8675304,
            $expectedResult,
            'Test of ifnull function  should return 8675304'
        );

        /*
        * Reset the date_field to a non-null value
        */
        $sql = "UPDATE testtable_1 
                   SET decimal_field = {$row[$secondColumn]} 
                 WHERE number_run_field={$row[$firstColumn]}";

        $this->db->startTrans();
        $this->db->Execute($sql);
        $this->db->CompleteTrans();
      
         $this->db->setFetchMode(ADODB_FETCH_ASSOC);
            
    }

    /**
     * Data provider for {@see testIfnull()}
     *
     * @return array [int fetchmode, string number_run column, string date column]
     */
    public function providerTestIfnull(): array
    {
        
        
        switch (ADODB_ASSOC_CASE) {
            case ADODB_ASSOC_CASE_UPPER:
            return [
                'FETCH_ASSOC,ASSOC_CASE_UPPER' => [
                    ADODB_FETCH_ASSOC, 
                    'NUMBER_RUN_FIELD',
                    'DECIMAL_FIELD',
                ],
                'FETCH_NUM,ASSOC_CASE_UPPER' => [
                    0=>ADODB_FETCH_NUM, 
                    1=>"0",
                    2=>"1"
    
                ]
            ];
            break;
            case ADODB_ASSOC_CASE_LOWER:
            default:
            return [
                'FETCH_ASSOC,ASSOC_CASE_LOWER' => [
                    ADODB_FETCH_ASSOC, 
                    'number_run_field',
                    'decimal_field',
                ],
                'FETCH_NUM,ASSOC_CASE_UPPER' => [
                    0=>ADODB_FETCH_NUM, 
                    1=>"0",
                    2=>"1"

                ]
            ];
            break;

        }
       
    }

}