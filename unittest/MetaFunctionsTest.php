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
class MetaFunctionsTest extends ADOdbTestCase
{
   
    protected string $testTableName = 'testtable_1';

    protected array $testfetchModes = [
        ADODB_FETCH_NUM   => 'ADODB_FETCH_NUM',
        ADODB_FETCH_ASSOC => 'ADODB_FETCH_ASSOC',
        ADODB_FETCH_BOTH  => 'ADODB_FETCH_BOTH'
    ];
    
         
    /**
     * Test for {@see ADODConnection::metaTables()]
     *
     * @dataProvider providerTestMetaTables
     * 
     * @param bool   $includesTable1
     * @param string $filterType
     * @param string $mask
     * 
     * @return void
     */
    public function testMetaTables(bool $includesTable1,mixed $filterType, mixed $mask) : void
    {
        
        foreach ($this->testfetchModes as $fetchMode => $fetchModeName) {
            
            $this->db->setFetchMode($fetchMode);
            
            $executionResult = $this->db->metaTables(
                $filterType, 
                false, //$this->db->database, 
                $mask
            );
            list($errno, $errmsg) = $this->assertADOdbError('metaTables()');

            $tableExists = $executionResult && in_array(strtoupper($this->testTableName), $executionResult);

            $this->assertSame(
                $includesTable1,            
                $tableExists,
                sprintf(
                    '[FETCH MODE: %s] Table %s should be in metaTables with filterType %s mask %s',
                    $fetchModeName,
                    $this->testTableName,
                    $filterType,
                    $mask
                )
            );
        }
    }
    
    /**
     * Data provider for {@see testMetaTables()}
     *
     * @return array [bool match, string $filterType string $mask]
     */
    public function providerTestMetaTables(): array
    {
        $match = substr($this->testTableName, 0, 4) . '%';
        return [
            'Show both Tables & Views' => [true,false,false],
            'Show only Tables' => [true,'TABLES',false],
            'Show only Views' => [false,'VIEWS',false],
            'Show only [T]ables' => [true,'T',false],
            'Show only [V]iews' => [false,'V',false],
            'Show only tables beginning test%' => [true,false,$match],
            'Show only tables beginning notest%' => [false,false,'notest%']
           ];
    }

    /**
     * Test for {@see ADODConnection::metaTables()]
     * 
     * Checks that an exact table name that exists in the database 
     * returns an array with exactly one element
     * that element being the exact table name
     * 
     * @return void
     */
    public function testExactMatchMetaTables(): void
    {
        
        foreach ($this->testfetchModes as $fetchMode => $fetchModeName) {
            
            $this->db->setFetchMode($fetchMode);
            
            $executionResult = $this->db->metaTables(
                'T', 
                false, //$this->db->database, 
                $this->testTableName,
            );
            list($errno, $errmsg) = $this->assertADOdbError('metaTables()');
            
            $assertionResult = $this->assertIsArray(
                $executionResult,
                sprintf(
                    '[FETCH MODE %s] metaTables returns an array when exact ' . 
                    'table name that exists in the database is provided',
                    $fetchModeName
                )
            );

            if ($assertionResult) {
                $assertionResult = $this->assertEquals(
                    1,
                    count($executionResult),
                    sprintf(
                        '[FETCH MODE %s] metaTables should return an array ' . 
                        'with exactly one element when exact table name ' . 
                        'that exists in the database is provided',
                        $fetchModeName
                    )
                );
                if ($assertionResult) {
                    $this->assertSame(
                        strtoupper($this->testTableName),
                        strtoupper($executionResult[0]),
                        sprintf(
                            '[FETCH MODE %s] metaTables should return an array ' . 
                            'with the exact table name when exact table name ' . 
                            'that exists in the database is provided',
                            $fetchModeName
                        )
                    );
                }
            }
        }
    }
    
    /**
     * Test for {@see ADODConnection::metaColumnNames()]
     *
     * @dataProvider providerTestMetaColumnNames
     * 
     * @param bool  $returnType
     * @param array $expectedResult
     * 
     * @return void
     */
    public function testMetaColumnNames(bool $returnType, int $fetchMode, array $expectedResult): void
    {

      
        $this->db->setFetchMode($fetchMode);

        $executionResult = $this->db->metaColumnNames(
            $this->testTableName, 
            $returnType
        );
        list($errno, $errmsg) = $this->assertADOdbError('metaColumnNames()');

        $this->assertSame(
            $expectedResult, 
            $executionResult,
            sprintf(
                '[FETCH MODE: %s] Checking metaColumnNames with returnType %s',
                $this->testfetchModes[$fetchMode],
                $returnType ? 'true' : 'false'
            )
        );
    }
    
