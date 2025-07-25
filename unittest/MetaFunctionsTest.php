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
class MetaFunctionsTest extends TestCase
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
     * Test for {@see ADODConnection::metaTables()]
     *
	 * @dataProvider providerTestMetaTables
     */
    public function testMetaTables(bool $includesTable1,string $filterType, string $mask): void
    {
        $executionResult = $this->db->metaTables($filterType);

		$this->assertSame($includesTable1, in_array('testtable_1',$executionResult));
    }
	
	/**
	 * Data provider for {@see testMetaTables()}
	 *
	 * @return array [bool match, string $filterType string $mask]
	 */
	public function providerTestMetaTables(): array
	{
		return [
			'Show both Tables & Views' 	=> [true, '',''],
			'Show only Tables'  		=> [true,'TABLES',''],
			'Show only Views'   		=> [false,'VIEWS',''],
			'Show only [T]ables'  	   => [true,'T',''],
			'Show only [V]iews'       => [false,'V',''],
			'Show only tables beginning test%'   => [true,'','test%'],
			'Show only tables beginning notest%' => [false,'','notest%'],
			];
	}
	
	/**
     * Test for {@see ADODConnection::metaColumn()]
     *
	 * @dataProvider providerTestMetaColumnNames
     */
    public function testMetaColumnNames(bool $returnType, array $expectedResult): void
    {
        $executionResult = $this->db->metaColumnNames('testtable_1',$returnType);
		$this->assertSame($expectedResult, $executionResult);
    }
	
	/**
	 * Data provider for {@see testMetaColumNames()}
	 *
	 * @return array [bool array type, array return value]
	 */
	public function providerTestMetaColumnNames(): array
	{
		return [
			 'Returning Associative Array' => [
				 false,
					[ 'ID' => 'id',
					  'VARCHAR_FIELD' => 'varchar_field',
					  'DATETIME_FIELD' => 'datetime_field',
					  'DATE_FIELD' => 'date_field',
					  'INTEGER_FIELD' => 'integer_field',
					  'DECIMAL_FIELD' => 'decimal_field',
					  'EMPTY_FIELD' => 'empty_field'
					]
				],
				'Returning Numeric Array' => [
				 true,
					[ '0' => 'id',
					  '1' => 'varchar_field',
					  '2' => 'datetime_field',
					  '3' => 'date_field',
					  '4' => 'integer_field',
					  '5' => 'decimal_field',
					  '6' => 'empty_field'
					]
				]
			];
	}
	
	/**
     * Test 1 for {@see ADODConnection::metaColumns()]
	 * Checks that there are right number of columns
     *
     */
    public function testMetaColumnCount(): void
    {
		$expectedResult  = 7;
        $executionResult = $this->db->metaColumns('testtable_1');
		$this->assertSame($expectedResult, count($executionResult));
    }
	
	/**
     * Test 3 for {@see ADODConnection::metaColumns()]
	 * Checks that every returned element is an ADOFieldObject
     *
     */
    public function testMetaColumnObjects(): void
    {
        $executionResult = $this->db->metaColumns('testtable_1');
		foreach ($executionResult as $o)
		{
			$oType = get_class($o);
			$this->assertSame('ADOFieldObject', $oType,'metaColumns returns an ADOFieldObject object');
		}
    }
	
	/**
     * Test for {@see ADODConnection::metaColumns()]
     *
	 * @xdataProvider providerTestMetaColumns
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
			'6' => 'EMPTY_FIELD'
		];
		
        $executionResult = $this->db->metaColumns('testtable_1');
		
		print "\nChecking that metaColumns returns the expected fields\n";

		foreach($expectedResult as $expectedField)
		{
			$this->assertArrayHasKey($expectedField, $executionResult, 'Checking for expected field in metaColumns return value');
			if (!isset($executionResult[$expectedField]))
				continue;

			$typeof = get_class($executionResult[$expectedField]);
			$this->assertSame('ADOFieldObject', $typeof, 'Checking that metaColumns returns an ADOFieldObject object');
			if (strcmp($typeof, 'ADOFieldObject') !== 0)
				continue;

		}
		
    }
	
	/**
	 * Data provider for {@see testMetaColumns()}
	 *
	 * @return array [bool array type, array return value]
	 */
	public function xproviderTestMetaColumns(): array
	{
		return [
			
				['0' => 'id',
				'1' => 'varchar_field',
				'2' => 'datetime_field',
				'3' => 'integer_field',
				'4' => 'decimal_field',
				'5' => 'empty_field'
				]
		];
	
	}
	
	/**
     * Test 1 for {@see ADODConnection::metaIndexes()]
	 * Checks that the correct number of indexes is returned
     *
     */
    public function testMetaIndexCount(): void
    {
        $executionResult = $this->db->metaIndexes('testtable_1');
		$this->assertSame(2, count($executionResult),'Checking Index Count');
    }
	
	/**
     * Test 2 for {@see ADODConnection::metaIndexes()]
	 * Checks that the correct unique indexes is returned
	 * @dataProvider providerTestMetaIndexUniqueness
     */
    public function testMetaIndexUniqueness($result,$indexName): void
    {
        $executionResult = $this->db->metaIndexes('testtable_1');
		$this->assertSame($result, ($executionResult[$indexName]['unique'] == 1),'Checking Index Uniqueness');
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
     */
    public function testMetaPrimaryKeys(): void
    {
        $executionResult = $this->db->metaPrimaryKeys('testtable_1');
		$this->assertSame('id', $executionResult[0]);

    }

	/**
     * Test for {@see ADODConnection::metaForeignKeys()]
	 * Checks that the correct list of foreigh keys is returned
     */
	public function testMetaForeignKeys(): void
	{
		$this->db->setFetchMode(ADODB_FETCH_ASSOC);
		$executionResult = $this->db->metaForeignKeys('testtable_2');
		
		$this->assertArrayHasKey('testtable_1', $executionResult);
		$this->assertArrayHasKey('integer_field', $executionResult['testtable_1']);
		
	} 
	
	/**
     * Test for {@see ADODConnection::metaType()]
	 * Checks that the correct metatype is returned
	 * @dataProvider providerTestMetaTypes
     */
    public function testMetaTypes(?string $metaType,int $offset): void
    {
        $executionResult = $this->db->execute('SELECT * FROM testtable_1');
		$this->assertSame($metaType, $executionResult->metaType($executionResult->fetchField($offset)->type),'Checking MetaType');
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
		3/
		*/
		return [
	     	'Field 0 Is INTEGER' => ['I',0],
			'Field 1 Is VARCHAR' => ['C',1],
			'Field 2 Is DATETIME' => ['T',2],
			'Field 3 Is DATE' => ['D',3],
			'Field 4 Is INTEGER' => ['I',4],
			'Field 5 Is NUMBER' => ['N',5],
			'Field 6 Is VARCHAR' => ['C',6],
			'Field 7 Does not Exist' => [null,7],
	
			 
		];
	}
	
	/**
     * Test for {@see ADODConnection::serverInfo()]
	 * Checks that version is returned
	 */
    public function testServerInfoVersion(): void
    {
		$this->db->setFetchMode(ADODB_FETCH_ASSOC);
        $executionResult = $this->db->serverInfo();
		$this->assertArrayHasKey('version',$executionResult,'Checking for mandatory key "version" in serverInfo');
    }
	/**
     * Test for {@see ADODConnection::serverInfo()]
	 * Checks that description is  returned
	 */
    public function testServerInfoDescription(): void
    {
		$this->db->setFetchMode(ADODB_FETCH_ASSOC);
        $executionResult = $this->db->serverInfo();
		$this->assertArrayHasKey('description',$executionResult,'Checking for mandatory key "description" in serverInfo');
    }
	
}