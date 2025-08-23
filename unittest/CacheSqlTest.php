<?php
/**
 * Tests cases for cache SQL functions of ADODb
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @author Mark Newnham
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
class CacheSqlTest extends TestCase
{
    protected $db;
    protected $adoDriver;
    protected $skipAllTests = false;
    protected $cacheMethod = 0;
    protected $timeout = 120;
    
    /**
     * Set up the test environment
     *
     * @return void
     */
    public static function setupBeforeClass(): void
    {
        $db        = &$GLOBALS['ADOdbConnection'];
        
        if (!isset($GLOBALS['TestingControl']['caching'])) {
             return;
        } 

        $db->startTrans();
        $SQL = "SELECT COUNT(*) AS cache_table3_count FROM testtable_3";
        $table3DataExists = $db->getOne($SQL);
        
        $db->completeTrans();
        if ($table3DataExists) {
            // Data already exists, no need to reload
            return;
        }
        
            

        /*
        *load Data into the table
        */
      
        $db->startTrans();

        $table3Data = sprintf('%s/DatabaseSetup/table3-data.sql', dirname(__FILE__));
        $table3Sql = file_get_contents($table3Data);
        $t3Sql = explode(';', $table3Sql);
        foreach ($t3Sql as $sql) {
            if (trim($sql ?? '')) {
                $db->execute($sql);
            }
        }

        $db->completeTrans();
       
    }

    /**
     * Set up the test environment before each test
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->db        = &$GLOBALS['ADOdbConnection'];
        $this->adoDriver = $GLOBALS['ADOdriver'];
        
        $cacheParams = $GLOBALS['TestingControl']['caching'];
        
        $cacheMethod = $cacheParams['cacheMethod'];
        
        if ($cacheMethod == 0) {
            
            $this->skipAllTests = true;
            return;
        }


        if ($this->skipAllTests) {
            $this->markTestSkipped('Skipping tests as caching not configured');
        }

         $this->db->cacheFlush();
    }
   
    /**
     * Set the empty_field column to a value
     *
     * @param string $value Value to set the empty_field column to
     * 
     * @return void
     */
    public function setEmptyColumn($value): void
    {

        if (!$value) {
            $value = 'NULL';
        } else {
            $value = $this->db->qstr($value);
        }

        $sql = "UPDATE testtable_3 SET empty_field = $value";
        $this->db->execute($sql);
    }

    /**
     * Changes the casing of the keys in an associative array
     * based on the value of ADODB_ASSOC_CASE
     *
     * @param array $input  by reference
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
     * Test for {@see ADODConnection::execute() in select mode]
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:cacheexecute
     *
     * @dataProvider providerTestSelectCacheExecute
     * 
     * @param bool $expectedValue Expected value of the result
     * @param string $sql SQL query to execute
     * @param ?array $bind Optional array of bind parameters
     * 
     * @return void
     */
    public function testSelectCacheExecute(bool $expectedValue, string $sql, ?array $bind): void
    {
        
        if ($this->skipAllTests) {
            
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }

        if ($bind) {
            $result = $this->db->cacheExecute($this->timeout, $sql, $bind);
        } else {
            $result = $this->db->cacheExecute($this->timeout, $sql);
        }
        
        $this->assertSame(
            $expectedValue, 
            is_object($result), 
            'First access of cacheExecute in SELECT mode sets cache'
        );

        if ($bind) {
            $result = $this->db->cacheExecute($this->timeout, $sql, $bind);
        } else {
            $result = $this->db->cacheExecute($this->timeout, $sql);
        }
        
        $this->assertSame(
            $expectedValue, 
            is_object($result), 
            'Second access of cacheexecute() in SELECT mode should read object from cache, not database'
        );
            
    }
    
    /**
     * Data provider for {@see testSelectExecute()}
     *
     * @return array [string(getRe, array return value]
     */
    public function providerTestSelectCacheExecute(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $bind = array('p1'=>1);
        return [
            'Select Unbound' => 
                [true, "SELECT * FROM testtable_3 ORDER BY number_run_field", null],
            'Invalid' => 
                [false, "SELECT testtable_3.varchar_fieldx 
                           FROM testtable_3 
                       ORDER BY number_run_field", 
                       null],
            'Select, Bound' => 
                [true, "SELECT testtable_3.varchar_field,testtable_3.* 
                         FROM testtable_3 
                        WHERE number_run_field=$p1", $bind],

            ];
    }
    
    /**
     * Test for {@see ADODConnection::cacheexecute() in non-seelct mode]
     * 
     * @dataProvider providerTestNonSelectCacheExecute
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:cacheexecute
     * 
     * @param bool $expectedValue Expected value of the result
     * @param string $sql SQL query to execute
     * @param ?array $bind Optional array of bind parameters
     * 
     * @return void
     */
    public function testNonSelectCacheExecute(bool $expectedValue, string $sql, ?array $bind): void
    {
    
        global $ADODB_CACHE_DIR;
        if ($this->skipAllTests) {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }

        if ($bind) {
            $result = $this->db->cacheExecute($this->timeout, $sql, $bind);
        } else {
            $result = $this->db->cacheExecute($this->timeout, $sql);
        }
        
        $this->assertSame(
            $expectedValue, 
            is_object($result) && get_class($result) == 'ADORecordSet_empty', 
            'ADOConnection::execute() in INSERT/UPDATE/DELETE mode returns ADORecordSet_empty'
        );
            
    }
    
    /**
     * Data provider for {@see testNonSelectExecute()}
     *
     * @return array [string(getRe, array return value]
     */
    public function providerTestNonSelectCacheExecute(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $bind = array('p1'=>'LINE 1');
        return [
             'Update Unbound' => [
                true, 
                "UPDATE testtable_3 SET integer_field=2000 WHERE id=1", 
                null
            ],
              'Invalid' => [
                false, 
                "UPDATE testtable_3 SET xinteger_field=2000 WHERE id=1",
                 null
            ],
              'Select, Bound' =>  [
                true, 
                "UPDATE testtable_3 SET integer_field=2000 WHERE varchar_field=$p1",
                 $bind
            ],
        ];
    }
    
    
    /**
     * Test for {@see ADODConnection::cacheGetOne()]
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:cachegetone
     * 
     * @param string $expectedValue Expected value of the result
     * @param string $sql SQL query to execute
     * @param ?array $bind Optional array of bind parameters
     * 
     * @return void
     * 
     * @dataProvider providerTestCacheGetOne
     */
    public function testCacheGetOne(string $expectedValue, string $sql, ?array $bind): void
    {
        global $ADODB_CACHE_DIR;
        if ($this->skipAllTests) { 
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }
        if ($bind) {
            $actualValue = $this->db->cacheGetOne($this->timeout, $sql, $bind);

            $this->assertSame(
                $expectedValue, 
                $actualValue, 
                'First access of cacheGetOne() with bind reads from database and sets cache'
            );
        } else {
            
            $actualValue = $this->db->cacheGetOne($this->timeout, $sql);
            
            $this->assertSame(
                $expectedValue, 
                $actualValue, 
                'First access of cacheGetOne() reads from database and sets cache'
            );
        }

        $rewriteSql = "UPDATE testtable_3 
                          SET varchar_field = 'NOCACHE VALUE1' 
                        WHERE varchar_field = 'LINE 1'";

        $this->db->execute($rewriteSql);

        if ($bind) {
            $actualValue = $this->db->cacheGetOne($this->timeout, $sql, $bind);
            $this->assertSame(
                $expectedValue, 
                $actualValue,
                'Second access of cacheGetOne() with bind reads from cache, not database'
            );
        } else {
            $actualValue = $this->db->cacheGetOne($this->timeout, $sql);
            $this->assertSame(
                $expectedValue, 
                $actualValue,
                'Second access of cacheGetOne() reads from cache, not database'
            );
        }
        $rewriteSql = "UPDATE testtable_3 
                          SET varchar_field = 'LINE 1' 
                        WHERE varchar_field = 'NOCACHE VALUE1'";
        $this->db->execute($rewriteSql);
    }

    /**
     * Data provider for {@see testGetOne()}
     *
     * @return array [string(getRe, array return value]
     */
    public function providerTestCacheGetOne(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $bind = array('p1'=>'LINE 11');

        return [
            'Return Last Col, Unbound' => [
                'LINE 11', 
                "SELECT varchar_field FROM testtable_3 ORDER BY number_run_field DESC", 
                null
            ],
            'Return Multiple Cols, take first, Unbound' => [
                'LINE 11', 
                "SELECT testtable_3.varchar_field,testtable_3.* FROM testtable_3 ORDER BY number_run_field DESC",
                null
            ],
            'Return Multiple Cols, take first, Bound' => [
                'LINE 11', 
                "SELECT testtable_3.varchar_field,testtable_3.* FROM testtable_3 WHERE varchar_field=$p1", 
                $bind
            ],

        ];
    }
    
    /**
     * Test for {@see ADODConnection::cachegetCol()]
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:cachegetcol
     * 
     * @param int $expectedValue Expected value of the result
     * @param string $sql SQL query to execute
     * @param ?array $bind Optional array of bind parameters
     * 
     * @return void
     *
     * @dataProvider providerTestCacheGetCol
    */
    public function testGetCacheCol(int $expectedValue, string $sql, ?array $bind): void
    {
        global $ADODB_CACHE_DIR;
        if ($this->skipAllTests) {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }
        if ($bind) {
            $cols = $this->db->cacheGetCol($sql, $bind);
            $this->assertSame(
                $expectedValue, 
                count($cols),
                'First access of cacheGetCol with bound variables() sets cache'
            );
        } else {
            $cols = $this->db->cacheGetCol($sql);
            $this->assertSame(
                $expectedValue, 
                count($cols),
                'First access of cacheGetCol without bound variables() sets cache'
            );
    
        }

        $rewriteSql = "UPDATE testtable_3 SET varchar_field = null WHERE varchar_field = 'LINE 1'";
        $this->db->execute($rewriteSql);

        if ($bind) {
            $cols = $this->db->cacheGetCol($sql, $bind);
            $this->assertSame(
                $expectedValue, 
                count($cols),
                'Second access of cacheGetCol with bound variables() should read cache, not database'
            );
        } else {
            $cols = $this->db->cacheGetCol($sql);
            $this->assertSame(
                $expectedValue, 
                count($cols), 
                'Second access of cacheGetCol without bound variables() should read cache not database'
            );
    
        }
        $rewriteSql = "UPDATE testtable_3 
                          SET varchar_field = 'LINE 1' 
                        WHERE varchar_field = NULL";

        $this->db->execute($rewriteSql);

    }
    /**
     * Data provider for {@see testCacheGetCol()}
     *
     * @return array [string(getRe, array return value]
     */
    public function providerTestCacheGetCol(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $bind = array('p1'=>'LINE 11');
        return [
                [
                    11, 
                    "SELECT varchar_field FROM testtable_3", 
                    null
                ],[
                    1, 
                    "SELECT testtable_3.varchar_field,testtable_3.* FROM testtable_3 WHERE varchar_field=$p1", 
                    $bind
                ],

            ];
    }
    
    /**
     * Test for {@see ADODConnection::cachegetRow()]
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:cachegetrow
     * 
     * @param int $expectedValue Expected value of the result
     * @param string $sql SQL query to execute
     * @param ?array $bind Optional array of bind parameters
     * 
     * @return void
     *
     * @dataProvider providerTestCacheGetRow
     */
    public function testCacheGetRow(int $expectedValue, string $emptyColumn, string $sql, ?array $bind): void
    {
        global $ADODB_CACHE_DIR;

        if ($this->skipAllTests) {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }

        /*
        * Set a value to cache
        */
        $this->setEmptyColumn('80111');
    
        if (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_UPPER) {

            $fields = [ 
                '0' => 'ID',
                '1' => 'VARCHAR_FIELD',
                '2' => 'DATETIME_FIELD',
                '3' => 'DATE_FIELD',
                '4' => 'INTEGER_FIELD',
                '5' => 'DECIMAL_FIELD',
                '6' => 'BOOLEAN_FIELD',            
                '7' => 'EMPTY_FIELD',
                '8' => 'NUMBER_RUN_FIELD'
            ];

        } else {
            $fields = [ 
                'id',
                'varchar_field',
                'datetime_field',
                'date_field',
                'integer_field',
                'decimal_field',
                'boolean_field',            
                'empty_field',
                'number_run_field'
            ];
        }

                
        if ($bind != null) {

            $this->db->setFetchMode(ADODB_FETCH_ASSOC);
    
            $record = $this->db->cacheGetRow($this->timeout, $sql, $bind);

            foreach ($fields as $key => $value) {
                $this->assertArrayHasKey(
                    $value, 
                    $record, 
                    'Checking if associative key exists in returned record'
                );
            }
         } else {
            $this->db->setFetchMode(ADODB_FETCH_NUM);
            $record = $this->db->cacheGetRow($this->timeout, $sql);
            
            foreach ($fields as $key => $value) {
                $this->assertArrayHasKey(
                    $key, 
                    $record, 
                    'Checking if numeric key exists in fields array'
                );
            }
        }

        /*
        * Now update the empty_field column
        */
        $this->setEmptyColumn(null);

        /*
        * Reread the cached row
        * and check that the empty_field column is 80111
        */
        if ($bind != null) {
            $this->db->setFetchMode(ADODB_FETCH_ASSOC);
    
            $record = $this->db->cacheGetRow($this->timeout, $sql, $bind);
            foreach ($fields as $key => $value) {
                $this->assertArrayHasKey(
                    $value, 
                    $record, 
                    'Checking if associative key exists in returned record'
                );
            }

            $this->assertSame(
                '80111', 
                $record[$emptyColumn], 
                'Checking that empty_field column is read from cache as 80111'
            );
        
        } else {
            
            $this->db->setFetchMode(ADODB_FETCH_NUM);
            $record = $this->db->cacheGetRow($this->timeout, $sql);
            
            foreach ($fields as $key => $value) {
                $this->assertArrayHasKey(
                    $key, 
                    $record, 
                    'Checking if numeric key exists in fields array'
                );
            }
           
            $this->assertSame(
                '80111', 
                $record[7], 
                'Checking that empty_field column is read from cache as 80111'
            );
        }
        $this->skipAllTests = true;

    }
    
    /**
     * Data provider for {@see testCacheGetRow()}
     *
     * @return array [int success, string sql, ?array bind]
     */
    public function providerTestCacheGetRow(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $bind = array(
            'p1'=>'LINE 11'
        );

        switch (ADODB_ASSOC_CASE) {
            case ADODB_ASSOC_CASE_UPPER:
                $firstColumn = 'EMPTY_FIELD';
            
                break;
            case ADODB_ASSOC_CASE_LOWER:
            default:
                $firstColumn = 'empty_field';
              
                break;
           
        }
        return [
                [1, $firstColumn, "SELECT * FROM testtable_3 ORDER BY number_run_field DESC", null],
                [1, $firstColumn, "SELECT * FROM testtable_3 WHERE varchar_field=$p1", $bind],
            ];
    }

    /**
     * Test for {@see ADODConnection::cachegetAll()}
     *
     *  @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:cachegetall
     *
     * @param int $fetchMode Fetch mode to use
     * @param array $expectedValue Expected value of the result
     * @param string $sql SQL query to execute
     * @param ?array $bind Optional array of bind parameters
     * 
     * @return void
     * 
     * @dataProvider providerTestCacheGetAll
     */
    public function testCacheGetAll(int $fetchMode,array $expectedValue, string $sql, ?array $bind): void
    {
        

        if ($this->skipAllTests) {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }
        
        $this->db->setFetchMode($fetchMode);

        if ($bind) {
            $returnedRows = $this->db->cacheGetAll($this->timeout, $sql, $bind);
        } else {
            $returnedRows = $this->db->cacheGetAll($this->timeout, $sql);
        }
         
        //foreach ($expectedValue as $eIndex => $eRow) {
         //   $this->changeKeyCasing($expectedValue[$eIndex]);
        //}


        $this->assertSame(
            $expectedValue,
            $returnedRows, 
            'Initial read of cacheGetAll()'
        );

        /*
        * This changes the value of the varchar_field in the database
        * but the cache should still return the original value
        */
        $rewriteSql = "UPDATE testtable_3 
                          SET varchar_field = 'SOME OTHER VALUE'
                        WHERE number_run_field = 3";
        $this->db->execute($rewriteSql);

        if ($bind) {
            $returnedRows = $this->db->cacheGetAll($this->timeout, $sql, $bind);
        } else {
            $returnedRows = $this->db->cacheGetAll($this->timeout, $sql);
        }
        
        $this->assertSame(
            $expectedValue, 
            $returnedRows, 
            'Second read of cacheGetAll should return cache not current()'
        );

         $rewriteSql = "UPDATE testtable_3 
                          SET varchar_field = 'LINE 3' 
                        WHERE number_run_field = 3";
        $this->db->execute($rewriteSql);


    }
    
    /**
     * Data provider for {@see testGetAll()}
     *
     * @return array [int fetchode, array return value, string sql, ?array bind]
     */
    public function providerTestCacheGetAll(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $p2 = $GLOBALS['ADOdbConnection']->param('p2');
        $bind = array('p1'=>2,
                      'p2'=>6
                    );
        return [
            'Numbers Between 2 and 6,Unbound, FETCH_ASSOC' => 
                [ADODB_FETCH_ASSOC, 
                    array(
                        array('VARCHAR_FIELD'=>'LINE 2'),
                        array('VARCHAR_FIELD'=>'LINE 3'),
                        array('VARCHAR_FIELD'=>'LINE 4'),
                        array('VARCHAR_FIELD'=>'LINE 5'),
                        array('VARCHAR_FIELD'=>'LINE 6')
                    ),
                     "SELECT testtable_3.varchar_field 
                        FROM testtable_3 
                       WHERE number_run_field BETWEEN 2 AND 6
                    ORDER BY number_run_field", null],
            'Bound, FETCH_NUM' => 
                [ADODB_FETCH_NUM, 
                    array(
                        array('0'=>'LINE 2'),
                        array('0'=>'LINE 3'),
                        array('0'=>'LINE 4'),
                        array('0'=>'LINE 5'),
                        array('0'=>'LINE 6')
                        ),
                    "SELECT testtable_3.varchar_field 
                       FROM testtable_3
                        WHERE number_run_field BETWEEN $p1 AND $p2 
                     ORDER BY number_run_field", $bind],

                ];
    }


    /**
     * Test for {@see ADODConnection::cacheselectlimit() in select mode]
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:cacheselectlimit
     * 
     * @param int $fetchMode Fetch mode to use
     * @param array $expectedValue Expected value of the result
     * @param string $sql SQL query to execute
     * @param int $rows Number of rows to return
     * @param int $offset Offset to start returning rows from
     * @param ?array $bind Optional array of bind parameters
     * 
     * @return void
     * 
     * @dataProvider providerTestCacheSelectLimit
     */
    public function testCacheSelectLimit(int $fetchMode,array $expectedValue, string $sql, int $rows, int $offset, ?array $bind): void
    {
        global $ADODB_CACHE_DIR;
        if ($this->skipAllTests) {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }

        $this->db->setFetchMode($fetchMode);

        $this->db->startTrans();

        if ($bind) {
            $result = $this->db->cacheSelectLimit(
                $this->timeout, 
                $sql, 
                $rows, 
                $offset, 
                $bind
            );
        } else {
            $result = $this->db->cacheSelectLimit(
                $this->timeout, 
                $sql, 
                $rows, 
                $offset
            );
        }

        $returnedRows = array();
        while ($row = $result->fetchRow()) {
            $returnedRows[] = $row;

        }
    
        $this->db->completeTrans();
        $this->db->startTrans();
        $this->assertSame(
            $expectedValue, 
            $returnedRows, 
            'First read of cacheSelectLimit(), builds cache'
        );
            
        $rewriteSql = "UPDATE testtable_3 
                          SET varchar_field = 'TCSL TEST VALUE' 
                        WHERE number_run_field = 3
                          AND varchar_field = 'LINE 3'";
        $this->db->execute($rewriteSql);

         $this->db->completeTrans();
        $this->db->startTrans();

        if ($bind) {
            $result = $this->db->cacheSelectLimit(
                $this->timeout,
                $sql, 
                $rows, 
                $offset, 
                $bind
            );
        } else {
            $result = $this->db->cacheSelectLimit(
                $this->timeout, 
                $sql,
                $rows, 
                $offset
            );
        }

        $returnedRows = array();
        while ($row = $result->fetchRow()) {
            $returnedRows[] = $row;

        }

         $this->db->completeTrans();
        $this->db->startTrans();
    
        $this->assertSame(
            $expectedValue, 
            $returnedRows, 
            'Second read of cacheSelectLimit(), should re-read cache, not database'
        );

        /*
        * Now rewrite the database back to its original state
        */
        $rewriteSql = "UPDATE testtable_3 
                          SET varchar_field = 'LINE 3' 
                        WHERE number_run_field = 3
                          AND varchar_field = 'TCSL TEST VALUE'";

        $this->db->execute($rewriteSql);
    
         $this->db->completeTrans();
        
    }
    
    /**
     * Data provider for {@see testSelectLimit()}
     *
     * @return array [int $fetchMode, array $result, string $sql, int $offset, int $rows, ?array $bind]
     */
    public function providerTestCacheSelectLimit(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        
        $bind = array(
            'p1'=>'2'
        );

        return [
            'Select Unbound, FETCH_ASSOC' => 
                [ADODB_FETCH_ASSOC, 
                    array(
                        array('VARCHAR_FIELD'=>'LINE 5'),
                        array('VARCHAR_FIELD'=>'LINE 6'),
                        array('VARCHAR_FIELD'=>'LINE 7'),
                        array('VARCHAR_FIELD'=>'LINE 8')
                    ),
                    "SELECT testtable_3.varchar_field 
                        FROM testtable_3 
                       WHERE number_run_field>2 
                    ORDER BY number_run_field",
                    4,
                    2,
                    null
                ],
            'Select, Bound, FETCH_NUM' => [
                ADODB_FETCH_NUM, 
                array(
                    array('0'=>'LINE 5'),
                    array('0'=>'LINE 6'),
                    array('0'=>'LINE 7'),
                    array('0'=>'LINE 8')
                    ),
                "SELECT testtable_3.varchar_field 
                   FROM testtable_3 
                  WHERE number_run_field>$p1 
               ORDER BY number_run_field", 
                4,
                2,
                $bind
            ],

        ];
    }
}