    /**
     * Data provider for {@see testMetaColumNames()}
     *
     * @return array [bool array type, array return value]
     */
    public function providerTestMetaColumnNames(): array
    {
        return array(
            'Returning Associative Array' => array(
                false,
                ADODB_FETCH_ASSOC,
                array ( 
                    'ID' => 'id',
                    'VARCHAR_FIELD' => 'varchar_field',
                    'DATETIME_FIELD' => 'datetime_field',
                    'DATE_FIELD' => 'date_field',
                    'INTEGER_FIELD' => 'integer_field',
                    'DECIMAL_FIELD' => 'decimal_field',
                    'BOOLEAN_FIELD' => 'boolean_field',
                    'EMPTY_FIELD' => 'empty_field',
                    'NUMBER_RUN_FIELD' => 'number_run_field'
                )
            ),
            
            'Returning Numeric Array' => array(
                true,
                ADODB_FETCH_NUM,
                array(
                    '0' => 'id',
                    '1' => 'varchar_field',
                    '2' => 'datetime_field',
                    '3' => 'date_field',
                    '4' => 'integer_field',
                    '5' => 'decimal_field',
                    '6' => 'boolean_field',
                    '7' => 'empty_field',
                    '8' => 'number_run_field'
                )
            )
        );
    }
    
    /**
     * Test 1 for {@see ADODConnection::metaColumns()]
     * Checks that there are right number of columns
     *
     * @return void
     */
    public function testMetaColumnCount(): void
    {
        $expectedResult  = 9;

        foreach ($this->testfetchModes as $fetchMode => $fetchModeName) {
            
            $this->db->setFetchMode($fetchMode);
     
        
            $metaTables = $this->db->metaTables('T', '', $this->testTableName);
            list($errno, $errmsg) = $this->assertADOdbError('metaTables()');
            
            if ($metaTables === false) {
                $this->fail(
                    sprintf(
                        '[FETCH MODE %s] metaTables did not return any table',
                        $fetchModeName
                    )
                );
                return;
            }
            
            $tableName = $metaTables[0];

            $executionResult = $this->db->metaColumns($tableName);
            list($errno, $errmsg) = $this->assertADOdbError('metaColumns()');
            $this->assertSame(
                $expectedResult, 
                count($executionResult),
                sprintf(
                    '[FETCH MODE %s] Checking Column Count, expected %d, got %d',
                    $fetchModeName,
                    $expectedResult,
                    count($executionResult)
                )
            );
        }
    }
    
    /**
     * Test 3 for {@see ADODConnection::metaColumns()]
     * Checks that every returned element is an ADOFieldObject
     *
     * @return void
     */
    public function testMetaColumnObjects(): void
    {

        foreach ($this->testfetchModes as $fetchMode => $fetchModeName) {
            
            $this->db->setFetchMode($fetchMode);
     
            $executionResult = $this->db->metaColumns($this->testTableName);
            list($errno, $errmsg) = $this->assertADOdbError('metaColumns()');
            if (empty($executionResult)) {
                $this->fail(
                    sprintf(
                        '[FETCH MODE %s] metaColumns returned an empty array',
                        $fetchModeName
                    )
                );
                return;
            }

            foreach ($executionResult as $column => $o) {
                $oType = get_class($o);
                $this->assertSame(
                    'ADOFieldObject', 
                    $oType,
                    sprintf(
                        '[FETCH MODE %s] metaColumns should return ' . 
                        'an ADOFieldObject object for column %s',
                        $fetchModeName,
                        $column
                    )
                );
            }
        }
        
    }
    
