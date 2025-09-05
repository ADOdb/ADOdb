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
class TransactionScopeTest extends ADOdbTestCase
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

        parent::setup();

        if (!$this->db->hasTransactions) {
            $this->skipFollowingTests = true;
            $this->markTestSkipped(
                'This database driver does not support transactions'
            );
        }
        
    }
    
   
    /**
     * Tests the smart transaction handling capabilities
     *
     * @return void
     */
    public function testStartCompleteTransaction(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping testStartCompleteTransaction as it ' . 
                'is not applicable for the current driver'
            );
        }

        if ($this->db->transOff > 0) {
            $this->db->completeTrans(false);
        }


        $this->db->StartTrans();
            
  
        $assertion = $this->assertEquals(
            1,
            $this->db->transOff,
            'Transaction did not start correctly, ' . 
            'transOff should be greater than 0'
        );
        
        
        $sql = "SELECT id, varchar_field 
                  FROM testtable_3 
              ORDER BY id";

        $baseData = $this->db->getRow($sql);
        list($errno, $errmsg) = $this->assertADOdbError($sql);

        if ($errno > 0) {
            return;
        }
      
        $sql = "UPDATE testtable_3 
                   SET varchar_field = 'transaction test' 
                 WHERE id = 1";

        list($result, $errno, $errmsg) = $this->executeSqlString($sql);

        if ($errno > 0) {
            return;
        }

        /*
        * Check that the data has been updated in the transaction
        */
        $sql = "SELECT varchar_field FROM testtable_3 WHERE id = {$baseData['ID']}";
        $preCommit = $this->db->getOne($sql);
        list($errno, $errmsg) = $this->assertADOdbError($sql);

        if ($errno > 0) {
            return;
        }

        $this->assertEquals(
            'transaction test',
            $preCommit,
            'Data should be updated in the transaction'
        );

        /*
        * Now we will rollback the transaction
        */
        $this->db->rollbackTrans();
        if ($this->db->errorNo() > 0) {
            $this->fail(
                $this->db->errorMsg()
            );
            return;
        }

        $this->assertEquals(
            1,
            $this->db->transOff,
            'Transaction count still should be 1 after rolling back the ' . 
            'transaction but before the completeTrans()'
        );

        $sql = "SELECT varchar_field FROM testtable_3 WHERE id = {$baseData['ID']}";
        $postRollback = $this->db->getOne($sql);
        list($errno, $errmsg) = $this->assertADOdbError($sql);

        if ($errno > 0) {
            return;
        }

        $this->assertEquals(
            'transaction test',
            $postRollback,
            'Data should still be the updated value ' . 
            'after rolling back the transaction'
        );

        $this->db->CompleteTrans();

        $assertion = $this->assertEquals(
            0,
            $this->db->transOff,
            'Transaction count $transOff should now equal 0 ' . 
            'after completing the transaction'
        );

        if ($this->db->transOff <> 0) {
            $this->fail(
                sprintf(
                    'Trans Count shoud be 0 but is %d', 
                    $this->db->transOff
                )
            );
            return;
        }

        $sql = "SELECT varchar_field 
                  FROM testtable_3 
                 WHERE id = {$baseData['ID']}";
        
        $postCommit = $this->db->getOne($sql);
        list($errno, $errmsg) = $this->assertADOdbError($sql);

        if ($errno > 0) {
            return;
        }

        $this->assertEquals(
            $baseData['VARCHAR_FIELD'],
            $postCommit,
            'Data should still be reverted to the original ' . 
            'value after commiting the transaction'
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
            
        $assertion = $this->assertEquals(
            1,
            $this->db->transCnt,
            'Transaction did not start correctly,' . 
            'transCnt should be equal to 1'
        );
        
        $sql = "SELECT id, varchar_field 
                  FROM testtable_3 
              ORDER BY id";

        $baseData = $this->db->getRow($sql);
        list($errno, $errmsg) = $this->assertADOdbError($sql);

        if ($errno > 0) {
            return;
        }


        $sql = "UPDATE testtable_3 
                   SET varchar_field = 'transaction test' 
                 WHERE id = 1";

        $this->db->execute($sql);
        list($result, $errno, $errmsg) = $this->executeSqlString($sql);

        if ($errno > 0) {
            return;
        }

        /*
        * Check that the data has been updated in the transaction
        */
        $sql = "SELECT varchar_field 
                  FROM testtable_3 
                 WHERE id = {$baseData['ID']}";
        $preCommit = $this->db->getOne($sql);
        
        list($errno, $errmsg) = $this->assertADOdbError($sql);

        if ($errno > 0) {
            return;
        }
        
        $this->assertEquals(
            $baseData['VARCHAR_FIELD'],
            $preCommit,
            'VARCHAR_FIELD Data should not yet be updated ' . 
            'in the transaction before commit'
        );

        $this->db->CommitTrans();

        /*
        * Check that the data has been updated in the transaction
        */
        $sql = "SELECT varchar_field 
                  FROM testtable_3 
                 WHERE id = {$baseData['ID']}";

        $postCommit = $this->db->getOne($sql);
        
        list($errno, $errmsg) = $this->assertADOdbError($sql);

        if ($errno > 0) {
            return;
        }
        $this->assertEquals(
            'transaction test',
            $postCommit,
            'VARCHAR_FIELD Data should now be updated ' . 
            'in the transaction after commit'
        );

    }
}