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
class ADOdbTestCase extends TestCase
{
    protected ?object $db;
    protected ?string $adoDriver;
    protected ?object $dataDictionary;

    protected bool $skipFollowingTests = false;
    protected bool $skipAllTests       = false;

    protected string $testTableName = 'testtable_1';
    protected string $testIndexName1 = 'insertion_index_1';
    protected string $testIndexName2 = 'insertion_index_2';

    
    /**
     * Instantiates new ADOdb connection to flush every test
     *
     * @return object
     */
    public function establishDatabaseConnector() : object
    {
        
        $template = array(
            'dsn'=>'',
            'host'=>null,
            'user'=>null,
            'password'=>null,
            'database'=>null,
            'parameters'=>null,
            'debug'=>0
        );


        $credentials = array_merge(
            $template, 
            $GLOBALS['TestingControl'][$GLOBALS['loadDriver']]
        );

        $loadDriver = str_replace('pdo-', 'PDO\\', $GLOBALS['loadDriver']);

        $db = newAdoConnection($loadDriver);
        $db->debug = $credentials['debug'];

        if ($credentials['parameters']) {

            $p = explode(';', $credentials['parameters']);
            $p = array_filter($p);
            foreach ($p as $param) {
                $scp = explode('=', $param);
                if (preg_match('/^[0-9]+$/', $scp[0]))
                    $scp[0] = (int)$scp[0];
                if (preg_match('/^[0-9]+$/', $scp[1]))
                    $scp[1] = (int)$scp[1];
                
                $db->setConnectionParameter($scp[0], $scp[1]);
            }
        }

        if ($credentials['dsn']) {
            $db->connect(
                $credentials['dsn'],
                $credentials['user'],
                $credentials['password'],
                $credentials['database']
            );
        } else {
            $db->connect(
                $credentials['host'],
                $credentials['user'],
                $credentials['password'],
                $credentials['database']
            );
        }

        if (!$db->isConnected()) {
            die(
                sprintf(
                    '%s database connection not established', 
                    $GLOBALS['adoDriver']
                )
            );
        }

        return $db;
    }

    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setup(): void
    {

        $this->db             = $this->establishDatabaseConnector();
        $this->adoDriver      = $GLOBALS['ADOdriver'];
        $this->dataDictionary = NewDataDictionary($this->db);

        
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
     * Exwcutes an SQL statement within a transaction and returns 
     * the result plus any message if it fails
     *
     * @param string     $sql  The SQL to execute
     * @param array|null $bind Optional bind parameters
     * 
     * @return array
     */
    public function executeSqlString(string $sql, ?array $bind=null) : array
    {
        $db = $this->db;
        
        $db->startTrans();

        if ($bind) {
            $result = $db->execute($sql, $bind);
        } else {
            $result = $db->execute($sql);
        }

        $errno  = $db->errorNo();
        $errmsg = $db->errorMsg();

        $db->completeTrans();

        $params = '';
        if ($bind) {
            $params = ' [' . implode(' , ', $bind) . ']';
        }

        $this->assertEquals(
            0,
            $errno,
            sprintf(
                'ADOdb string execution of SQL %s%s should not return error: %d - %s',
                $sql,
                $params,
                $errno,
                $errmsg
            )    
        );

        return array($result,$errno,$errmsg);

    }

    /**
     * Tests an ADOdb execution for db errors
     *
     * @param string $sql The statement executed
     * 
     * @return array
     */
    public function assertADOdbError(string $sql, ?array $bind=null) : array
    {

      
        $db = $this->db;

        $errno  = $db->errorNo();
        $errmsg = $db->errorMsg();

        $db->_errorCode = 0;
        $db->_errorMsg = '';

        $transOff = $db->transOff;

        
        $params = '';
        if ($bind) {
            $params = ' [' . implode(' , ', $bind) . ']';
        }

        $this->assertEquals(
            0,
            $errno,
            sprintf(
                'ADOdb execution of SQL %s%s should not return error: %d - %s',
                $sql,
                $params,
                $errno,
                $errmsg
            )    
        );

        if ($GLOBALS['globalTransOff'] < $transOff) {

            $this->assertTrue(
                $transOff < 2,
                sprintf(
                    '$transOff should not exceed 1 in test suite, currently %d, previously %d',
                    $transOff,
                    $GLOBALS['globalTransOff']
                )
            );
         
        }
        $GLOBALS['globalTransOff'] = $transOff;
        
        return array($errno,$errmsg);


    }

    /**
     * Exwcutes an SQL statement within a transaction and returns 
     * the result plus any message if it fails
     *
     * @param array      $sqlArray The SQL to execute
     * @param array|null $bind     Optional bind parameters
     * 
     * @return void
     */
    public function executeDictionaryAction(array $sqlArray, ?array $bind=null) : array
    {
        $db = $this->db;
        $dictionary = $this->dataDictionary;
        
        $db->startTrans();

        if ($bind) {
            $result = $dictionary->executeSqlArray($sqlArray, $bind);
        } else {
            $result = $dictionary->executeSqlArray($sqlArray);
        }

        $errno  = $db->errorNo();
        $errmsg = $db->errorMsg();

        $db->completeTrans();

        
        $params = '';
        if ($bind) {
            $params = ' [' . implode(' , ', $bind) . ']';
        }

        $this->assertEquals(
            0,
            $errno,
            sprintf(
                'ADOdb array execution of SQL %s%s should not return error: %d - %s',
                implode('/', $sqlArray),
                $params,
                $errno,
                $errmsg
            )    
        );

        return array($result,$errno,$errmsg);

    }
}