    /**
     * Test for {@see ADODConnection::metaColumns()]
     *
     * Checks that the returned columns match the expected ones
     */   
    public function testMetaColumns(): void
    {
        $expectedResult = [
            '0' => 'ID',
            '1' => 'VARCHAR_FIELD',
            '2' => 'DATETIME_FIELD',
            '3' => 'DATE_FIELD',
            '4' => 'INTEGER_FIELD',
            '5' => 'DECIMAL_FIELD',
            '6' => 'BOOLEAN_FIELD',
            '7' => 'EMPTY_FIELD'
        ];
        
        foreach ($this->testfetchModes as $fetchMode => $fetchModeName) {
            
            $this->db->setFetchMode($fetchMode);
                        
            $executionResult = $this->db->metaColumns($this->testTableName);
            list($errno, $errmsg) = $this->assertADOdbError('metaColumns()');
    
            foreach ($expectedResult as $expectedField) {
                
                $this->assertArrayHasKey(
                    $expectedField, 
                    $executionResult,
                    sprintf(
                        '[FETCH MODE %s] ' . 
                        'Checking for expected field %s in metaColumns return value',
                        $fetchModeName,
                        $expectedField
                    )
                );
                
                if (!isset($executionResult[$expectedField])) {
                    continue;
                }

            }
        }
    }
    
        
    /**
     * Test 1 for {@see ADODConnection::metaIndexes()]
     * Checks that the correct number of indexes is returned
     *
     * @return void
     */
    public function testMetaIndexCount(): void
    {

        foreach ($this->testfetchModes as $fetchMode => $fetchModeName) {
            
            $this->db->setFetchMode($fetchMode);
        
            $executionResult = $this->db->metaIndexes($this->testTableName);
            list($errno, $errmsg) = $this->assertADOdbError('metaIndexes()');
            
            $this->assertSame(
                3,
                count($executionResult),
                sprintf(
                    'FETCH MODE %s] Checking Index Count should be 3', 
                    $fetchModeName
                )
            );
        }
    }
    
    /**
     * Test 2 for {@see ADODConnection::metaIndexes()]
     * Checks that the correct unique indexes is returned
     * 
     * @dataProvider providerTestMetaIndexUniqueness
     * 
     * @param bool $result
     * @param string $indexName
     * 
     * @return void
     */
    public function testMetaIndexUniqueness($result,$indexName): void
    {
        foreach ($this->testfetchModes as $fetchMode => $fetchModeName) {
            
            $this->db->setFetchMode($fetchMode);
        
            $executionResult = $this->db->metaIndexes($this->testTableName);
            list($errno, $errmsg) = $this->assertADOdbError('metaIndexes()');
            
            $this->assertSame(
                $result, 
                ($executionResult[$indexName]['unique'] == 1),
                sprintf('[FETCH MODE %s] Checking Index Uniqueness',
                    $fetchModeName
                )
            );
        };
    }
    
    /**
     * Data provider for {@see testMetaColumns()}
     *
     * @return array [bool array type, array return value]
     */
    public function providerTestMetaIndexUniqueness(): array
    {
        return [
             'Index vdx1 is unique' => [true,'vdx1'],
             'Index vdx2 is unique' => [true,'vdx2'],
                ];
    }
    
    /**
     * Test for {@see ADODConnection::metaPrimaryKeys()]
     *
     * Checks that the correct primary key is returned
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:metaprimarykeys
     * 
     * @return void
     */
    public function testMetaPrimaryKeys(): void
    {
        foreach ($this->testfetchModes as $fetchMode => $fetchModeName) {
            
            $this->db->setFetchMode($fetchMode);
        
            $executionResult = $this->db->metaPrimaryKeys($this->testTableName);
            list($errno, $errmsg) = $this->assertADOdbError('metaPrimaryKeys()');

            $this->assertIsArray(
                $executionResult,
                sprintf(
                    '[FETCH MODE %s] metaPrimaryKeys should return an array',
                    $fetchModeName
                )
            );

            if (!is_array($executionResult)) {
                return;
            }

            $this->assertCount(
                1,
                $executionResult,
                sprintf(
                    '[FETCH MODE %s] Checking Primary Key Count should be 1',
                    $fetchModeName
                )
            );

            if (count($executionResult) != 1) {
                return;
            }

            $this->assertSame(
                'id',
                $executionResult[0],
                sprintf(
                    '[FETCH MODE %s] Validating the primary key is on column ID',
                    $fetchModeName
                )
            );
        }
       
    }

