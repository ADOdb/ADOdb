<?php
/**
 * Tests cases for XMLSc functions of ADODb
 *
 * This file is part ohemaf ADOdb, a Database Abstraction Layer library for PHP.
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
class XmlSchemaTest extends TestCase
{
    protected ?object $db;
    protected ?string $adoDriver;
    protected ?object $dataDictionary;
    protected ?object $xmlSchema;

    protected bool $skipFollowingTests = false;

    protected string $testTableName = 'insertion_table';
    protected string $testIndexName1 = 'insertion_index_1';
    protected string $testIndexName2 = 'insertion_index_2';

    /**
     * Global setup for the test class
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        // This method is called once before any test methods in the class
        // It can be used to set up shared resources or configurations
        // For this test, we do not need to set anything up here

        if (!array_key_exists('xmlschema', $GLOBALS['TestingControl'])) {
            return;
        }
        if (!array_key_exists('skipXmlTests', $GLOBALS['TestingControl']['xmlschema'])) {
            $this->skipFollowingTests = true;
            return;
        }
        $GLOBALS['ADOxmlSchema']  = new adoSchema($GLOBALS['ADOdbConnection']);
    }
    
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

        if (!$GLOBALS['ADOxmlSchema']) {
            $this->skipFollowingTests = true;
            $this->markTestSkipped('ADOxmlSchema is not available');
            return;
        }
        
        $this->xmlSchema = $GLOBALS['ADOxmlSchema'];
        
    }

    /**
     * Test the XML Schema update
     *
     * @return void
     */
    public function testXmlSchemaUpdate(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping XML Schema tests');
            return;
        }
        
        
        $schemaFile = sprintf('%s/DatabaseSetup/xmlshemafile-update.xml', dirname(__FILE__));
        
        $ok = $this->xmlSchema->parseSchema($schemaFile); 
        
        if (!$ok) {
            $this->assertTrue(
                $ok,
                'XML Schema parsing failed'
            );
            $this->markTestSkipped('XML Schema parsing failed');
            $this->skipFollowingTests = true;
            return;
        }

        $ok = $this->xmlSchema->executeSchema(); 
        
        $this->assertSame(
            2,
            $ok,
            'XML Schema creation failed'
        );
    }
    
    /**
     * Test the loaded fields in the table
     *
     * @return void
     */   
    function testLoadedFields(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping XML Schema tests');
            return;
        }
        
        $table = 'testxmltable_1';
        $fields = $this->db->MetaColumns($table);
    
      

        $this->assertNotEmpty(
            $fields,
            'No fields found in the table'
        );
        
        $this->assertArrayHasKey(
            'ID',
            $fields,
            'Field "id" not found in the table'
        );
        
        $this->assertArrayHasKey(
            'VARCHAR_FIELD',
            $fields,
            'Field "varchar_fields" not found in the table'
        );

        $this->assertArrayHasKey(
            'INTEGER_FIELD',
            $fields,
            'Field "integer_fields" not found in the table'
        );

        $this->assertArrayHasKey(
            'DECIMAL_FIELD',
            $fields,
            'Field "decimal_fields" not found in the table'
        );

    }   

    /**
     * Test the XML Schema creation
     *
     * @return void
     */
    public function testXmlSchemaCreation(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping XML Schema tests');
            return;
        }

        $schemaFile = sprintf('%s/DatabaseSetup/xmlshemafile-create.xml', dirname(__FILE__));
         
        
        $ok = $this->xmlSchema->parseSchema($schemaFile); 
        
        if (!$ok) {
            $this->assertTrue(
                $ok,
                'XML Schema parsing failed'
            );
            $this->markTestSkipped('XML Schema parsing failed');
            $this->skipFollowingTests = true;
            return;
        }

        $ok = $this->xmlSchema->executeSchema(); 
        
        $this->assertSame
        (
            true, // Successful operations
            $ok,
            'XML Schema update failed'
        );

    }
 
    /**
     * Test the update fields in the table
     *
     * @return void
     */   
    function testUpdatedFields(): void
    {
        if ($this->skipFollowingTests) {
            $this->markTestSkipped('Skipping XML Schema tests');
            return;
        }
        
        $table = 'testxmltable_1';
        $fields = $this->db->MetaColumns($table);
    
  
        $this->assertArrayNotHasKey(
            'VARCHAR_FIELD_TO_DROP',
            $fields,
            'Field "varchar_field_to_drop" should not be found in the table'
        );

    }   

}