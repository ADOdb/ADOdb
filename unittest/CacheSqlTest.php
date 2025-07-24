<?php
/**
 * Tests cases for cache SQL functions of ADODb
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @authot Mark Newnham
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

	public function setUp(): void
	{
		$this->db        = $GLOBALS['ADOdbConnection'];
		$this->adoDriver = $GLOBALS['ADOdriver'];
		
        if (!isset($GLOBALS['TestingControl']['caching'])) {
            $this->skipAllTests = true;
            return;
        } 
        $cacheParams = $GLOBALS['TestingControl']['caching'];
        
        $this->cacheMethod = $cacheParams['cacheMethod'];
        
        if ($this->cacheMethod == 0)
        {
            $this->skipAllTests = true;
            return;
        }

        if ($this->cacheMethod == 1)
        {
            $ADODB_CACHE_DIR = $cacheParams['cacheDir'] ?? '';
            if (!is_dir($ADODB_CACHE_DIR) || !is_writable($ADODB_CACHE_DIR)) {
                $this->skipAllTests = true;
                return;
            }

            global $ADODB_CACHE_DIR;
        }

        $this->db->cacheFlush();
	}
	
	public function tearDown(): void
	{
		
	}
	
	/**
     * Test for {@see ADODConnection::execute() in select mode]
     *
	 * @dataProvider providerTestSelectCacheExecute
	*/
	public function testSelectCacheExecute(bool $expectedValue, string $sql, ?array $bind): void
	{
		if ($this->skipAllTests)
        {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }

        if($bind)
			$result = $this->db->cacheExecute($this->timeout,$sql,$bind);
		else	
			$result = $this->db->cacheExecute($this->timeout,$sql);
		
		
		$this->assertSame($expectedValue, is_object($result), 'ADOConnection::execute() in SELECT mode');
			
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
     * Test for {@see ADODConnection::execute() in non-seelct mode]
     *
	 * @dataProvider providerTestNonSelectCacheExecute
	*/
	public function testNonSelectCacheExecute(bool $expectedValue, string $sql, ?array $bind): void
	{
        if ($this->skipAllTests)
        {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }
		if($bind)
			$result = $this->db->cacheExecute($this->timeout,$sql,$bind);
		else	
			$result = $this->db->cacheExecute($this->timeout,$sql);
		
		
		$this->assertSame($expectedValue, is_object($result) && get_class($result) == 'ADORecordSet_empty', 'ADOConnection::execute() in INSERT/UPDATE/DELETE mode returns ADORecordSet_empty');
			
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
			 'Update Unbound' => 
				[true, "UPDATE testtable_1 SET integer_field=2000 WHERE id=1", null],
		 'Invalid' => 
				[false, "UPDATE testtable_1 SET xinteger_field=2000 WHERE id=1", null],
		 'Select, Bound' => 
				[true, "UPDATE testtable_1 SET integer_field=2000 WHERE varchar_field=$p1", $bind],

			];
	}
	
	
	/**
     * Test for {@see ADODConnection::getOne()]
     *
	 * @dataProvider providerTestCacheGetOne
	*/
	public function testCacheGetOne(string $expectedValue, string $sql, ?array $bind): void
	{
        if ($this->skipAllTests)
        {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }
		if ($bind)
			$this->assertSame($expectedValue, "{$this->db->cacheGetOne($this->timeout,$sql,$bind)}",'ADOConnection::getOne()');
		else
			$this->assertSame($expectedValue, "{$this->db->getOne($this->timeout,$sql)}",'ADOConnection::getOne()');
	}

	/**
	 * Data provider for {@see testGetOne()}
	 *
	 * @return array [string(getRe, array return value]
	 */
	public function providerTestCacheGetOne(): array
	{
		$p1 = $GLOBALS['ADOdbConnection']->param('p1');
		$bind = array('p1'=>'LINE 1');

		return [
			 'Return First Col, Unbound' => 
				['LINE 1', "SELECT varchar_field FROM testtable_1 ORDER BY id", null],
		 'Return Multiple Cols, take first, Unbound' => 
				['LINE 1', "SELECT testtable_1.varchar_field,testtable_1.* FROM testtable_1 ORDER BY id", null],
		 'Return Multiple Cols, take first, Bound' => 
				['LINE 1', "SELECT testtable_1.varchar_field,testtable_1.* FROM testtable_1 WHERE varchar_field=$p1", $bind],

			];
	}
	
	/**
     * Test for {@see ADODConnection::getCol()]
     *
	 * @dataProvider providerTestCacheGetCol
	*/
	public function testGetCacheCol(int $expectedValue, string $sql, ?array $bind): void
	{
        if ($this->skipAllTests)
        {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }
		if ($bind)
		{
			$cols = $this->db->cacheGetCol($sql,$bind);
			$this->assertSame($expectedValue, count($cols),'ADOConnection::getCol with bound variables()');
		}
		else
		{
			$cols = $this->db->cacheGetCol($sql);
			$this->assertSame($expectedValue, count($cols),'ADOConnection::getCol without bind variables()');
	
		}
	}
	/**
	 * Data provider for {@see testGetCol`()}
	 *
	 * @return array [string(getRe, array return value]
	 */
	public function providerTestCacheGetCol(): array
	{
		$p1 = $GLOBALS['ADOdbConnection']->param('p1');
		$bind = array('p1'=>'LINE 1');
		return [
				[11, "SELECT varchar_field FROM testtable_1 ORDER BY id", null],
				[1, "SELECT testtable_1.varchar_field,testtable_1.* FROM testtable_1 WHERE varchar_field=$p1", $bind],

			];
	}
	
	/**
     * Test for {@see ADODConnection::getRow()]
     *
	 * @dataProvider providerTestCacheGetRow
	*/
	public function testCacheGetRow(int $expectedValue, string $sql, ?array $bind): void
	{
		
        if ($this->skipAllTests)
        {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }

		$fields = [ '0' => 'id',
					  '1' => 'varchar_field',
					  '2' => 'datetime_field',
					  '3' => 'integer_field',
					  '4' => 'decimal_field'
	];
		
		if ($bind)
		{
			$this->db->setFetchMode(ADODB_FETCH_ASSOC);
			$record = $this->db->cacheGetRow($this->timeout,$sql,$bind);
			foreach($fields as $key => $value)
			{
				$this->assertArrayHasKey($value, $fields, 'Checking if associative key exists in fields array');
			}
		}
		else
		{
			$this->db->setFetchMode(ADODB_FETCH_NUM);
			$record = $this->db->cacheGetRow($this->timeout,$sql,$bind);
			foreach($fields as $key => $value)
			{
				$this->assertArrayHasKey($key, $fields, 'Checking if numeric key exists in fields array');
			}
		}
	}
	
	/**
	 * Data provider for {@see testGetRow()}
	 *
	 * @return array [string(getRe, array return value]
	 */
	public function providerTestCacheGetRow(): array
	{
		$p1 = $GLOBALS['ADOdbConnection']->param('p1');
		$bind = array('p1'=>'LINE 1');
		return [
				[1, "SELECT varchar_field FROM testtable_1 ORDER BY id", null],
				[1, "SELECT testtable_1.varchar_field,testtable_1.* FROM testtable_1 WHERE varchar_field=$p1", $bind],
			];
	}

	/**
	 * Test for {@see ADODConnection::getAll()}
	 *
	 * @dataProvider providerTestCacheGetAll
	*/
	public function testCacheGetAll(int $fetchMode,array $expectedValue, string $sql, ?array $bind): void
	{
        if ($this->skipAllTests)
        {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }
		$this->db->setFetchMode($fetchMode);

		if($bind)
			$returnedRows = $this->db->cacheGetAll($this->timeout,$sql,$bind);
		else	
			$returnedRows = $this->db->cacheGetAll($this->timeout,$sql);
		
		
		$this->assertSame($expectedValue,$returnedRows, 'ADOConnection::selectLimit()');
	}
	
	/**
	 * Data provider for {@see testGetAll()}
	 *
	 * @return array [string(getRe, array return value]
	 */
	public function providerTestCacheGetAll(): array
	{
		$p1 = $GLOBALS['ADOdbConnection']->param('p1');
		$p2 = $GLOBALS['ADOdbConnection']->param('p2');
		$bind = array('p1'=>'LINE 3',
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
					   WHERE varchar_field BETWEEN 'LINE 3' AND 'LINE 6'
					ORDER BY id", null],
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
				   ORDER BY id", $bind],

				];
	}


	/**
     * Test for {@see ADODConnection::execute() in select mode]
     *
	 * @dataProvider providerTestCacheSelectLimit
	*/
	public function testCacheSelectLimit(int $fetchMode,array $expectedValue, string $sql, ?array $bind): void
	{
        if ($this->skipAllTests)
        {
            $this->markTestSkipped('Skipping tests as caching not configured');
            return;
        }

		$this->db->setFetchMode($fetchMode);

		if($bind)
			$result = $this->db->cacheSelectLimit($this->timeout,$sql,4,2,$bind);
		else	
			$result = $this->db->cacheSelectLimit($this->timeout,$sql,4,2);
		
		$returnedRows = array();
		foreach($result as $index => $row)
		{
			$returnedRows[] = $row;

		}
	
		$this->assertSame($expectedValue,$returnedRows, 'ADOConnection::selectLimit()');
			
	}
	
	/**
	 * Data provider for {@see testSelectLimit()}
	 *
	 * @return array [int $fetchMode, array $result, string $sql, ?array $bind]
	 */
	public function providerTestCacheSelectLimit(): array
	{
		$p1 = $GLOBALS['ADOdbConnection']->param('p1');
		
		$bind = array(
			'p1'=>'LINE 0'
		);

		return [
			 'Select Unbound, FETCH_ASSOC' => 
				[ADODB_FETCH_ASSOC, 
					array(
						array('varchar_field'=>'LINE 3'),
						array('varchar_field'=>'LINE 4'),
						array('varchar_field'=>'LINE 5'),
						array('varchar_field'=>'LINE 6')
					),
					 "SELECT testtable_1.varchar_field FROM testtable_1 ORDER BY id", null],
		    'Select, Bound, FETCH_NUM' => 
				[ADODB_FETCH_NUM, 
					array(
						array('0'=>'LINE 3'),
						array('0'=>'LINE 4'),
						array('0'=>'LINE 5'),
						array('0'=>'LINE 6')
						),
					"SELECT testtable_1.varchar_field FROM testtable_1 WHERE varchar_field>$p1 ORDER BY id", $bind],

				];
	}
}
	
	