    /**
     * Test for {@see ADODConnection::metaForeignKeys()]
     * Checks that the correct list of foreigh keys is returned
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:metaforeignkeys
     * 
     * @return void
     */
    public function testMetaForeignKeys(): void
    {
        
        global $ADODB_FETCH_MODE;
        $originalFetchMode = $ADODB_FETCH_MODE;
        
        

        $this->db->setFetchMode(ADODB_FETCH_ASSOC);


        $testTable1 = 'testtable_1';
        $testTable2 = 'testtable_2';

            
        $executionResult = $this->db->metaForeignKeys($testTable2);
        list($errno, $errmsg) = $this->assertADOdbError('metaForeignKeys()');
        
        $this->db->setFetchMode($originalFetchMode);
        
        if ($executionResult == false) {
            $this->fail('With fetch mode set to ADODB_FETCH_ASSOC, metaForeignKeys did not return any foreign keys');
            return;
        }
       
        $executionResult = array_change_key_case($executionResult, CASE_UPPER);

        $fkTableNames = array_flip(
            array_change_key_case(
                array_keys($executionResult), 
                CASE_UPPER
            )
        );


       
        $fkTableExists = $this->assertArrayHasKey(
            strtoupper($testTable1), 
            $fkTableNames,
            'fetch mode ADODB_FETCH_ASSOC Checking for foreign key testtable_1 in testtable_2'
        );

        if (!$fkTableExists) {
            return;
        }

        $fkData = $executionResult[strtoupper($testTable1)];
                
        $this->assertArrayHasKey(
            'TT_ID', 
            $fkData,
            'With fetch mode ADODB_FETCH_ASSOC, Checking for foreign key field TT_ID in testtable_2 foreign key testtable_1'
        );
            
        $this->assertArrayHasKey(
            'INTEGER_FIELD', 
            $fkData,
            'With fetch mode ADODB_FETCH_ASSOC, Checking for foreign key field INTEGER_FIELD in testtable_2 foreign key testtable_1'
        );


        $this->db->setFetchMode(ADODB_FETCH_NUM);

        $executionResult = $this->db->metaForeignKeys($testTable2);
        list($errno, $errmsg) = $this->assertADOdbError('metaForeignKeys()');
        
        $this->db->setFetchMode($originalFetchMode);
        
        if ($executionResult == false) {
            $this->fail(
                'With fetch mode set to ADODB_FETCH_NUM, '. 
                ' metaForeignKeys did not return any foreign keys'
            );
            return;
        }

        $executionResult = array_change_key_case($executionResult, CASE_UPPER);

        $fkTableNames = array_flip(
            array_change_key_case(
                array_keys($executionResult), 
                CASE_UPPER
            )
        );

                
        $this->assertContains(
            'TT_ID=ID', 
            $fkData,
            'With fetch mode ADODB_FETCH_NUM, Checking for foreign key field TT_ID in testtable_2 foreign key testtable_1'
        );

        $this->assertContains(
            'INTEGER_FIELD=INTEGER_FIELD', 
            $fkData,
            'With fetch mode ADODB_FETCH_NUM Checking for foreign key field INTEGER_FIELD in testtable_2 foreign key testtable_1'
        );
   
        
    } 
    
    /**
     * Test for {@see ADODConnection::metaType()]
     * Checks that the correct metatype is returned
     * 
     * @param ?string $metaType
     * @param int $offset
     * 
     * @return void
     *
     * @dataProvider providerTestMetaTypes 
    */
    public function testMetaTypes(mixed $metaType,int $offset): void
    {
        $sql = 'SELECT * FROM ' . $this->testTableName;
        list ($executionResult, $errno, $errmsg) = $this->executeSqlString($sql);


        $metaResult = false;
        $metaFetch = $executionResult->fetchField($offset);

        if ($metaFetch != false) {
            $metaResult = $executionResult->metaType($metaFetch->type);
        }

        $this->assertSame(
            $metaType, 
            $metaResult,
            'Checking MetaType'
        );
    }
    
    /**
     * Data provider for {@see testMetaTypes()}
     *
     * @return array [string metatype, int offset]
     */
    public function providerTestMetaTypes(): array
    {

        /*
        CREATE TABLE testtable_1 (
        id INT NOT NULL AUTO_INCREMENT,
        varchar_field VARCHAR(20),
        datetime_field DATETIME,
        date_field DATE,
        integer_field INT(2) DEFAULT 0,
        decimal_field decimal(12.2) DEFAULT 1,2 
        */
        
        return [
            'Field 0 Is INTEGER' => ['I',0],
            'Field 1 Is VARCHAR' => ['C',1],
            'Field 2 Is DATETIME' => ['T',2],
            'Field 3 Is DATE' => ['D',3],
            'Field 4 Is INTEGER' => ['I',4],
            'Field 5 Is NUMBER' => ['N',5],
            'Field 6 Is BOOLEAN' => ['L',6],
            'Field 7 Is VARCHAR' => ['C',7],
            'Field 8 Is INTEGER' => ['I',8],
            'Field 9 Does not Exist' => [false,9],
    
             
        ];
    }
    
