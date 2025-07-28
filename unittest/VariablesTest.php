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

    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setUp(): void
    {

        $this->db        = $GLOBALS['ADOdbConnection'];
        $this->adoDriver = $GLOBALS['ADOdriver'];
        
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
     * Test for {@see $ADODB_QUOTE_FIELDNAMES}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:adodb_quote_fieldnames
     * 
     * @return void
     */
    public function testQuotingExecute(): void
    {
       
        global $ADODB_QUOTE_FIELDNAMES;

        /*
        * Fetch a template row from the table
        */
        $sql = "SELECT * FROM `table_name` WHERE id=-1";
        $template = $this->db->execute($sql);
       
        $ar = array(
            'column_name' => 'Sample data'
        );

        $sql = $this->db->getInsertSQL(
            $template,
            $ar
        );
        
        $success = $this->db->execute($sql);

        $this->assertIsObject(
            $success, 
            'Data insertion should not succed using Unquoted field and table names'
        );

        $count = $this->db->getOne("SELECT COUNT(*) FROM `table_name`");

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

        $this->assertIsObject(
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

        /*
        * Fetch a template row from the table
        */
        $sql = "SELECT * FROM testtable_1";
        $testRow = $this->db->getRow($sql);

        $this->assertArrayHasKey(
            'id',
            $testRow,
            'Row should have an id column'
        );        

        $ADODB_FETCH_MODE = ADODB_FETCH_NUM;

        $testRow = $this->db->getRow($sql);

        $this->assertArrayHasKey(
            0,
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

 
        $sql = "SELECT * FROM testtable_1";
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