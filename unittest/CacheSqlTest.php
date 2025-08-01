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

        return; 
        /*
        * Refresh the data set
        */
        $db->Execute("DELETE FROM testtable_1");       

        /*
        *reload Data into the table
        */
        $db->startTrans();

        $table1Data = sprintf('%s/DatabaseSetup/table1-data.sql', dirname(__FILE__));
        $table1Sql = file_get_contents($table1Data);
        $t1Sql = explode(';', $table1Sql);
        foreach ($t1Sql as $sql) {
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

        $sql = "UPDATE testtable_1 SET empty_field = $value";
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
        $bind = array('p1'=>'LINE 1');
        return [
             'Select Unbound' => 
                [true, "SELECT * FROM testtable_1 ORDER BY id", null],
         'Invalid' => 
                [false, "SELECT testtable_1.varchar_fieldx FROM testtable_1 ORDER BY id", null],
         'Select, Bound' => 
                [true, "SELECT testtable_1.varchar_field,testtable_1.* FROM testtable_1 WHERE varchar_field=$p1", $bind],

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
                "UPDATE testtable_1 SET integer_field=2000 WHERE id=1", 
                null
            ],
              'Invalid' => [
                false, 
                "UPDATE testtable_1 SET xinteger_field=2000 WHERE id=1",
                 null
            ],
              'Select, Bound' =>  [
                true, 
                "UPDATE testtable_1 SET integer_field=2000 WHERE varchar_field=$p1",
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

        $rewriteSql = "UPDATE testtable_1 
                          SET varchar_field = null 
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
        $rewriteSql = "UPDATE testtable_1 SET varchar_field = 'LINE 1' WHERE varchar_field IS NULL";
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
                "SELECT varchar_field FROM testtable_1 ORDER BY id DESC", 
                null
            ],
            'Return Multiple Cols, take first, Unbound' => [
                'LINE 11', 
                "SELECT testtable_1.varchar_field,testtable_1.* FROM testtable_1 ORDER BY id DESC",
                null
            ],
            'Return Multiple Cols, take first, Bound' => [
                'LINE 11', 
                "SELECT testtable_1.varchar_field,testtable_1.* FROM testtable_1 WHERE varchar_field=$p1", 
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

        $rewriteSql = "UPDATE testtable_1 SET varchar_field = null WHERE varchar_field = 'LINE 1'";
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
        $rewriteSql = "UPDATE testtable_1 
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
                    "SELECT varchar_field FROM testtable_1", 
                    null
                ],[
                    1, 
                    "SELECT testtable_1.varchar_field,testtable_1.* FROM testtable_1 WHERE varchar_field=$p1", 
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
    public function testCacheGetRow(int $expectedValue, string $sql, ?array $bind): void
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
    

        $fields = [ 
            '0' => 'id',
            '1' => 'varchar_field',
            '2' => 'datetime_field',
            '3' => 'date_field',
            '4' => 'integer_field',
            '5' => 'decimal_field',
            '6' => 'boolean_field',            
            '7' => 'empty_field'
        ];

        $fields = array_flip($fields);

        $this->changeKeyCasing($fields);

        $fields = array_flip($fields);


        
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
                $record['empty_field'], 
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

        return [
                [1, "SELECT * FROM testtable_1 ORDER BY id DESC", null],
                [1, "SELECT * FROM testtable_1 WHERE varchar_field=$p1", $bind],
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
         
        foreach ($expectedValue as $eIndex => $eRow) {
            $this->changeKeyCasing($expectedValue[$eIndex]);
        }


        $this->assertSame(
            $expectedValue,
            $returnedRows, 
            'Initial read of cacheGetAll()'
        );

        $rewriteSql = "UPDATE testtable_1 
                          SET varchar_field = null 
                        WHERE varchar_field = 'LINE 3'";
        $this->db->execute($rewriteSql);

        if ($bind) {
            $returnedRows = $this->db->cacheGetAll($this->timeout, $sql, $bind);
        } else {
            $returnedRows = $this->db->cacheGetAll($this->timeout, $sql);
        }
        
        $this->assertSame(
            $this->changeKeyCasing($expectedValue), 
            $returnedRows, 
            'Second read of cacheGetAll should return cache not current()'
        );

        $sql = "UPDATE testtable_1 SET varchar_field = 'LINE 3' WHERE varchar_field IS NULL";
        $this->db->execute($sql);

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
        $bind = array('p1'=>'LINE 2',
                      'p2'=>'LINE 6'
                    );
        return [
            'Unbound, FETCH_ASSOC' => 
                [ADODB_FETCH_ASSOC, 
                    array(
                        array('varchar_field'=>'LINE 3'),
                        array('varchar_field'=>'LINE 4'),
                        array('varchar_field'=>'LINE 5'),
                        array('varchar_field'=>'LINE 6')
                    ),
                     "SELECT testtable_1.varchar_field 
                        FROM testtable_1 
                       WHERE varchar_field BETWEEN 'LINE 2' AND 'LINE 6'
                    ORDER BY varchar_field", null],
            'Bound, FETCH_NUM' => 
                [ADODB_FETCH_NUM, 
                    array(
                        array('0'=>'LINE 3'),
                        array('0'=>'LINE 4'),
                        array('0'=>'LINE 5'),
                        array('0'=>'LINE 6')
                        ),
                    "SELECT testtable_1.varchar_field 
                       FROM testtable_1 
                      WHERE varchar_field BETWEEN $p1 AND $p2
                   ORDER BY varchar_field", $bind],

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
    
        $this->assertSame(
            $this->changeKeyCasing($expectedValue), 
            $returnedRows, 
            'First read of cacheSelectLimit(), builds cache'
        );
            
        $rewriteSql = "UPDATE testtable_1 SET varchar_field = null WHERE varchar_field = 'LINE 3'";
        $this->db->execute($rewriteSql);

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
    
        $this->assertSame(
            $this->changeKeyCasing($expectedValue), 
            $returnedRows, 
            'Second read of cacheSelectLimit(), should re-read cache, not database'
        );

        $rewriteSql = "UPDATE testtable_1 
                          SET varchar_field = 'LINE 3' 
                        WHERE varchar_field IS NULL";

        $this->db->execute($rewriteSql);
    
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
            'p1'=>'LINE 2'
        );

        return [
            'Select Unbound, FETCH_ASSOC' => 
                [ADODB_FETCH_ASSOC, 
                    array(
                        array('varchar_field'=>'LINE 5'),
                        array('varchar_field'=>'LINE 6'),
                        array('varchar_field'=>'LINE 7'),
                        array('varchar_field'=>'LINE 8')
                    ),
                    "SELECT testtable_1.varchar_field 
                        FROM testtable_1 
                       WHERE varchar_field>'LINE 2' 
                    ORDER BY varchar_field, id",
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
                "SELECT testtable_1.varchar_field 
                   FROM testtable_1 
                  WHERE varchar_field>$p1 
               ORDER BY varchar_field,id", 
                4,
                2,
                $bind
            ],

        ];
    }
}