    /**
     * Test for {@see ADODConnection::serverInfo()]
     * Checks that version is returned
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:serverinfo
     * 
     * @return void
     */
    public function testServerInfoVersion(): void
    {
        
        foreach ($this->testfetchModes as $fetchMode => $fetchModeName) {
            $this->db->setFetchMode($fetchMode);
            
            $executionResult = $this->db->serverInfo();
            
            $this->assertIsArray(
                $executionResult,
                sprintf(
                    '[FETCH MODE %s] serverInfo should return an array',
                    $fetchModeName
                )
            );

            if (!is_array($executionResult)) {
                return;
            }
       
        
            $this->assertArrayHasKey(
                'version',
                $executionResult,
                sprintf(
                    '[FETCH MODE %s] Checking for mandatory key ' . 
                    '"version" in serverInfo',
                    $fetchModeName
                )
            );
            $this->assertArrayHasKey(
                'description',
                $executionResult,
                sprintf(
                    '[FETCH MODE %s] Checking for mandatory key ' . 
                    '"description" in serverInfo',
                    $fetchModeName
                )
            );
        }
    }
   
    /**
     * Test for errors when a meta function is called on an invalid table
     *
     * @return void
     */
    public function testMetaFunctionsForInvalidTable(): void
    {
        
      
        foreach ($this->testfetchModes as $fetchMode => $fetchModeName) {
            
            $this->db->setFetchMode($fetchMode);
        

            $response = $this->db->metaColumns('invalid_table');
            list($errno, $errmsg) = $this->assertADOdbError('metaColumns()');

            $this->assertTrue(
                $this->db->errorNo() > 0,
                sprintf(
                    '[FETCH MODE %s] Checking for error when querying metaColumns for an invalid table',
                    $fetchModeName
                )
            );

            $this->assertFalse(
                $response,
                sprintf(
                    '[FETCH MODE %s] Checking that metaColumns returns false for an invalid table',
                    $fetchModeName
                )
            );

            $response = $this->db->metaColumnNames('invalid_table');
            list($errno, $errmsg) = $this->assertADOdbError('metaColumnNames()');

            $this->assertTrue(
                $this->db->errorNo() > 0,
                sprintf(
                    '[FETCH MODE %s] Checking for error when querying metaColumnNames for an invalid table',
                    $fetchModeName
                )
            );

            $this->assertFalse(
                $response,
                sprintf(
                    '[FETCH MODE %s] Checking that metaColumnNames returns false for an invalid table',
                    $fetchModeName
                )
            );

            $response = $this->db->metaIndexes('invalid_table');
            list($errno, $errmsg) = $this->assertADOdbError('metaIndexes()');
            $this->assertTrue(
                $this->db->errorNo() > 0,
                sprintf(
                    '[FETCH MODE %s] Checking for error when querying metaIndexes for an invalid table',
                    $fetchModeName
                )
            );
            $this->assertFalse(
                $response,
                sprintf(
                    '[FETCH MODE %s] Checking that metaIndexes returns false for an invalid table',
                    $fetchModeName
                )
            );
            $response = $this->db->metaPrimaryKeys('invalid_table');
            list($errno, $errmsg) = $this->assertADOdbError('metaPrimaryKeys()');
            $this->assertTrue(
                $this->db->errorNo() > 0,
                sprintf(
                    '[FETCH MODE %s] Checking for error when querying metaPrimaryKeys for an invalid table',
                    $fetchModeName
                )
            );
            $this->assertFalse(
                $response,
                sprintf(
                    '[FETCH MODE %s] Checking that metaPrimaryKeys returns false for an invalid table',
                    $fetchModeName
                )
            );
            $response = $this->db->metaForeignKeys('invalid_table');
            list($errno, $errmsg) = $this->assertADOdbError('metaForeignKeys()');
            $this->assertTrue(
                $this->db->errorNo() > 0,
                sprintf(
                    '[FETCH MODE %s] Checking for error when querying metaForeignKeys for an invalid table',
                    $fetchModeName
                )
            );
            $this->assertFalse(
                $response,
                sprintf(
                    '[FETCH MODE %s] Checking that metaForeignKeys ' . 
                    'returns false for an invalid table',
                    $fetchModeName
                )
            );
        }
    }
    
}