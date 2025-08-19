<?php
/**
 * Tests cases for the mssqlnative driver of ADOdb.
 * Try to write database-agnostic tests where possible.
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
 * Class TransactionScopeTest
 *
 * Test cases for for ADOdb Tranaction Scope functionality
 */
class TransactionScopeTest extends TestCase
{
    protected ?object $db;
    protected ?string $adoDriver;
    protected ?object $dataDictionary;

    protected bool $skipFollowingTests = false;

    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setup(): void
    {

        $this->db        = &$GLOBALS['ADOdbConnection'];
        $this->adoDriver = $GLOBALS['ADOdriver'];
        $this->db->setFetchMode(ADODB_FETCH_ASSOC);

        if (!$this->db->hasTransactions) {
            $this->skipFollowingTests = true;
            $this->markTestSkipped(
                'This database driver does not support transactions'
            );
        }
        
    }
    
    /**
     * Tear down the test environment
     *
     * @return void
     */
    public function tearDown(): void
    {
        
    }

    public function testStartCompleteTransaction(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping testStartCompleteTransaction as it is not applicable for the current driver');
        }

        $this->db->StartTrans();
            
        if ($this->db->transCnt == 0) {
            $this->assertTrue(
                0,
                'Transaction did not start correctly, transCnt should be greater than 0'
            );
            return;
        }

        $this->assertTrue(
            $this->db->transCnt == 1,
            'Transaction count should be equal to 1 after starting a transaction'
        );

        $sql = "SELECT id, varchar_field FROM testtable_3 ORDER BY id";

        $baseData = $this->db->getRow($sql);
      
        $sql = "UPDATE testtable_3 SET varchar_field = 'transaction test' WHERE id = 1";

        $this->db->execute($sql);

        /*
        * Check that the data has been updated in the transaction
        */
        $sql = "SELECT varchar_field FROM testtable_3 WHERE id = {$baseData['ID']}";
        $preCommit = $this->db->getOne($sql);

        $this->assertEquals(
            'transaction test',
            $preCommit,
            'Data should be updated in the transaction'
        );

        /*
        * Now we will rollback the transaction
        */
        $this->db->rollbackTrans();
        $this->assertTrue(
            $this->db->transCnt == 1,
            'Transaction count remain equal to 1 after rolling back the transaction'
        );

        $sql = "SELECT varchar_field FROM testtable_3 WHERE id = {$baseData['ID']}";
        $postRollback = $this->db->getOne($sql);

        $this->assertEquals(
            'transaction test',
            $postRollback,
            'Data should still be the updated value after rolling back the transaction'
        );

        $this->db->CompleteTrans();

        $this->assertTrue(
            $this->db->transCnt == 0,
            'Transaction count should now equal 0 after completing the transaction'
        );

        $sql = "SELECT varchar_field FROM testtable_3 WHERE id = {$baseData['ID']}";
        $postCommit = $this->db->getOne($sql);

        $this->assertEquals(
            $baseData['VARCHAR_FIELD'],
            $postCommit,
            'Data should still be reverted to the original value after commiting the transaction'
        );

    
    }
    
    /**
     * Test beginning a transaction, committing it, and checking the data
     *
     * @return void
     */
    public function testBeginCommitTransaction(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping testBeginCommitTransaction as it is not applicable for the current driver');
        }

    $this->db->BeginTrans();
            
        if ($this->db->transCnt == 0) {
            $this->assertTrue(
                0,
                'Transaction did not start correctly, transCnt should be greater than 0'
            );
            return;
        }

        $this->assertTrue(
            $this->db->transCnt == 1,
            'Transaction count should be equal to 1 after starting a transaction'
        );

        $sql = "SELECT id, varchar_field FROM testtable_3 ORDER BY id";

        $baseData = $this->db->getRow($sql);

        $sql = "UPDATE testtable_3 SET varchar_field = 'transaction test' WHERE id = 1";

        $this->db->execute($sql);

        /*
        * Check that the data has been updated in the transaction
        */
        $sql = "SELECT varchar_field FROM testtable_3 WHERE id = {$baseData['ID']}";
        $preCommit = $this->db->getOne($sql);

        $this->assertEquals(
            $baseData['VARCHAR_FIELD'],
            $preCommit,
            'Data should not yet be updated in the transaction before commit'
        );

        $this->db->CommitTrans();

        /*
        * Check that the data has been updated in the transaction
        */
        $sql = "SELECT varchar_field FROM testtable_3 WHERE id = {$baseData['ID']}";
        $postCommit = $this->db->getOne($sql);

        $this->assertEquals(
            'transaction test',
            $postCommit,
            'Data should now be updated in the transaction after commit'
        );

    }
}