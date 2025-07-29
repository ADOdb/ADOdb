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
     * Test for {@see ADODConnection::CreateTableSQL()}
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:createtablesql
     * @return void
     */
	public function testBuildBasicTable(): void
	{

        $this->db->execute("DROP TABLE IF EXISTS {$this->testTableName}");

        $flds = " 
            ID INT NOTNULL PRIMARY KEY AUTOINCREMENT
           
            ";

        $sqlArray = $this->dataDictionary->CreateTableSQL($this->testTableName, $flds);

        $this->dataDictionary->executeSqlArray($sqlArray);


        $metaTables = $this->db->metaTables();

        $this->assertContains($this->testTableName, $metaTables, 'Test of CreateTableSQL');
 
        if (!array_key_exists($this->testTableName, $metaTables)) {
            $this->skipFollowingTests = true;
        }
       
    }

    /**
     * Test for {@see ADODConnection::addColumnSQL()}
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:addcolumnsql
     * @return void
     */
    public function testaddColumnToBasicTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table was not created successfully');
            return;
        }

        $flds = " 
            VARCHAR_FIELD VARCHAR(50) NOTNULL DEFAULT '',
            DATE_FIELD DATE NOTNULL,
            INTEGER_FIELD INTEGER NOTNULL DEFAULT 0,
            DROPPABLE_FIELD DECIMAL(10,6) NOTNULL DEFAULT 80.111
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

    /**
     * Test for {@see ADODConnection::alterColumnSQL()}
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:altercolumnsql
     * @return void
     */
    public function testalterColumnInBasicTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table was not created successfully');
            return;
        }

        $flds = " 
            VARCHAR_FIELD VARCHAR(120) NOTNULL DEFAULT ''
            ";

        $sqlArray = $this->dataDictionary->alterColumnSQL($this->testTableName, $flds);

        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaColumns = $this->db->metaColumns($this->testTableName);

        $this->assertArrayHasKey('VARCHAR_FIELD', $metaColumns, 'Test of AlterColumnSQL');

        $this->assertSame('120', $metaColumns['VARCHAR_FIELD']->max_length, 'Test of AlterColumnSQL - Increase of length of VARCHAR_FIELD to 120');
    }

    /**
     * Test for {@see ADODConnection::renameColumnSQL()}
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:renamecolumnsql
     * @return void
     */
    public function testRenameColumnInBasicTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table was not created successfully');
            return;
        }

       
        $sqlArray = $this->dataDictionary->alterColumnSQL($this->testTableName, 'VARCHAR_FIELD', 'ANOTHER_VARCHAR_FIELD');

        $this->dataDictionary->executeSqlArray($sqlArray);

        
        $metaColumns = $this->db->metaColumns($this->testTableName);

        $this->assertArrayHasKey('ANOTHER_VARCHAR_FIELD', $metaColumns, 'Test of RenameColumnSQL');

        if (array_key_exists('ANOTHER_VARCHAR_FIELD', $metaColumns)) {
        
            $sqlArray = $this->dataDictionary->alterColumnSQL($this->testTableName, 'ANOTHER_VARCHAR_FIELD', 'VARCHAR_FIELD');
            $this->dataDictionary->executeSqlArray($sqlArray);
        }
    
    }

    /**
     * Test for {@see ADODConnection::dropColumnSQL()}
     * Written entirely by Copilot
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:dropcolumnsql
     *
     * @return void
     */
    public function testDropColumnInBasicTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table was not created successfully');
            return;
        }

        $sqlArray = $this->dataDictionary->dropColumnSQL($this->testTableName, 'DROPPABLE_FIELD');

        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaColumns = $this->db->metaColumns($this->testTableName);

        $this->assertArrayNotHasKey('DROPPABLE_FIELD', $metaColumns, 'Test of DropColumnSQL');

        if (array_key_exists('DROPPABLE_FIELD', $metaColumns)) {
            $this->skipFollowingTests = true;
        }
    }
    
    /**
     * Test for {@see ADODConnection::createIndexSQL()} passing a string 
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:createindexsql
     * 
     * @return void
     */
    public function testaddIndexToBasicTableViaString(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table or column was not created successfully');
            return;
        }

        $flds = "VARCHAR_FIELD, DATE_FIELD, INTEGER_FIELD";
        $indexOptions = array(
            'UNIQUE'
        );

        $sqlArray = $this->dataDictionary->createIndexSQL(
            $this->testIndexName1,
            $this->testTableName,
            $flds,
            $indexOptions
        );

        /*
        * create the SQL statement necessary to add the index
        */

        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaIndexes = $this->db->metaIndexes($this->testTableName);

        $this->assertArrayHasKey(
            $this->testIndexName1, 
            $metaIndexes, 
            'Test of AddIndexSQL Using String For Fields'
        );
        
    }

    /**
     * Test for {@see ADODConnection::createIndexSQL()} passing an array 
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:createindexsql
     * 
     * @return void
     */
    public function testaddIndexToBasicTableViaArray(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table or column was not created successfully');
            return;
        }

        $flds = array(
            "DATE_FIELD", 
            "INTEGER_FIELD",
            "VARCHAR_FIELD" 
        );
        $indexOptions = array(
            'UNIQUE'
        );

        $sqlArray = $this->dataDictionary->createIndexSQL(
            $this->testIndexName2,
            $this->testTableName,
            $flds,
            $indexOptions
        );

        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaIndexes = $this->db->metaIndexes($this->testTableName);

        $this->assertArrayHasKey(
            $this->testIndexName2, 
            $metaIndexes, 
            'Test of AddIndexSQL Using Array For Fields'
        );

        
    }

    /**
     * Test for {@see ADODConnection::dropIndexSQL()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:dropindexsql
     * 
     * @return void
     */
    public function testdropIndexFromBasicTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table or column was not created successfully');
            return;
        }

        $sqlArray = $this->dataDictionary->dropIndexSQL(
            $this->testIndexName1,
            $this->testTableName
        );

        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaIndexes = $this->db->metaIndexes($this->testTableName);

        $this->assertArrayNotHasKey(
            $this->testIndexName1, 
            $metaIndexes, 
            'Test of dropIndexSQL Using Array For Fields'
        );
   
    }

    /**
     * Test for {@see ADODConnection::changeTableSQL()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:changetablesql
     * 
     * @return void
     */
    public function testChangeTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping tests as the table was not created successfully');
            return;
        }

        $flds = " 
            VARCHAR_FIELD VARCHAR(50) NOTNULL DEFAULT '',
            DATE_FIELD DATE NOTNULL,
            ANOTHER_INTEGER_FIELD INTEGER NOTNULL DEFAULT 0
            ";

        $sqlArray = $this->dataDictionary->changeTableSQL($this->testTableName, $flds);

        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaColumns = $this->db->metaColumns($this->testTableName);

        $this->assertArrayNotHasKey(
            'INTEGER_FIELD', 
            $metaColumns, 
            'Test of changeTableSQL - old column removed'
        );

        $this->assertArrayHasKey(
            'ANOTHER_INTEGER_FIELD',
            $metaColumns,
            'Test of changeTableSQL - New column added'
        );

        if (!array_key_exists('ANOTHER_VARCHAR_FIELD', $metaColumns)) {
            $this->skipFollowingTests = true;
        }
    }


    /**
     * Test for {@see ADODConnection::renameTableSQL()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:renametable
     * 
     * @return void
     */
    public function testRenameTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        $sqlArray = $this->dataDictionary->renameTableSQL(
            $this->testTableName, 
            'insertion_table_renamed'
        );
              
        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaTables = $this->db->metaTables();
       
        $this->assertArrayHasKey(
            'insertion_table_renamed', 
            $metaTables, 
            'Test of renameTableSQL - renamed table exists'
        );

        $this->assertArrayNotHasKey(
            $this->testTableName, 
            $metaTables, 
            'Test of renameTableSQL - old table does not exist'
        );

        $sqlArray = $this->dataDictionary->renameTableSQL(
            'insertion_table_renamed',
            $this->testTableName
        );
              
        $this->dataDictionary->executeSqlArray($sqlArray);
       
    }

    /**
     * Test for {@see ADODConnection::dropTableSQL()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:droptablesql
     * 
     * @return void
     */
    public function testDropTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        $sqlArray = $this->dataDictionary->dropTableSQL($this->testTableName);

        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaTables = $this->db->metaTables();

        $this->assertArrayNotHasKey(
            $this->testTableName, 
            $metaTables, 
            'Test of dropTableSQL - table should not exist'
        );
       
    }

    /**
     * Test for {@see ADODConnection::createDatabase()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:createdatabase
     * 
     * @return void
     */
    public function testCreateDatabase(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        $dbName = 'unittest_database';
        $sqlArray = $this->dataDictionary->createDatabase($dbName);

        $this->dataDictionary->executeSqlArray($sqlArray);

        // Check if the database was created successfully
        $metaDatabases = $this->db->metaDatabases();
        $this->assertContains(
            $dbName, 
            $metaDatabases, 
            'Test of createDatabase - database should exist'
        );

        // Clean up by dropping the database
        $this->dataDictionary->dropDatabase($dbName);
    }
   
}