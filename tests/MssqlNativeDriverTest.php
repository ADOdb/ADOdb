<?php
/**
 * Tests for mssqlnative transaction return values.
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
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
 */

use PHPUnit\Framework\TestCase;

require_once dirname(__FILE__) . '/../adodb.inc.php';

/**
 * Isolate the global SQLSRV substitutes from other tests.
 * Run with the sqlsrv extension disabled.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MssqlNativeDriverTest extends TestCase
{
	/** @var ADODB_mssqlnative */
	private $db;

	public function setUp(): void
	{
		if (extension_loaded('sqlsrv')) {
			$this->markTestSkipped('Disable the sqlsrv extension to use the native function substitutes.');
		}

		function sqlsrv_configure($setting, $value) {
			return true;
		}
		function sqlsrv_begin_transaction($connection) {
			$connection->calls[] = 'begin';
			return $connection->result;
		}
		function sqlsrv_commit($connection) {
			$connection->calls[] = 'commit';
			return $connection->result;
		}
		function sqlsrv_rollback($connection) {
			$connection->calls[] = 'rollback';
			return $connection->result;
		}

		require_once dirname(__FILE__) . '/../drivers/adodb-mssqlnative.inc.php';
		// Skip native connection configuration; these tests only exercise transaction methods.
		$this->db = (new ReflectionClass('ADODB_mssqlnative'))->newInstanceWithoutConstructor();
		$this->db->_connectionID = (object) array('result' => false, 'calls' => array());
	}

	/** @dataProvider providerTransactionResults */
	public function testTransactionReturnsNativeResult($method, $nativeCall, $result, $arguments): void
	{
		$this->db->_connectionID->result = $result;
		$this->assertSame($result, $this->db->$method(...$arguments));
		$this->assertSame(array($nativeCall), $this->db->_connectionID->calls);
	}

	public function providerTransactionResults(): array
	{
		return array(
			'begin succeeds' => array('BeginTrans', 'begin', true, array()),
			'begin fails' => array('BeginTrans', 'begin', false, array()),
			'commit succeeds' => array('CommitTrans', 'commit', true, array()),
			'commit fails' => array('CommitTrans', 'commit', false, array()),
			'rollback succeeds' => array('RollbackTrans', 'rollback', true, array()),
			'rollback fails' => array('RollbackTrans', 'rollback', false, array()),
			'commit(false) rollback succeeds' => array('CommitTrans', 'rollback', true, array(false)),
			'commit(false) rollback fails' => array('CommitTrans', 'rollback', false, array(false)),
		);
	}

	public function testDisabledTransactionsDoNotCallNativeFunctions(): void
	{
		$this->db->transOff = 1;
		foreach (array('BeginTrans', 'CommitTrans', 'RollbackTrans') as $method) {
			$this->assertTrue($this->db->$method());
		}
		$this->assertTrue($this->db->CommitTrans(false));
		$this->assertSame(array(), $this->db->_connectionID->calls);
		$this->assertSame(0, $this->db->transCnt);
	}

	public function testStartTransReportsBeginFailure(): void
	{
		$this->assertFalse($this->db->StartTrans());
		$this->assertSame(array('begin'), $this->db->_connectionID->calls);
	}

	public function testCompleteTransReportsCommitFailure(): void
	{
		$this->db->_connectionID->result = true;
		$this->assertTrue($this->db->StartTrans());
		$this->db->_connectionID->result = false;
		$this->assertFalse($this->db->CompleteTrans());
		$this->assertSame(array('begin', 'commit'), $this->db->_connectionID->calls);
	}
}
