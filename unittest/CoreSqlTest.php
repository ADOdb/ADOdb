<?php
/**
 * Tests cases for core SQL functions of ADODb
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
class CoreSqlTest extends TestCase
{
	protected $db;
	protected $adoDriver;

	public function setUp(): void
	{
		$this->db        = $GLOBALS['ADOdbConnection'];
		$this->adoDriver = $GLOBALS['ADOdriver'];
		
	}
	
	public function tearDown(): void
	{
		
	}
	
	/**
     * Test for {@see ADODConnection::execute() in select mode]
     *
	 * @dataProvider providerTestSelectExecute
	*/
	public function testSelectExecute(bool $expectedValue, string $sql, ?array $bind): void
	{
		if($bind)
			$result = $this->db->execute($sql,$bind);
		else	
			$result = $this->db->execute($sql);
		
		
		$this->assertSame($expectedValue, is_object($result), 'ADOConnection::execute() in SELECT mode');
			
	}
	
	/**
	 * Data provider for {@see testSelectExecute()}
	 *
	 * @return array [string(getRe, array return value]
	 */
	public function providerTestSelectExecute(): array
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
	 * @dataProvider providerTestNonSelectExecute
	*/
	public function testNonSelectExecute(bool $expectedValue, string $sql, ?array $bind): void
	{
		if($bind)
			$result = $this->db->execute($sql,$bind);
		else	
			$result = $this->db->execute($sql);
		
		
		$this->assertSame($expectedValue, is_object($result) && get_class($result) == 'ADORecordSet_empty', 'ADOConnection::execute() in INSERT/UPDATE/DELETE mode returns ADORecordSet_empty');
			
	}
	
	/**
	 * Data provider for {@see testNonSelectExecute()}
	 *
	 * @return array [string(getRe, array return value]
	 */
	public function providerTestNonSelectExecute(): array
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
	 * @dataProvider providerTestGetOne
	*/
	public function testGetOne(string $expectedValue, string $sql, ?array $bind): void
	{
		if ($bind)
			$this->assertSame($expectedValue, "{$this->db->getOne($sql,$bind)}",'ADOConnection::getOne()');
		else
			$this->assertSame($expectedValue, "{$this->db->getOne($sql)}",'ADOConnection::getOne()');
	}

	/**
	 * Data provider for {@see testGetOne()}
	 *
	 * @return array [string(getRe, array return value]
	 */
	public function providerTestGetOne(): array
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
	 * @dataProvider providerTestGetCol
	*/
	public function testGetCol(int $expectedValue, string $sql, ?array $bind): void
	{
		if ($bind)
			$this->assertSame($expectedValue, count("{$this->db->getCol($sql,$bind)}"),'ADOConnection::getCol with bound variables()');
		else
			$this->assertSame($expectedValue, count("{$this->db->getCol($sql)}"),'ADOConnection::getOne without bind variables()');
	}
	
	/**
	 * Data provider for {@see testGetCol`()}
	 *
	 * @return array [string(getRe, array return value]
	 */
	public function providerTestGetCol(): array
	{
		$p1 = $GLOBALS['ADOdbConnection']->param('p1');
		$bind = array('p1'=>'LINE 1');
		return [
			 'Return First Col, Unbound' => 
				[11, "SELECT varchar_field FROM testtable_1 ORDER BY id", null],
			 'Return Multiple Cols, take first, Bound' => 
				[1, "SELECT testtable_1.varchar_field,testtable_1.* FROM testtable_1 WHERE varchar_field=$p1", $bind],

			];
	}
	
	/**
     * Test for {@see ADODConnection::getRow()]
     *
	 * @dataProvider providerTestGetRow
	*/
	public function testGetRow(int $expectedValue, string $sql, ?array $bind): void
	{
		if ($bind)
			$this->assertSame($expectedValue, count("{$this->db->getRow($sql,$bind)}"),'ADOConnection::getRow` with bound variables()');
		else
			$this->assertSame($expectedValue, count("{$this->db->getRow($sql)}"),'ADOConnection::getOne without bind variables()');
	}
	
	/**
	 * Data provider for {@see testGetRow()}
	 *
	 * @return array [string(getRe, array return value]
	 */
	public function providerTestGetRows(): array
	{
		$p1 = $GLOBALS['ADOdbConnection']->param('p1');
		$bind = array('p1'=>'LINE 1');
		return [
			 'Return First Col, Unbound' => 
				[11, "SELECT varchar_field FROM testtable_1 ORDER BY id", null],
			 'Return Multiple Cols, take first, Bound' => 
				[1, "SELECT testtable_1.varchar_field,testtable_1.* FROM testtable_1 WHERE varchar_field=$p1", $bind],

			];
	}
}
	
	