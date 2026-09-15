<?php
/**
 * Tests cases for drivers/adodb-mysqli.inc.php
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
 * Class MysqliReturningTest.
 *
 * Regression test for the mysqli driver's true prepared statement path:
 * a duplicate-key (or other constraint) violation on a bound
 * INSERT ... RETURNING statement is not reported by mysqli_stmt_execute() -
 * it only surfaces when the result set is materialized via get_result().
 * Before the fix, ErrorNo()/ErrorMsg() came back empty in that case even
 * though Execute() correctly returned false and no row was inserted.
 *
 * Requires a live MariaDB 10.5+ server (RETURNING support); the test is
 * skipped if one isn't reachable via the ADODB_TEST_MYSQLI_* env vars.
 */
class MysqliReturningTest extends TestCase
{
    /** @var ADODB_mysqli|false */
    private $db;

    protected function setUp(): void
    {
        if (!extension_loaded('mysqli')) {
            $this->markTestSkipped('mysqli extension not available');
        }

        $host = getenv('ADODB_TEST_MYSQLI_HOST') ?: '127.0.0.1';
        $port = getenv('ADODB_TEST_MYSQLI_PORT') ?: '3306';
        $user = getenv('ADODB_TEST_MYSQLI_USER') ?: 'root';
        $pass = getenv('ADODB_TEST_MYSQLI_PASS') ?: 'root';
        $name = getenv('ADODB_TEST_MYSQLI_DB') ?: 'test';

        $this->db = ADONewConnection('mysqli');
        $connected = @$this->db->Connect("$host:$port", $user, $pass, $name);
        if (!$connected) {
            $this->markTestSkipped(
                'No mysqli test database reachable at ' . $host . ':' . $port .
                ' (set ADODB_TEST_MYSQLI_* env vars to point at one)'
            );
        }

        $info = $this->db->ServerInfo();
        if (stripos($info['description'], 'mariadb') === false) {
            $this->markTestSkipped('INSERT ... RETURNING requires MariaDB');
        }

        $this->db->Execute('DROP TABLE IF EXISTS adodb_test_returning');
        $this->db->Execute(
            'CREATE TABLE adodb_test_returning (id INT PRIMARY KEY, name VARCHAR(50))'
        );
        $this->db->Execute(
            "INSERT INTO adodb_test_returning (id, name) VALUES (1, 'existing')"
        );
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->Execute('DROP TABLE IF EXISTS adodb_test_returning');
        }
    }

    /**
     * A duplicate-key violation on a bound INSERT ... RETURNING must be
     * reported through ErrorNo()/ErrorMsg(), not just as a false return.
     */
    public function testDuplicateKeyOnReturningInsertReportsError(): void
    {
        $rs = $this->db->Execute(
            'INSERT INTO adodb_test_returning (id, name) VALUES (?, ?) RETURNING id',
            [1, 'duplicate']
        );

        $this->assertFalse($rs, 'Execute() must fail on a duplicate key');
        $this->assertSame(1062, $this->db->ErrorNo(), 'ErrorNo() must report the duplicate-key error');
        $this->assertStringContainsString(
            'Duplicate entry',
            $this->db->ErrorMsg(),
            'ErrorMsg() must report the duplicate-key error'
        );

        $count = $this->db->GetOne('SELECT COUNT(*) FROM adodb_test_returning WHERE id = 1');
        $this->assertSame(1, (int) $count, 'The duplicate row must never be inserted');
    }

    /**
     * Control: a plain (non-RETURNING) bound INSERT must keep reporting the
     * error exactly as before this fix (see #872 / #876).
     */
    public function testDuplicateKeyOnPlainInsertReportsError(): void
    {
        $rs = $this->db->Execute(
            'INSERT INTO adodb_test_returning (id, name) VALUES (?, ?)',
            [1, 'duplicate']
        );

        $this->assertFalse($rs);
        $this->assertSame(1062, $this->db->ErrorNo());
        $this->assertStringContainsString('Duplicate entry', $this->db->ErrorMsg());
    }

    /**
     * A successful bound INSERT ... RETURNING must still return a usable
     * recordset with the returned column(s).
     */
    public function testSuccessfulReturningInsertReturnsRow(): void
    {
        $rs = $this->db->Execute(
            'INSERT INTO adodb_test_returning (id, name) VALUES (?, ?) RETURNING id',
            [2, 'new']
        );

        $this->assertNotFalse($rs);
        $row = $rs->FetchRow();
        $this->assertSame(2, (int) $row['id']);
    }
}
