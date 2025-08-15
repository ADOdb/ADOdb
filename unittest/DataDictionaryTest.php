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
    protected bool $skipCommentTests = false;

    protected string $testTableName = 'insertion_table';
    protected string $testIndexName1 = 'insertion_index_1';
    protected string $testIndexName2 = 'insertion_index_2';

    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setup(): void
    {

        $this->db        = &$GLOBALS['ADOdbConnection'];
        $this->adoDriver = $GLOBALS['ADOdriver'];
        $this->dataDictionary = $GLOBALS['ADOdataDictionary'];

    }
    
    
    /**
     * Test for {@see ADODConnection::CreateTableSQL()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:createtablesql
     * 
     * @return void
     */
    public function testBuildBasicTable(): void
    {

        $this->db->startTrans();
        $this->db->execute("DROP TABLE IF EXISTS {$this->testTableName}");
        $this->db->completeTrans();

        $this->db->startTrans();
       

        $flds = "id I NOTNULL PRIMARY KEY AUTOINCREMENT";

        $sqlArray = $this->dataDictionary->CreateTableSQL($this->testTableName, $flds);

        $this->dataDictionary->executeSqlArray($sqlArray);

        $this->db->completeTrans();
        
        $metaTables = $this->db->metaTables();

        $this->assertContains(
            $this->testTableName, 
            $metaTables, 
            'Test of CreateTableSQL'
        );
 
        if (!array_key_exists($this->testTableName, $metaTables)) {
            $this->skipFollowingTests = true;
        }
       
    }

    /**
     * Test for {@see ADODConnection::addColumnSQL()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:addcolumnsql
     * 
     * @return void
     */
    public function testaddColumnToBasicTable(): void
    {
        
        $this->db->startTrans();
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        $flds = " 
            VARCHAR_FIELD C(50) NOTNULL DEFAULT '',
            DATE_FIELD D NOTNULL DEFAULT '2010-01-01',
            INTEGER_FIELD I NOTNULL DEFAULT 0,
            BOOLEAN_FIELD I NOTNULL DEFAULT 0,
            DROPPABLE_FIELD N(10.6) DEFAULT 80.111
            ";

        $sqlArray = $this->dataDictionary->AddColumnSQL($this->testTableName, $flds);

        $this->dataDictionary->executeSqlArray($sqlArray);

        $this->db->completeTrans();

        $metaColumns = $this->db->metaColumns($this->testTableName);

        $this->assertArrayHasKey(
            'VARCHAR_FIELD',
            $metaColumns,
            'Test of AddColumnSQL'
        );

        if (!array_key_exists('VARCHAR_FIELD', $metaColumns)) {
            $this->skipFollowingTests = true;
        }
    }

    /**
     * Test for {@see ADODConnection::alterColumnSQL()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:altercolumnsql
     * 
     * @return void
     */
    public function testalterColumnInBasicTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        $flds = " 
            VARCHAR_FIELD VARCHAR(120) NOTNULL DEFAULT ''
            ";

        $sqlArray = $this->dataDictionary->alterColumnSQL(
            $this->testTableName,
            $flds
        );

        $this->dataDictionary->executeSqlArray($sqlArray);

        $metaColumns = $this->db->metaColumns($this->testTableName);

        $this->assertArrayHasKey(
            'VARCHAR_FIELD', 
            $metaColumns, 
            'Test of AlterColumnSQL'
        );

        $this->assertSame(
            '120',
            $metaColumns['VARCHAR_FIELD']->max_length, 
            'Test of AlterColumnSQL - Increase of length of VARCHAR_FIELD to 120'
        );
    }

    /**
     * Test for {@see ADODConnection::renameColumnSQL()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:renamecolumnsql
     * 
     * @return void
     */
    public function testRenameColumnInBasicTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }
       
        
        $sqlArray = $this->dataDictionary->renameColumnSQL(
            $this->testTableName, 
            'BOOLEAN_FIELD', 
            'ANOTHER_BOOLEAN_FIELD'
        );
       
        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        $this->db->completeTrans();
        
        $metaColumns = $this->db->metaColumnNames($this->testTableName);
  
        $this->assertArrayHasKey(
            'ANOTHER_BOOLEAN_FIELD', 
            $metaColumns, 
            'Test of RenameColumnSQL by renaming BOOLEAN_FIELD to ANOTHER_BOOLEAN_FIELD'
        );

        if (array_key_exists('ANOTHER_BOOLEAN_FIELD', $metaColumns)) {
        
            /*
            * reset the column name back to original
            */
            $sqlArray = $this->dataDictionary->renameColumnSQL(
                $this->testTableName, 
                'ANOTHER_BOOLEAN_FIELD', 
                'BOOLEAN_FIELD'
            );
            
            $this->db->startTrans();
            $this->dataDictionary->executeSqlArray($sqlArray);
            $this->db->completeTrans();

            $metaColumns = $this->db->metaColumnNames($this->testTableName);
  
            $this->assertArrayHasKey(
                'BOOLEAN_FIELD', 
                $metaColumns, 
                'Test of RenameColumnSQL by renaming ANOTHER_BOOLEAN_FIELD back to BOOLEAN_FIELD'
            );

        }
    
    }

    /**
     * Test for {@see ADODConnection::dropColumnSQL()}
     * 
     * Written entirely by Copilot
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:dropcolumnsql
     *
     * @return void
     */
    public function testDropColumnInBasicTable(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        $this->db->startTrans();
        $sqlArray = $this->dataDictionary->dropColumnSQL(
            $this->testTableName, 
            'DROPPABLE_FIELD'
        );

        $this->dataDictionary->executeSqlArray($sqlArray);

        $this->db->completeTrans();
        
        $metaColumns = $this->db->metaColumns($this->testTableName);

        $this->assertArrayNotHasKey(
            'DROPPABLE_FIELD', 
            $metaColumns, 
            'Test of DropColumnSQL'
        );

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
            DATE_FIELD DATE NOTNULL DEFAULT '2010-01-01',
            ANOTHER_INTEGER_FIELD INTEGER NOTNULL DEFAULT 0,
            YET_ANOTHER_VARCHAR_FIELD VARCHAR(50) NOTNULL DEFAULT ''
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

        
        $this->assertArrayHasKey(
            'YET_ANOTHER_VARCHAR_FIELD',
            $metaColumns,
            'Test of changeTableSQL - New varchar [yet_another_varchar_field] column added'
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
        $this->db->startTrans();

        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        $sqlArray = $this->dataDictionary->renameTableSQL(
            'insertion_table', 
            'insertion_table_renamed'
        );
              

        $this->dataDictionary->executeSqlArray($sqlArray);

        $this->db->completeTrans();

        $metaTables = $this->db->metaTables();

        $this->assertContains(
            'insertion_table_renamed', 
            $metaTables, 
            'Test of renameTableSQL - renamed table exists'
        );

        $this->assertNotContains(
            'insertion_table', 
            $metaTables, 
            'Test of renameTableSQL - old table should not exist'
        );

        $this->db->startTrans();
        $sqlArray = $this->dataDictionary->renameTableSQL(
            'insertion_table_renamed',
            'insertion_table'
        );
              
        $this->dataDictionary->executeSqlArray($sqlArray);

        $this->db->completeTrans();
       
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

        return;

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

        /*
        * The default configuration for the tests is to skip database creation 
        * Because this needs Create db privileges
        */
        if (!array_key_exists('meta', $GLOBALS['TestingControl'])) {
 
            $this->markTestSkipped(
                'Skipping database creation test as per configuration'
            );
            return;
        } else if ($GLOBALS['TestingControl']['meta']['skipDbCreation']) {
            $this->markTestSkipped(
                'Skipping database creation test as per configuration'
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


    /**
     * Tests setting a comment on a column using {@see ADODConnection::setColumnCommentSql()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:setcommentsql
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:getcommentsql   *
     * @return void
     */
    public function testColumnCommentSql(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        if ($this->adoDriver == 'mysqli') {
            $this->markTestSkipped(
                'Skipping test as setCommentSql not supported by the driver in this format. See the driver-specific version'
            );
            $this->skipCommentTests = true;
            return;
        }

        $sql = "SELECT 
    obj_description(format('%s.%s',isc.table_schema,isc.table_name)::regclass::oid, 'pg_class') as table_description,
    pg_catalog.col_description(format('%s.%s',isc.table_schema,isc.table_name)::regclass::oid,isc.ordinal_position) as column_description
FROM
    information_schema.columns isc";
        print_r($this->db->getAll($sql));
        return;

        $sql = $this->dataDictionary->setColumnCommentSql(
            'testtable_1', 
            'varchar_field',
            'varchar_test_comment'
        );

        if (!$sql) {
            $this->markTestSkipped(
                'Skipping test as setCommentSql not supported by the driver'
            );
            $this->skipCommentTests = true;
            return;
        }
              
        $this->db->startTrans();
        $response = $this->db->execute($sql);
        $this->db->completeTrans();
       
        $ok = is_object($response);
       
        $this->assertEquals(
            true,
            $ok, 
            'Test of setColumnCommentSql - should return an object if the comment was set successfully'
        );

        if (!$ok) {
            return;          
        }

        $className = get_class($response);
        $this->assertStringContainsString(
            'ADORecordSet_',
            $className,
            'Test of setCommentSql - should return an ADORecordset_ object'
        );
    
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        if ($this->skipCommentTests) {
            $this->markTestSkipped(
                'Skipping getColumnCommentSql test as feature not supported by the driver'
            );
            return;
        }

         
        $sql = $this->dataDictionary->getColumnCommentSql(
            'testtable_1', 
            'varchar_field'
        );

          
        $comment = $this->db->getOne($sql);

        $this->assertSame(
            'varchar_test_comment', 
            $comment, 
            'Test of getColumnCommentSql - should return the comment set previously'
        );
    }

    /**
     * Tests setting a comment on a column using {@see ADODConnection::setColumnCommentSql()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:setcommentsql
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:getcommentsql   *
     * @return void
     */
    public function testTableCommentSql(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }
       
        $sql = $this->dataDictionary->setTableCommentSql(
            'testtable_1', 
            'testtable_1_comment'
        );

        if (!$sql) {
            $this->markTestSkipped(
                'Skipping test as setTableCommentSql not supported by the driver'
            );
            $this->skipCommentTests = true;
            return;
        }
              
        $this->db->startTrans();
        $response = $this->db->execute($sql);
        $this->db->completeTrans();
       
        $ok = is_object($response);
       
        $this->assertEquals(
            true,
            $ok, 
            'Test of setTableCommentSql - should return an object if the comment was set successfully'
        );

        if (!$ok) {
            return;          
        }

        $className = get_class($response);
        $this->assertStringContainsString(
            'ADORecordSet_',
            $className,
            'Test of setTableCommentSql - should return an ADORecordset_ object'
        );
    
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        if ($this->skipCommentTests) {
            $this->markTestSkipped(
                'Skipping getTableCommentSql test as feature not supported by the driver'
            );
            return;
        }

        $sql = $this->dataDictionary->getTableCommentSql(
            'testtable_1'
        );

        $comment = $this->db->getOne($sql);

        $this->assertEquals(
            'testtable_1_comment', 
            $comment, 
            'Test of getTableCommentSql - should return the table comment set previously'
        );
    }
   
}