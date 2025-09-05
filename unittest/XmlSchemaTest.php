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
class XmlSchemaTest extends ADOdbTestCase
{
    protected ?object $xmlSchema;

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
        
        //parent::setUpBeforeClass();
        
        // This method is called once before any test methods in the class
        // It can be used to set up shared resources or configurations
        // For this test, we do not need to set anything up here

        if (!array_key_exists('xmlschema', $GLOBALS['TestingControl'])) {
            return;
        }
        
       
       // $GLOBALS['ADOdbConnection']->transOff = 0;

        $GLOBALS['ADOdbConnection']->startTrans();
        $GLOBALS['ADOdbConnection']->execute("DROP TABLE IF EXISTS testxmltable_1");
        $GLOBALS['ADOdbConnection']->completeTrans();

    }
    
    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setup(): void
    {

        parent::setup();
       
        if (!$GLOBALS['ADOxmlSchema']) {
            $this->skipFollowingTests = true;
            $this->markTestSkipped('ADOxmlSchema is not available');
            return;
        }
      
        
         $this->xmlSchema = new adoSchema($this->db);

       
        
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

        $schemaFile = sprintf('%s/DatabaseSetup/xmlschemafile-create.xml', dirname(__FILE__));
  
        
        $ok = $this->xmlSchema->parseSchema($schemaFile); 
        
        if (!$ok) {
            $this->assertTrue(
                $ok,
                'XML Schema Creation File parsing failed'
            );
            $this->markTestSkipped('XML Schema Creation parsing failed');
            $this->skipFollowingTests = true;
            return;
        }


        $ok = $this->xmlSchema->executeSchema(); 
        list($errno, $errmsg) = $this->assertADOdbError('xml->executeSchema()');

        $this->assertSame
        (
            2, // Successful operations
            $ok,
            'XML Schema Creation failed'
        );
        if ($ok !== 2) {
            $this->markTestSkipped('Schema File Creation failed, skipping XML Schema tests');
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

        /**
         * Test the XML Schema update
         *
         */
     
        $schemaFile = sprintf('%s/DatabaseSetup/xmlschemafile-update.xml', dirname(__FILE__));
        $this->assertFileExists(
            $schemaFile,
            'Schema file does not exist: ' . $schemaFile
        );


        $ok = $this->xmlSchema->parseSchema($schemaFile); 
        list($errno, $errmsg) = $this->assertADOdbError('xml->parseSchema()');

        if (!$ok) {
            $this->assertTrue(
                $ok,
                'XML Schema parsing for table update failed'
            );
            $this->markTestSkipped('XML Schema parsing failed updating table');
            $this->skipFollowingTests = true;
            return;
        }

        $ok = $this->xmlSchema->executeSchema(); 
        list($errno, $errmsg) = $this->assertADOdbError('xml->executeSchema()');

        $this->assertSame(
            2,
            $ok,
            'XML Schema update failed after calling executeSchema()'
        );
      
        /**
        * Test the update fields in the table
        */
        
        $table = 'testxmltable_1';
        $fields = $this->db->MetaColumns($table);
    
  
        $this->assertArrayNotHasKey(
            'VARCHAR_FIELD_TO_DROP',
            $fields,
            'Field "varchar_field_to_drop" should not be found in the table'
        );

    }   

}