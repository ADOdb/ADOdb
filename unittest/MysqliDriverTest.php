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
class MysqliDriverTest extends ADOdbTestCase
{
    
    protected string $testTableName = 'testtable_1';
    protected string $testIndexName1 = 'insertion_index_1';
    protected string $testIndexName2 = 'insertion_index_2';

    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setup(): void
    {

        parent::setup();

        if ($this->adoDriver !== 'mysqli') {
            $this->skipFollowingTests = true;
            $this->markTestSkipped(
                'This test is only applicable for the mysqli driver'
            );
        }
        
    }
    
    
    /**
     * Tests setting a comment on a column 
     * using {@see ADODConnection::setCommentSQL()}
     * using the mysql format which includes the column definition.
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:setcommentsql
     *
     * @return void
     */
    public function testSetCommentSql(): void
    {
        $this->markTestSkipped(
            'Skipping test as the feature is not yet implemented'
        );
        return;
 
        $sql = $this->dataDictionary->setCommentSQL(
            $this->testTableName, 
            'varchar_field',
            'varchar_test_comment',
            "varchar(50) NOT NULL DEFAULT ''"
        );

      
        list ($response,$errno,$errmsg) = $this->executeSqlString($sql);
       
        if ($errno > 0) {
            $this->fail(
                'setCommentSql() failed:' . $errmsg
            );
            return;
        }

        $ok = is_object($response);
       
        $this->assertEquals(
            true,
            $ok, 
            'setCommentSql() should return an object ' . 
            'if the comment was set successfully'
        );

        if (!$ok) {
            return;          
        }

        $className = get_class($response);
        $this->assertStringContainsString(
            'ADORecordSet_',
            $className,
            'setCommentSQL() should return an ADORecordset_ object'
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
        $this->markTestSkipped(
            'Skipping tests as the feature is not yet implemented'
        );
        return;

        $sql = $this->dataDictionary->getCommentSQL(
            $this->testTableName, 
            'varchar_field'
        );
       
        list ($response,$errno,$errmsg) = $this->executeSqlString($sql);

        if ($errno > 0) {
            $this->fail(
                'getCommentSql() failed:' . $errmsg
            );
            return;
        }
               
        $this->assertEquals(
            'varchar_test_comment',
            $response, 
            'getCommentSQL() should return "varchar_test_comment" if ' . 
            'retrieved successfully'
        );

    }
}