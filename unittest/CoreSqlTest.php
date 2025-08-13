<?php
/**
 * Tests cases for core SQL functions of ADODb
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
class CoreSqlTest extends TestCase
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
     * Set up the test environment first time
     *
     * @return void
     */
    public static function setupBeforeClass(): void
    {
        $db        = $GLOBALS['ADOdbConnection'];
        $adoDriver = $GLOBALS['ADOdriver'];

        $db->startTrans();
       
        $SQL = "SELECT COUNT(*) AS core_table3_count FROM testtable_3";
        $table3DataExists = $db->getOne($SQL);

        $db->completeTrans();

        if ($table3DataExists) {
            print "$table3DataExists records already exists in testtable_3, skipping data load.\n";
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
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:execute
     * 
     * @param bool $expectedValue
     * @param string $sql
     * @param ?array $bind
     * 
     * @return void
     * 
     * @dataProvider providerTestSelectExecute
     */
    public function testSelectExecute(bool $expectedValue, string $sql, ?array $bind): void
    {

        $this->db->startTrans();
        if ($bind) {
            $result = $this->db->execute($sql, $bind);
        } else {
            $result = $this->db->execute($sql);
        }

        $this->db->completeTrans();

        $this->assertSame(
            $expectedValue, 
            is_object($result), 
            'ADOConnection::execute() in SELECT mode'
        );
            
    }
    
    /**
     * Data provider for {@see testSelectExecute()}
     *
     * @return array [bool success, string sql ?array bind]
     */
    public function providerTestSelectExecute(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $bind = array('p1'=>'LINE 1');
        return [
            'Select Unbound' => [
                true, 
                "SELECT * FROM testtable_3 ORDER BY id", 
                null
            ],
            /*
            'Invalid' => [
                false, 
                "SELECT testtable_3.varchar_fieldx FROM testtable_3 ORDER BY id", 
                null
            ],
            */
            'Select, Bound' => [
                true, 
                "SELECT testtable_3.varchar_field,testtable_3.* FROM testtable_3 WHERE varchar_field=$p1", 
                $bind
            ],
        ];
    }
    
    /**
     * Test for {@see ADODConnection::execute() in non-seelct mode]
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:execute
     *
     * @dataProvider providerTestNonSelectExecute
     * 
     * @param bool $expectedValue
     * @param string $sql
     * @param ?array $bind
     * 
     * @return void
     */
    public function testNonSelectExecute(bool $expectedValue, string $sql, ?array $bind): void
    {

        $this->db->startTrans();
        if ($bind) {
            $result = $this->db->execute($sql, $bind);
        } else {
            $result = $this->db->execute($sql);
        }

        $this->db->completeTrans();
        
        $this->assertSame(
            $expectedValue, 
            is_object($result) && get_class($result) == 'ADORecordSet_empty', 
            'ADOConnection::execute() in INSERT/UPDATE/DELETE mode returns ADORecordSet_empty'
        );
            
    }
    
    /**
     * Data provider for {@see testNonSelectExecute()}
     *
     * @return array [string success, string sql, ?array bind]
     */
    public function providerTestNonSelectExecute(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $bind = array('p1'=>'LINE 1');
        return [
            'Update Unbound' => [
                true, 
                "UPDATE testtable_3 SET integer_field=2000 WHERE id=1",
                null
            ],
            /*
            'Invalid' => [
                false, 
                "UPDATE testtable_3 SET xinteger_field=2000 WHERE id=1", 
                null
            ],
            */
            'Select, Bound' => [
                true, 
                "UPDATE testtable_3 SET integer_field=2000 WHERE varchar_field=$p1", 
                $bind
            ],
        ];
    }
    
    /**
     * Test for {@see ADODConnection::getOne()]
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:getone
     *
     * @param string $expectedValue The expected value to be returned
     * @param string $sql The SQL query to execute
     * @param ?array $bind An optional array of bind parameters
     * 
     * @return void
     * 
     * @dataProvider providerTestGetOne
     */
    public function testGetOne(string $expectedValue, string $sql, ?array $bind): void
    {
        $this->db->startTrans();
        if ($bind) {
            $actualValue = (string)$this->db->getOne($sql, $bind);
            $this->assertSame(
                $expectedValue, 
                $actualValue,
                'Test of getOne with bind variables'
            );
        } else {
           
            $actualValue = (string)$this->db->getOne($sql);

            $this->assertSame(
                $expectedValue, 
                $actualValue,
                'Test of getOne without bind variables'
            );
        }
        $this->db->completeTrans();
    }

    /**
     * Data provider for {@see testGetOne()}
     *
     * @return array [string expected value, string sql ?array bind]
     */
    public function providerTestGetOne(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $bind = array('p1'=>9);

        return [
             'Return First Col, Unbound' => [
                '9', 
                "SELECT number_run_field 
                   FROM testtable_3  
                  WHERE number_run_field < 10
               ORDER BY number_run_field DESC", 
               null
                ],
                'Return Multiple Cols, take first, Unbound' => [
                'LINE 9',
                "SELECT testtable_3.varchar_field,testtable_3.* 
                   FROM testtable_3 
                  WHERE number_run_field < 10
               ORDER BY number_run_field DESC", null],
                'Return Multiple Cols, take first, Bound' => [
                'LINE 9', 
                "SELECT testtable_3.varchar_field,testtable_3.* 
                   FROM testtable_3 
                  WHERE number_run_field=$p1", 
                  $bind
                ],

            ];
    }
    
    /**
     * Test for {@see ADODConnection::getCol()]
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:getcol
     *
     * @param int $expectedValue
     * @param string $sql
     * @param ?array $bind
     * 
     * @return void
     * 
     * @dataProvider providerTestGetCol
     */
    public function testGetCol(int $expectedValue, string $sql, ?array $bind): void
    {

        $this->db->startTrans();
        if ($bind) {
            $cols = $this->db->getCol($sql, $bind);
            $this->assertSame(
                $expectedValue, 
                count($cols),
                'Get col with bind variables should return expected number of rows'
            );
        } else {
            $cols = $this->db->getCol($sql);
            $this->assertSame(
                $expectedValue, 
                count($cols),
                'getCol without bind variables should return expected number of rows'
            );
    
        }
        $this->db->completeTrans();
    }
    /**
     * Data provider for {@see testGetCol`()}
     *
     * @return array [string(getRe, array return value]
     */
    public function providerTestGetCol(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $bind = array('p1'=>'LINE 11');
        return [
                [11, "SELECT varchar_field FROM testtable_3 ORDER BY id", null],
                [1, "SELECT testtable_3.varchar_field,testtable_3.* FROM testtable_3 WHERE varchar_field=$p1", $bind],

            ];
    }
    
    /**
     * Test for {@see ADODConnection::getRow()]
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:getrow
     *
     * @param int $expectedValue
     * @param string $sql
     * @param ?array $bind
     * @return void
     * 
     * @dataProvider providerTestGetRow
     */
    public function testGetRow(int $expectedValue, string $sql, ?array $bind): void
    {
        
        if (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_UPPER) {
            $fields = [ '0' => 'ID',
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
            $fields = [ '0' => 'id',
                        '1' => 'varchar_field',
                        '2' => 'datetime_field',
                        '3' => 'date_field',
                        '4' => 'integer_field',
                        '5' => 'decimal_field',
                        '6' => 'boolean_field',
                        '7' => 'empty_field',
                        '8' => 'number_run_field'
                      ];
        }
        
        
        $this->db->startTrans();
        if ($bind) {
            $this->db->setFetchMode(ADODB_FETCH_ASSOC);
       
            $record = $this->db->getRow($sql, $bind);
        
            foreach ($fields as $key => $value) {
                $this->assertArrayHasKey(
                    $value, 
                    $record, 
                    'Checking if associative key exists in fields array'
                );
            }
        } else {
            $this->db->setFetchMode(ADODB_FETCH_NUM);
            $record = $this->db->getRow($sql);
            foreach ($fields as $key => $value) {
                $this->assertArrayHasKey(
                    $key,
                    $record,
                    'Checking if numeric key exists in fields array'
                );
            }
        }
        $this->db->completeTrans();
    }
    
    /**
     * Data provider for {@see testGetRow()}
     *
     * @return array [int numOfRows, string sql, ?array bind]
     */ 
    public function providerTestGetRow(): array
    {

        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $bind = array('p1'=>11);
        return [    
                [1, "SELECT * FROM testtable_3 ORDER BY number_run_field", null],
                [11, "SELECT * FROM testtable_3 WHERE number_run_field=$p1", $bind],
            ];
    }

    /**
     * Test for {@see ADODConnection::getAll()}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:getall
     *
     * @param int $fetchMode
     * @param array $expectedValue
     * @param string $sql
     * @param ?array $bind
     * 
     * @return void
     * 
     * @dataProvider providerTestGetAll
     */
    public function testGetAll(int $fetchMode,array $expectedValue, string $sql, ?array $bind): void
    {
        $this->db->setFetchMode($fetchMode);
        $this->db->startTrans();
       
        if ($bind) {
            $returnedRows = $this->db->getAll($sql, $bind);
        
        } else {
            $returnedRows = $this->db->getAll($sql);
        }

        $this->assertSame(
            $expectedValue,
            $returnedRows,
            'getall() should return expected rows using casing ' . ADODB_ASSOC_CASE
        );

        $this->db->completeTrans();
    }
    
    /**
     * Data provider for {@see testGetAll()}
     *
     * @return array [int fetchmode, array expected result, string sql, ?array bind]
     */
    public function providerTestGetAll(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        $p2 = $GLOBALS['ADOdbConnection']->param('p2');
        $bind = array('p1'=>2,
                      'p2'=>6
                    );
        
        switch (ADODB_ASSOC_CASE) {
            case ADODB_ASSOC_CASE_UPPER:
                return [
            'Unbound, FETCH_ASSOC,ASSOC_CASE_UPPER' => 
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
                    ORDER BY number_run_field", null]];
            /*
            'Bound, FETCH_NUM' => 
                [ADODB_FETCH_NUM, 
                    array(
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
            */
                break;
            case ADODB_ASSOC_CASE_LOWER:
               return [
            'Unbound, FETCH_ASSOC, ASSOC_CASE_LOWER' => 
                [ADODB_FETCH_ASSOC, 
                    array(
                        array('varchar_field'=>'LINE 2'),
                        array('varchar_field'=>'LINE 3'),
                        array('varchar_field'=>'LINE 4'),
                        array('varchar_field'=>'LINE 5'),
                        array('varchar_field'=>'LINE 6')
                    ),
                     "SELECT testtable_3.varchar_field 
                        FROM testtable_3 
                       WHERE number_run_field BETWEEN 2 AND 6
                    ORDER BY number_run_field", null]];
            /*
            'Bound, FETCH_NUM' => 
                [ADODB_FETCH_NUM, 
                    array(
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
            */
                break;
      
        }
       
    }


    /**
     * Test for {@see ADODConnection::selectlimit]
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:selectlimit
     *
     * @param int $fetchMode
     * @param array $expectedValue
     * @param string $sql
     * @param int $count
     * @param int $offset
     * @param ?array $bind
     * 
     * @return void
     * 
     * @dataProvider providerTestSelectLimit
    */
    public function testSelectLimit(int $fetchMode,array $expectedValue, string $sql, $count, $offset, ?array $bind): void
    {
        $this->db->setFetchMode($fetchMode);

        $this->db->startTrans();

        if ($bind) {
            $result = $this->db->selectLimit($sql, $count, $offset, $bind);
        } else {
            $result = $this->db->selectLimit($sql, $count, $offset);
        }
   

        $this->db->completeTrans();
        $returnedRows = array();
        
        foreach ($result as $index => $row) {
            $returnedRows[] = $row;
        }
    
        $this->assertSame(
            $expectedValue,
            $returnedRows, 
            'ADOConnection::selectLimit()'
        );
            
    }
    
    /**
     * Data provider for {@see testSelectLimit()}
     *
     * @return array [int $fetchMode, array $result, string $sql, int rows, int offset, ?array $bind]
     */
    public function providerTestSelectLimit(): array
    {
        $p1 = $GLOBALS['ADOdbConnection']->param('p1');
        
        $bind = array(
            'p1'=>3
        );

        return [
            'Select Unbound, FETCH_ASSOC' => 
                [ADODB_FETCH_ASSOC, 
                    array(
                        array('VARCHAR_FIELD'=>'LINE 6'),
                        array('VARCHAR_FIELD'=>'LINE 7'),
                        array('VARCHAR_FIELD'=>'LINE 8'),
                        array('VARCHAR_FIELD'=>'LINE 9')
                    ),
                     "SELECT testtable_3.varchar_field 
                        FROM testtable_3 
                          WHERE number_run_field>3
                    ORDER BY number_run_field", 4, 2, null
                ],
            'Select, Bound, FETCH_NUM' => 
                [ADODB_FETCH_NUM,
                    array(
                        array('0'=>'LINE 5'),
                        array('0'=>'LINE 6'),
                        array('0'=>'LINE 7'),
                        array('0'=>'LINE 8')
                        ),
                    "SELECT testtable_3.varchar_field 
                       FROM testtable_3 
                      WHERE number_run_field>=$p1 
                   ORDER BY number_run_field", 4, 2, $bind
                ],
            'Select Unbound, FETCH_ASSOC Get first record' => 
                [ADODB_FETCH_ASSOC, 
                    array(
                        array('DATE_FIELD'=>'2025-01-01'),
                    ),
                    "SELECT testtable_3.date_field 
                          FROM testtable_3 
                      ORDER BY number_run_field", 1, -1, null
                ],
        ];

    }

    /**
     * Test for {@see ADODConnection::CreateSequence()}
     *
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:createsequence
     *
     * @return void
     */
    public function testCreateSequence() : void
    {

        $this->db->startTrans();
        $ok = $this->db->CreateSequence('unittest_seq', 50);
        
        $this->assertTrue(
            $ok, 
            'CreateSequence should return true If a sequence is created successfully'
        );

        $this->db->completeTrans();
        $this->db->startTrans();
        $nextId = $this->db->GenID('unittest_seq');

        $this->assertSame(
            50, 
            $nextId, 
            'GenID should return the initial value of 50 in the sequence'
        );                      
        $this->assertSame(
            50, 
            $nextId, 
            'GenID should return the initial value of 50 in the sequence'
        );


        $nextId = $this->db->GenID('unittest_seq');

        $this->assertSame(
            51, 
            $nextId, 
            'GenID should return the next value of 51 in the sequence'
        );

        $this->db->completeTrans();

    } 
    
    /**
     * Test for {@see ADODConnection::DropSequence()}
     *
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:dropsequence
     *
     * @return void
     */
    public function testDropSequence() : void
    {
        $this->db->startTrans();
        $ok = $this->db->DropSequence('unittest_seq');

        $this->assertTrue(
            $ok, 
            'DropSequence should return true If a sequence is dropped successfully'
        );

        /*
        * Check if the sequence is actually dropped
        */
        $nextId = $this->db->GenID('unittest_seq');
        
        $this->assertSame(
            1, 
            $nextId, 
            'GenID should return 1 for a non-existing sequence'
        );
        $this->db->completeTrans();  
    }   

}