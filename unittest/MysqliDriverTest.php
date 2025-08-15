<?php
/**
 * Tests cases for the mysqli driver of ADOdb.
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
 * Class MetaFunctionsTest
 *
 * Test cases for for ADOdb MetaFunctions
 */
class MysqliDriverTest extends TestCase
{
    protected ?object $db;
    protected ?string $adoDriver;
    protected ?object $dataDictionary;

    protected bool $skipFollowingTests = false;

    protected string $testTableName = 'insertion_table';
    protected string $testIndexName1 = 'insertion_index_1';
    protected string $testIndexName2 = 'insertion_index_2';

    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setup(): void
    {

        $this->db        = &$GLOBALS['ADOdbConnection'];
        $this->adoDriver = $GLOBALS['ADOdriver'];
        $this->dataDictionary = $GLOBALS['ADOdataDictionary'];


        if ($this->adoDriver !== 'mysqli') {
            $this->skipFollowingTests = true;
            $this->markTestSkipped(
                'This test is only applicable for the mysqli driver'
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

    


    /**
     * Tests setting a comment on a column using {@see ADODConnection::setCommentSQL()}
     * using the mysql format which includes the column definition.
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:setcommentsql
     *
     * @return void
     */
    public function testSetCommentSql(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        $sql = $this->dataDictionary->setCommentSQL(
            $this->testTableName, 
            "varchar_field varchar(50) NOT NULL DEFAULT ''",
            'varchar_test_comment'
        );

       
        $response = $this->db->execute($sql);

        $ok = is_object($response);
       
        $this->assertEquals(
            true,
            $ok, 
            'Test of setCommentSQL - should return an object if the comment was set successfully'
        );

        if (!$ok) {
            return;          
        }

        $className = get_class($response);
        $this->assertStringContainsString(
            'ADORecordSet_',
            $className,
            'Test of setCommentSQL - should return an ADORecordset_ object'
        );     
    }

    /**
     * Tests getting a comment on a column
     * 
     * @see ADODConnection::getCommentSQL()
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:getcommentsql
     *
     * @return void
     */
    public function testGetCommentSql(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping tests as the table was not created successfully'
            );
            return;
        }

        $sql = $this->dataDictionary->getCommentSQL(
            $this->testTableName, 
            'varchar_field'
        );
      
        $response = $this->db->execute($sql);

        $ok = is_object($response);
       
        $this->assertEquals(
            true,
            $ok, 
            'Test of getCommentSQL - should return an object if the comment was set successfully'
        );

        if (!$ok) {
            return;          
        }

        $className = get_class($response);
        $this->assertStringContainsString(
            'ADORecordSet_',
            $className,
            'Test of getCommentSQL - should return an ADORecordset_mysqli object'
        );     

    }
}