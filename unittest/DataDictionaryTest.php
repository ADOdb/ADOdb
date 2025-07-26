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
 * Class MetaFunctionsTest
 *
 * Test cases for for ADOdb MetaFunctions
 */
class DataDictionaryTest extends TestCase
{
	protected ?object $db;
	protected ?string $adoDriver;
    protected ?object $dataDictionary;

    protected bool $skipFollowingTests = false;

    protected string $testTableName = 'insertion_table';
    protected string $testIndexName1 = 'insertion_index_1';
    protected string $testIndexName2 = 'insertion_index_2';

	public function setUp(): void
	{

        $this->db        = $GLOBALS['ADOdbConnection'];
		$this->adoDriver = $GLOBALS['ADOdriver'];
        $this->dataDictionary = NewdataDictionary($this->db);

	}
	
	public function tearDown(): void
	{
		//$this->db->execute("DROP TABLE IF EXISTS {$this->testTableName}");
	}

    /**
     * Test for {@see ADODConnection::execute() in select mode]
     *
	*/
	public function testBuildBasicTable(): void
	{

       // $this->db->debug = true;
        $this->db->execute("DROP TABLE IF EXISTS {$this->testTableName}");

        $flds = " 
            ID INT NOTNULL PRIMARY KEY AUTOINCREMENT
           
            ";

        $sqlArray = $this->dataDictionary->CreateTableSQL($this->testTableName, $flds);

        /*
        * create the SQL statement necessary to create the table and its columns
        */

        $this->dataDictionary->executeSqlArray($sqlArray);


        $metaTables = $this->db->metaTables();

        $this->assertContains($this->testTableName, $metaTables, 'Test of CreateTableSQL');
 
        if (!array_key_exists($this->testTableName, $metaTables)) {
            $this->skipFollowingTests = true;
        }
       
    }

    public function testaddColumnToBasicTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table was not created successfully');
            return;
        }

        $flds = " 
            VARCHAR_FIELD VARCHAR(50) NOTNULL DEFAULT '',
            DATE_FIELD DATE NOTNULL,
            INTEGER_FIELD INTEGER NOTNULL DEFAULT 0
            ";

        $sqlArray = $this->dataDictionary->AddColumnSQL($this->testTableName, $flds);

        /*
        * create the SQL statement necessary to add the column
        */

       
        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaColumns = $this->db->metaColumns($this->testTableName);

        $this->assertArrayHasKey('VARCHAR_FIELD', $metaColumns, 'Test of AddColumnSQL');

        if (!array_key_exists('VARCHAR_FIELD', $metaColumns)) {
            $this->skipFollowingTests = true;
        }
    }

    public function testaddIndexToBasicTableViaString(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table or column was not created successfully');
            return;
        }

        $flds = "VARCHAR_FIELD, DATE_FIELD, INTEGER_FIELD";
        $this->indexOptions = array(
            'UNIQUE'
        );

        $sqlArray = $this->dataDictionary->createIndexSQL(
            $this->testIndexName1,
            $this->testTableName,
            $flds,
            $this->indexOptions
        );

        /*
        * create the SQL statement necessary to add the index
        */

        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaIndexes = $this->db->metaIndexes($this->testTableName);

        $this->assertArrayHasKey($this->testIndexName1, $metaIndexes, 'Test of AddIndexSQL Using String For Fields');

        //print_r($metaIndexes);
        //if (array_key_exists($this->testIndexName1, $metaIndexes)) 
        //{
        //    $this->db->execute("DROP INDEX {$this->testIndexName} FROM {$this->testTableName}");
        //}

        
    }

    public function testaddIndexToBasicTableViaArray(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table or column was not created successfully');
            return;
        }

        $flds = array("VARCHAR_FIELD", "DATE_FIELD", "INTEGER_FIELD");
        $this->indexOptions = array(
            'UNIQUE'
        );

        $sqlArray = $this->dataDictionary->createIndexSQL(
            $this->testIndexName2,
            $this->testTableName,
            $flds,
            $this->indexOptions
        );

        /*
        * create the SQL statement necessary to add the index
        */

        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaIndexes = $this->db->metaIndexes($this->testTableName);

        $this->assertArrayHasKey($this->testIndexName2, $metaIndexes, 'Test of AddIndexSQL Using Array For Fields');

        //if (array_key_exists($this->testIndexName, $metaIndexes)) {
        //    $$this->db->execute("DROP INDEX {$this->testIndexName}");
        //}
       
    }

    public function testdropIndexFromBasicTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table or column was not created successfully');
            return;
        }

        $sqlArray = $this->dataDictionary->dropIndexSQL(
            $this->testIndexName1,
            $this->testTableName,

        );

        /*
        * create the SQL statement necessary to add the index
        */

        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaIndexes = $this->db->metaIndexes($this->testTableName);

        $this->assertArrayNotHasKey($this->testIndexName1, $metaIndexes, 'Test of dropIndexSQL Using Array For Fields');

           
    }

    
}