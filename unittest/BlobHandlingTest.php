<?php
/**
 * Tests cases for variables and constants of ADODb
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
 * Class BlobHandlingTest
 *
 * Test cases for for ADOdb MetaFunctions
 */
class BlobHandlingTest extends TestCase
{
    protected ?object $db;
    protected ?string $adoDriver;
    protected ?object $dataDictionary;

    protected bool $skipFollowingTests = false;

    protected  $testBlobFile;


    protected  $testTableName;

    /**
     * Set up the test environment
     *
     * This method is called once before any tests are run.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
    
    
        if (!array_key_exists('testBlob', $GLOBALS['TestingControl']['blob'])) {
            return;
        }

        $testBlobFile = $GLOBALS['TestingControl']['blob']['testBlob'];
        if (!$testBlobFile) {
            return;
        }
        if (!file_exists($testBlobFile)) {
            return;
        }

        $GLOBALS['ADOdbConnection']->startTrans();
        
        $sql = "INSERT INTO testtable_2 (integer_field, date_field,blob_field)
                     VALUES (9002,'2025-02-01',null)";
       
       
        $GLOBALS['ADOdbConnection']->Execute($sql);

        $GLOBALS['ADOdbConnection']->completeTrans();
    }
    
    
    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setup(): void
    {

        $this->db        = $GLOBALS['ADOdbConnection'];
        $this->adoDriver = $GLOBALS['ADOdriver'];
    
        if (!array_key_exists('testBlob', $GLOBALS['TestingControl']['blob'])) {
            $this->skipFollowingTests = true;
            $this->markTestSkipped(
                'The testBlob setting is not defined in the adodb-unittest.ini file'
            );
        }

        $this->testBlobFile = $GLOBALS['TestingControl']['blob']['testBlob'];
    
        if (!$this->testBlobFile) {
            $this->skipFollowingTests = true;
            $this->markTestSkipped(
                'Blob sets will be skipped'
            );
        }

        if (!file_exists($this->testBlobFile)) {
            $this->skipFollowingTests = true;
            $this->markTestSkipped(
                'The testBlob file does not exist: ' . $this->testBlobFile
            );
        }

        static $testTableName = false;

        if ($testTableName) {
            $this->testTableName = $testTableName;
            return;
        }
        
    }
    
  
    /**
     * Test for {@see updateBlob}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:blobEncode
     * 
     * @return void
     */
    public function testBlobEncode(): void
    {
       
        if ($this->skipFollowingTests) {
            return;
        }

        $this->db->startTrans();

        $fd   = file_get_contents($this->testBlobFile);
        $blob = $this->db->blobEncode($fd);

        $hasData = strlen($blob) > 0;

        $this->assertSame(
            true,
            $hasData,
            'Blob encoding should not return an empty string'
        );

        $this->db->completeTrans();
    }

    /**
     * Test for {@see updateBlob}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:updateblob
     * 
     * @return void
     */
    public function testUpdateBlob(): void
    {
       
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping testUpdateBlob as the testBlob setting is not defined in the adodb-unittest.ini file'
            );
            return;
        }
   
        $saveDebug = $this->db->debug;

        $this->db->debug = false; // Disable debug output for this test
        $fd = file_get_contents($this->testBlobFile);
        $blob = $this->db->blobEncode($fd);

        $this->db->startTrans();
        
        $result = $this->db->updateBlob(
            $this->testTableName, 
            'blob_field', 
            $blob, 
            'integer_field=1'
        );

        $this->db->completeTrans();

        $this->db->debug = $saveDebug; // Restore debug setting

        $this->assertTrue(
            $result,
            'updateBlob should return true on success'
        );

    }

    /**
     * Test for {@see updateBlobFile}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:updateblobfile
     * 
     * @return void
     */
    public function testUpdateBlobFile(): void
    {
       
        if ($this->skipFollowingTests) {
            $this->markTestSkipped(
                'Skipping testUpdateBlob as the testBlob setting is not defined in the adodb-unittest.ini file'
            );
            return;
        }
   
        $this->db->startTrans();

        $result = $this->db->updateBlobFile(
            $this->testTableName, 
            'blob_field', 
            $this->testBlobFile,
            'integer_field=1' 
        );

        $this->db->completeTrans();

        $this->assertTrue(
            $result,
            'updateBlob should return true on success'
        );

    }


    
    /**
     * Test for {@see updateBlob}
     * 
     * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:blobDecode
     * 
     * @return void
     */
    public function testBlobDecode(): void
    {
       
        if ($this->skipFollowingTests) {
            return;
        }

        $newFileArray = explode('.', $this->testBlobFile);
        $extension = array_pop($newFileArray);
        $newFile = implode('.', $newFileArray) . '-decoded' . $extension;
       
        
        $SQL = "SELECT LENGTH(BLOB_FIELD) 
                  FROM  {$this->testTableName} 
                 WHERE INTEGER_FIELD=1";

        
        $blobLength = $this->db->getOne($SQL);
        $this->assertGreaterThan(
            0,
            $blobLength,
            'The blob field should contain data'
        );
        

        $SQL = "SELECT BLOB_FIELD 
                  FROM {$this->testTableName} 
                 WHERE INTEGER_FIELD=1";
        
        $blob = $this->db->blobDecode($this->db->getOne($SQL));
        


        file_put_contents(
            $newFile, 
            $blob
        );

        /*
        * Do some filesystem checks
        */
        $originalFileSize = filesize($this->testBlobFile);
        $decodedFileSize  = filesize($newFile);

        
        $this->assertSame(
            $originalFileSize,
            $decodedFileSize,
            'Decoded file size should match the original file size'
        );
    }
}