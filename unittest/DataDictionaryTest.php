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
class DataDictionaryTest extends ADOdbTestCase
{
   
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

        parent::setup();
        /*
        * Find the correct test table name
        */
              
        $this->buildBasicTable();
         
    }
    
    
    /**
     * Test for {@see ADODConnection::CreateTableSQL()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:createtablesql
     * 
     * @return void
     */
    public function buildBasicTable(): void
    {

        $sql = "DROP TABLE IF EXISTS {$this->testTableName}";

        list ($response,$errno,$errmsg) = $this->executeSqlString($sql);
               

        $flds = "ID I NOTNULL PRIMARY KEY AUTOINCREMENT";

        $sqlArray = $this->dataDictionary->CreateTableSQL(
            $this->testTableName, 
            $flds
        );

        list ($response,$errno,$errmsg) = $this->executeDictionaryAction($sqlArray);
        $this->dataDictionary->executeSqlArray($sqlArray);
        if ($errno > 0) {
            $this->fail(
                'Error creating insertion_table'
            );
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
        
    
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        $flds = " 
            VARCHAR_FIELD C(50) NOTNULL DEFAULT '',
            DATE_FIELD D NOTNULL DEFAULT '2010-01-01',
            INTEGER_FIELD I4 NOTNULL DEFAULT 0,
            BOOLEAN_FIELD I NOTNULL DEFAULT 0,
            DECIMAL_FIELD N(8.4) DEFAULT 0,
            DROPPABLE_FIELD N(10.6) DEFAULT 80.111
            ";

        $sqlArray = $this->dataDictionary->AddColumnSQL($this->testTableName, $flds);

        list ($response,$errno,$errmsg) = $this->executeDictionaryAction($sqlArray);
        
        if ($errno > 0) {
            return;
        }

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
       
        $tableName = $this->testTableName;

        $metaColumns = $this->db->metaColumns($tableName);

        if (!array_key_exists('VARCHAR_FIELD', $metaColumns)) {
            $this->testaddColumnToBasicTable();
            $metaColumns = $this->db->metaColumns($tableName);
            
        }

        $flds = " 
            VARCHAR_FIELD VARCHAR(120) NOTNULL DEFAULT ''
            ";

        $sqlArray = $this->dataDictionary->alterColumnSQL(
            $tableName,
            $flds
        );

        if (count($sqlArray) == 0) {
            $this->fail(
                'AlterColumnSql() not supported currently by driver'
            );
            $this->db->completeTrans();
            return;
        }
        
        $this->db->startTrans();

        $this->dataDictionary->executeSqlArray($sqlArray);
        if ($this->db->errorNo()) {
            $this->fail(
                $this->db->errorMsg()
            );
            return;
        }
        $this->db->completeTrans();
        /*
        * re-read the column definitions
        */
        $metaColumns = $this->db->metaColumns($tableName);
        
        $this->assertArrayHasKey(
            'VARCHAR_FIELD', 
            $metaColumns, 
            'AlterColumnSQL should not remove the VARCHAR_FIELD from the table'
        );

        $this->assertSame(
            '120',
            $metaColumns['VARCHAR_FIELD']->max_length, 
            'AlterColumnSQL should have Increased the ' . 
            'length of VARCHAR_FIELD to from 50 to 120'
        );

        $flds = " 
            INTEGER_FIELD I8 NOTNULL DEFAULT 1
            ";

        $sqlArray = $this->dataDictionary->alterColumnSQL(
            $tableName,
            $flds
        );

        $this->db->startTrans();

        $this->dataDictionary->executeSqlArray($sqlArray);

        if ($this->db->errorNo()) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
        $this->db->completeTrans();
        /*
        * re-read the column definitions
        */
        $metaColumns = $this->db->metaColumns($tableName);
        
        $this->assertArrayHasKey(
            'INTEGER_FIELD', 
            $metaColumns, 
            'AltercolumnSQL INTEGER_FIELD should still exist in the table'
        );

        $this->assertSame(
            '1',
            $metaColumns['INTEGER_FIELD']->default_value, 
            'AltercolumnSql should have change the default ' . 
            'of INTEGER_FIELD from 0 to 1'
        );

        /*
        * Change the scale of the decimal field
        */

         $flds = " 
            DECIMAL_FIELD N(16.12) NOTNULL
            ";

        $sqlArray = $this->dataDictionary->alterColumnSQL(
            $tableName,
            $flds
        );

        $this->db->startTrans();

        $this->dataDictionary->executeSqlArray($sqlArray);
        
        if ($this->db->errorNo()) {
            $this->fail(
                $this->db->errorMsg()
            );
             $this->db->completeTrans();
            return;
        }

        $this->db->completeTrans();
        /*
        * re-read the column definitions
        */
        $metaColumns = $this->db->metaColumns($tableName);
               
        $this->assertArrayHasKey(
            'DECIMAL_FIELD', 
            $metaColumns, 
            'AltercolumnSQL DECIMAL_FIELD should still exist in the table'
        );

        $this->assertSame(
            '16',
            $metaColumns['DECIMAL_FIELD']->max_length, 
            'AlterColumnSQL: maxlength of DECIMAL_FIELD' . 
            'should have changed from 8 to 16'
        );

        $this->assertSame(
            '12',
            $metaColumns['DECIMAL_FIELD']->scale, 
            'AlterColumnSQL: Change of scale of DECIMAL_FIELD 4 to 12'
        );
    }

    /**
     * Test for {@see ADODConnection::addColumnSQL()} adding a duplicate column with different case
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:addcolumnsql
     * 
     * @return void
     */
    function testAddDuplicateCasedColumn(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        $tableName = $this->testTableName;

        $metaColumns = $this->db->metaColumns($tableName);

        if (!array_key_exists('VARCHAR_FIELD', $metaColumns)) {
            $this->testaddColumnToBasicTable();

        }

        $tableName = $this->testTableName;

        $flds = " 
            vArcHar_field C(50) NOTNULL DEFAULT ''
            ";

        $sqlArray = $this->dataDictionary->AddColumnSQL($tableName, $flds);
     
        $assertion = $this->assertIsArray(
            $sqlArray, 
            'AddColumnSQL should return an array even ' . 
            'if the column already exists with different case'
        );

        if ($assertion) {
            $this->assertCount(
                0,
                $sqlArray,
                'AddColumnSql should return an empty array ' . 
                'if the column already exists'
            );
        }

        $flds = " 
            VARCHAR_FIELD C(50) NOTNULL DEFAULT ''
            ";

        $sqlArray = $this->dataDictionary->AddColumnSQL($tableName, $flds);

        $assertion = $this->assertIsArray(
            $sqlArray, 
            'AddColumnSQL - should return an array even ' . 
            'if the column already exists with same case'
        );    

        if ($assertion) {
            $this->assertCount(
                0,
                $sqlArray,
                'AddColumnSql should return an empty array ' . 
                'if the column already exists'
            );
        }

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

        $assertion = $this->assertIsArray(
            $sqlArray,
            'renameColumnSql should return an array'
        );

        if ($assertion) {
            if (count($sqlArray) == 0) {
                $this->fail(
                    'renameColumnSql not supported by driver'
                );
                return;
            }
        }

        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        if ($this->db->errorNo()) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
        $this->db->completeTrans();
        
        $metaColumns = $this->db->metaColumns($this->testTableName);
  
        $this->assertArrayHasKey(
            'ANOTHER_BOOLEAN_FIELD', 
            $metaColumns, 
            'RenameColumnSQL should have renamed ' . 
            'BOOLEAN_FIELD to ANOTHER_BOOLEAN_FIELD'
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
            if ($this->db->errorNo()) {
                $this->fail(
                    $this->db->errorMsg()
                );
                $this->db->completeTrans();
                return;
            }
            $this->db->completeTrans();

            $metaColumns = $this->db->metaColumnNames($this->testTableName);
  
            $this->assertArrayHasKey(
                'BOOLEAN_FIELD', 
                $metaColumns, 
                'RenameColumnSQL should have renamed ' . 
                'ANOTHER_BOOLEAN_FIELD back to BOOLEAN_FIELD'
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

             
        $sqlArray = $this->dataDictionary->dropColumnSQL(
            $this->testTableName, 
            'DROPPABLE_FIELD'
        );

        if (!is_array($sqlArray)) {
            $this->fail(
                'dropColumnSql() should always return an array'
            );
            return;
        }

        if (count($sqlArray) == 0) {
            $this->fail(
                'dropColumnSql() not supported by driver'
            );
        }

        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        if ($this->db->errorNo()) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
        $this->db->completeTrans();
        
        $metaColumns = $this->db->metaColumns($this->testTableName);

        $this->assertArrayNotHasKey(
            'DROPPABLE_FIELD', 
            $metaColumns, 
            'after executution of dropColumnSQL(), ' . 
            'column DROPPABLE_FIELD should no longer exist'
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
        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        if ($this->db->errorNo()) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
        $this->db->completeTrans();
        
        $metaIndexes = $this->db->metaIndexes($this->testTableName);

        $this->assertArrayHasKey(
            $this->testIndexName1, 
            $metaIndexes, 
            'AddIndexSQL Using String For Fields should now ' . 
            'contain index ' . $this->testIndexName1
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
            $this->markTestSkipped(
                'Skipping tests as the table or ' . 
                'column was not created successfully'
            );
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

        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        if ($this->db->errorNo()) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
        $this->db->completeTrans();

        $metaIndexes = $this->db->metaIndexes($this->testTableName);

        $this->assertArrayHasKey(
            $this->testIndexName2, 
            $metaIndexes, 
            'AddIndexSQL Using Array For Fields should have ' . 
            'added index ' . $this->testIndexName1
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

        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        if ($this->db->errorNo()) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
        $this->db->completeTrans();

        $metaIndexes = $this->db->metaIndexes($this->testTableName);

        $this->assertArrayNotHasKey(
            $this->testIndexName1, 
            $metaIndexes, 
            'dropIndexSQL() Using Array For Fields ' . 
            'should have dropped index ' . $this->testIndexName1
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
            $this->markTestSkipped(
                'Skipping tests as the table was not ' . 
                'created successfully'
            );
            return;
        }

        $flds = " 
            VARCHAR_FIELD VARCHAR(50) NOTNULL DEFAULT '',
            DATE_FIELD DATE NOTNULL DEFAULT '2010-01-01',
            ANOTHER_INTEGER_FIELD INTEGER NOTNULL DEFAULT 0,
            YET_ANOTHER_VARCHAR_FIELD VARCHAR(50) NOTNULL DEFAULT ''
            ";

        $sqlArray = $this->dataDictionary->changeTableSQL(
            $this->testTableName, 
            $flds
        );

        $assertion = $this->assetIsArray(
            $sqlArray,
            'changeTableSql() should alway return an array'
        );

        if (!$assertion) {
            return;
        }

        if (count($sqlArray) == 0) {
            $this->fail(
                'changeTableSql() not supported by driver'
            );
            return;
        }

        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        if ($this->db->errorNo()) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
        $this->db->completeTrans();


        $metaColumns = $this->db->metaColumns($tableName);

        $this->assertArrayHasKey(
            'INTEGER_FIELD', 
            $metaColumns, 
            'changeTableSQL() using $dropflds=false ' . 
            '- old column should be retained even if ' . 
            'not in the new definition'
        );

        $this->assertArrayHasKey(
            'ANOTHER_INTEGER_FIELD',
            $metaColumns,
            'changeTableSql() ANOTHER_INTEGER_FIELD should have been added'
        );

        
        $this->assertArrayHasKey(
            'YET_ANOTHER_VARCHAR_FIELD',
            $metaColumns,
            'changeTableSQ() YET_ANOTHER_VARCHAR_FIELD should have been added'
        );

        if (!array_key_exists('ANOTHER_VARCHAR_FIELD', $metaColumns)) {
            $this->skipFollowingTests = true;
        }

        /*
        * Now re-execute wth the drop flag set to true
        */
        $sqlArray = $this->dataDictionary->changeTableSQL(
            $this->testTableName, 
            $flds, 
            false, 
            true
        );

        
        $assertion = $this->assetIsArray(
            $sqlArray,
            'changeTableSql() should alway return an array'
        );

        if (!$assertion) {
            return;
        }

        if (count($sqlArray) == 0) {
            $this->fail(
                'changeTableSql() not supported by driver'
            );
            return;
        }

        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        
        if ($this->db->errorNo() > 0) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
        $this->db->completeTrans();

        $metaColumns = $this->db->metaColumns($this->testTableName);

        $this->assertArrayNotHasKey(
            'INTEGER_FIELD', 
            $metaColumns, 
            'changeTableSQL() using $dropFlds=true ' . 
            'old column INTEGER_FIELD should be dropped'
        );

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
            'insertion_table', 
            'insertion_table_renamed'
        );

        $assertionResult = $this->assertIsArray(
            $sqlArray,  
            'Test of renameTableSQL - should return an array of SQL statements'
        );
         
        if (!$assertionResult) {
            $this->markTestSkipped(
                'Skipping test as renameTableSQL not supported by the driver'
            );
            return;
        }

        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        if ($this->db->errorNo() > 0) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
        $this->db->completeTrans();

        /*
        * Depends on the success of the metatables 
        * function passing the new table name
        */
        $metaTables = $this->db->metaTables('T', '', 'insertion_table_renamed');
       
        $assertionResult = $this->assertFalse(
            $metaTables, 
            'Test of renameTableSQL - new table insertion_table_renamed should exist'
        );

        if ($assertionResult) {
            $this->skipFollowingTests = true;
            return;
        }

        $this->assertSame(
            'insertion_table_renamed',
            $metaTables[0], 
            'Test of renameTableSQL - renamed table exists'
        );

         $metaTables = $this->db->metaTables('T', '', 'insertion_table_renamed');

        
        if (empty($metaTables)) {
            $this->skipFollowingTests = true;
            return;
        }

        $sqlArray = $this->dataDictionary->renameTableSQL(
            'insertion_table_renamed',
            'insertion_table'
        );
       
        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        if ($this->db->errorNo()) {
            $this->fail(
                $this->db->errorMsg()
            );
        }
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

        
        $sqlArray = $this->dataDictionary->dropTableSQL($this->testTableName);
        
        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        $this->db->completeTrans();

        $metaTables = $this->db->metaTables('T','',$this->testTableName);

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

        $this->db->startTrans();
        $this->dataDictionary->executeSqlArray($sqlArray);
        if ($this->db->errorNo() > 0) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
        $this->db->completeTrans();
        

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
        if ($this->db->errorNo() > 0) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
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
        if ($this->db->errorNo() > 0) {
            $this->fail(
                $this->db->errorMsg()
            );
            $this->db->completeTrans();
            return;
        }
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