<?php
/**
 * Tests cases for variables and constants of ADODb
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
class VariablesTest extends TestCase
{
    protected ?object $db;
    protected ?string $adoDriver;
    protected ?object $dataDictionary;

    protected bool $skipFollowingTests = false;

    protected string $testTableName = 'table_name';
    protected string $testIdColumnName = 'ID';

    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setup(): void
    {

        $this->db        = &$GLOBALS['ADOdbConnection'];
        $this->adoDriver = $GLOBALS['ADOdriver'];

        static $testTableName = false;

        if ($testTableName) {
            $this->testTableName = $testTableName;
            return;
        }

    
    }
    
    /**
     * Tear down the test environment
     *
     * @return void
     */
    public function tearDown(): void
    {
        //$this->db->execute("DROP TABLE IF EXISTS {$this->testTableName}");
    }

    /**
     * Changes the casing of the keys in an associative array
     * based on the value of ADODB_ASSOC_CASE
     *
     * @param array $input by reference
     * 
     * @return void
     */
    protected function changeKeyCasing(array &$input) : void
    {
        if (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_UPPER) {
            $input = array_change_key_case($input, CASE_UPPER);
        } elseif (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_LOWER) {
            $input = array_change_key_case($input, CASE_LOWER);
        } elseif (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_NATURAL) {
            // No change needed
        } else {
            throw new InvalidArgumentException('Invalid ADODB_ASSOC_CASE value');
        }   

    }

    /**
     * Test for {@see $ADODB_QUOTE_FIELDNAMES}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:adodb_quote_fieldnames
     * 
     * @return void
     */
    public function testQuotingExecute(): void
    {
       
        global $ADODB_QUOTE_FIELDNAMES;

        $ADODB_QUOTE_FIELDNAMES = false;
        /*
        * Fetch a template row from the table
        */

        $quotedTable = sprintf(
            '%s%s%s', 
            $this->db->nameQuote, 
            $this->testTableName,
            $this->db->nameQuote
        );

        
        $sql = "SELECT * FROM $quotedTable WHERE {$this->testIdColumnName}=-1";

        $template = $this->db->execute($sql);
       
        $ar = array(
            'column_name' => 'Sample data'
        );

        $sql = $this->db->getInsertSQL(
            $template,
            $ar
        );
        
        $response = $this->db->execute($sql);

        $success = is_object($response);

        $this->assertSame(
            false,
            $success, 
            'Data insertion should not succeed using Unquoted field and table names'
        );

        $count = $this->db->getOne("SELECT COUNT(*) FROM $quotedTable");

        $this->assertEquals(
            0, 
            $count, 
            'Data insertion should not have succeeded using Unquoted field and table names'
        );
        
        /*
        * Now activate the quoting of field and table names
        */
        $ADODB_QUOTE_FIELDNAMES = true;

        $sql = $this->db->getInsertSQL(
            $template,
            $ar
        );
        
        $success = $this->db->execute($sql);

        $this->assertSame(
            true,
            $success, 
            'Data insertion failed Using Quoted field and table names. The failing SQL was: ' . $sql
        );
    }

    /**
     * Test for {@see $ADODB_FETCH_MODE}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:adodb_fetch_mode
     * 
     * @return void
     */
    public function testFetchMode(): void
    {
        global $ADODB_FETCH_MODE;

        switch (ADODB_ASSOC_CASE) {
        case ADODB_ASSOC_CASE_UPPER:
            $expectedResult = 'ID';
            break;
        case ADODB_ASSOC_CASE_LOWER:
        case ADODB_ASSOC_CASE_NATURAL:
            $expectedResult = 'id';
            break;

        }
        
        /*
        * Fetch a template row from the table
        */
        $sql = "SELECT * FROM {$this->testTableName}";
        
        $testRow = $this->db->getRow($sql);

        $this->assertArrayHasKey(
            $expectedResult,
            $testRow,
            'Row should have an id column'
        );

        // Cannot set the fetch mode to ADODB_FETCH_NUM this way
        //$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
        // must do it through the db object
        $this->db->setFetchMode(ADODB_FETCH_NUM);

        $testRow = $this->db->getRow($sql);

        $expectedResult = '0'; // Numeric index for the first column

        $this->assertArrayHasKey(
            $expectedResult,
            $testRow, 
            'Row should have a numeric column'
        );
    
        $ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
    }

    /**
     * Test for {@see $ADODB_GETONE_EOF}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:adodb_getone_eof
     * 
     * @return void
     */
    public function testGetOneEof(): void
    {
        global $ADODB_GETONE_EOF;
     
        $sql = 'select varchar_field from testtable_1 where id=9999';
        $test = $this->db->getOne($sql);

        $this->assertEquals(
            $test, 
            false, 
            'getOne by default should return false when no row is found'
        );

        $ADODB_GETONE_EOF = -1;

        $test = $this->db->getOne($sql);

        $this->assertEquals(
            $test,
            -1,
            'getOne should now flag by -1 when no row is found'
        );

    }
    /**
     * Test for {@see $ADODB_COUNTRECS}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:adodb_countrecs
     * 
     * @return void
     */ 
    public function testCountRecs(): void
    {   
        global $ADODB_COUNTRECS;
        $ADODB_COUNTRECS = true; // Set to true by default

 
        $sql = "SELECT * FROM testtable_3";
        $result = $this->db->Execute($sql);

        $this->assertEquals(
            11,
            $result->recordCount(), 
            'With ADODB_COUNTRECS set to true, the record count should be 11'
        );        

        $ADODB_COUNTRECS = false;

        $result = $this->db->Execute($sql);
        
        $this->assertEquals(
            -1,
            $result->recordCount(), 
            'With ADODB_COUNTRECS set to false, the record count should be -1'
        );

        $ADODB_COUNTRECS = true; // Reset to default for other tests
    }

}