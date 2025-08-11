<?php
/**
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 * This is the unittest connection bootstrap file
 *
 * @package    ADOdb
 * @subpackage unittest 
 * @copyright  2000-2013 John Lim
 * @copyright  2014 Damien Regad, Mark Newnham and the ADOdb community
 * 
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v2.1 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-2.1-or-later
 *
 * This must be called as part of the phpunit run to expose a database connection
 * The connection must be located in the parent of the ADOdb Source
 * and should be named adodb-connection-<driver>.php 
 * If the driver is PDO, it should be named adodb-connection-<pdo-driver>.php
 * to call a driver dynamically for testing, use the following syntax:
 * phpunit unittest --bootstrap unittest/dbconnector.php sqlite3
 *
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 */

use PHPUnit\Framework\TestCase;

/**
 * Reads an external SQL file and executes its statements against the database.
 * The format of the SQL file must match the database's SQL dialect.
 *
 * @param ADOConnection $db The database connection object.
 * @param string $fileName The name of the SQL file to read.
 * 
 * @return void
 */
function readSqlIntoDatabase(ADOConnection $db, string $fileName) : void
{
    
    if (!file_exists($fileName)) {
        die('Schema ' . $fileName . ' file for unit testing not found');
    }
    $filePointer = fopen($fileName, 'r');
    $executionPoint = '';
    while ($filePointer && !feof($filePointer)) {
        $line = fgets($filePointer);
        $line = trim($line);
        
        if (!$line) {
            continue; // skip empty lines
        }
        
        if (substr($line, 0, 2) === '--') {
            continue; // skip comments
        }   

        $executionPoint .= $line . ' ';
        if (preg_match('/;$/', $line)) {
            $executionPoint = trim($executionPoint, ';');
            if ($executionPoint) {
                $db->execute($executionPoint);
            }
            $executionPoint = ''; // reset for the next statement
            continue; 
        }
        $executionPoint .= ' ';
        
    }
    fclose($filePointer);
}


$iniFile = stream_resolve_include_path ('adodb-unittest.ini');

if (!$iniFile) {
    die('could not find adodb-unittest.ini in the PHP include_path');
}

$availableCredentials = parse_ini_file($iniFile, true);

if (!array_key_exists('ADOdb', $availableCredentials)) {
    /* 
    * If the ADOdb section is not present, we assume the directory is the 
    * parent of the current directory
    */
    $availableCredentials['ADOdb'] = array(
        'directory' => dirname(__DIR__),
        'casing' => 1, // 1= Upper Case
        
    );
}

$ADOdbSettings        = $availableCredentials['ADOdb'];
if (!array_key_exists('casing', $ADOdbSettings)) {
    $ADOdbSettings['casing'] = 1; // 1= Upper Case
    $availableCredentials['ADOdb']['casing'] = 1;
}

if (!array_key_exists('blob', $availableCredentials)) {
    die('blob section not found in adodb-unittest.ini. See the documentation for details on how to set this up');
}

require_once $ADOdbSettings['directory'] . '/adodb.inc.php';
require_once $ADOdbSettings['directory'] . '/adodb-xmlschema03.inc.php';

global $argv;
global $db;

$adoDriver = '';

define('ADODB_ASSOC_CASE', $ADOdbSettings['casing']);


/*
* First try to use the active flag in the ini file because
* Version 12 of PHPUnit does not support the unnammed parameters
*/
foreach ($availableCredentials as $driver=>$driverOptions) {
    if (isset($driverOptions['active']) && $driverOptions['active']) {
        $adoDriver = $driver;
        break;
    }
}

//if (!$adoDriver) {
 
    $o = (preg_grep('/dbconnector/', $argv));

    if ($o) {
        //die('unit tests must contain either an entry in the INI file or a dbconnector argument');
    //}

    /*
    * See if there is an unnamed parameter
    */
    $o = array_keys($o);
    $oIndex = $o[0] + 1;

    //if (!array_key_exists($oIndex, $argv)) {
      //  die('The dbconnector argument must be followed by the name of the driver');
    //}
    /*
    * Match the location of the bootstrap load
    * the driver name is the next argument
    */

    $adoDriver = strtolower($argv[$oIndex] ?? '');

    unset($argv[$oIndex]);
    }

//}

/*
* At the point we either have a driver via the active flog or the command line
*/

if (!isset($availableCredentials[$adoDriver])) {
    die('login credentials not available for driver ' . $adoDriver); 
}

/*
* Push global settings into the ini file
*/
$iniParams = $availableCredentials['globals'];
if (is_array($iniParams)) {
    foreach ($iniParams as $key => $value) {
                
        ini_set($key, $value);
    }
}


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
    $availableCredentials[$adoDriver]
);

$loadDriver = str_replace('pdo-', 'PDO\\', $adoDriver);

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
    die(sprintf('%s database connection not established', $adoDriver));
}

/*
* This is now available to unittests. The caching section will need this info
*/
$GLOBALS['ADOdbConnection'] = &$db;
$GLOBALS['ADOdriver']       = $adoDriver;
$GLOBALS['ADOxmlSchema']    = false;
$GLOBALS['TestingControl']  = $availableCredentials;

//$db->startTrans();

$tableSchema = sprintf(
    '%s/DatabaseSetup/%s/table-schema.sql', 
    dirname(__FILE__), 
    $adoDriver
);

readSqlIntoDatabase($db, $tableSchema);


/*
* Reads common format data and nserts it into the database
*/
$table3Data = sprintf('%s/DatabaseSetup/table3-data.sql', dirname(__FILE__));

readSqlIntoDatabase($db, $table3Data);



/*
* Set up the data dictionary
*/
$GLOBALS['ADOdataDictionary'] = NewDataDictionary($db);

$ADODB_CACHE_DIR = '';
if (array_key_exists('caching', $availableCredentials)) {   

    $cacheParams = $availableCredentials['caching'];
    switch ($cacheParams['cacheMethod'] ?? 0) {
    case 1:
        $ADODB_CACHE_DIR = $cacheParams['cacheDir'] ?? '';
        break;
    case 2:
        $db->memCache     = true;
        $db->memCacheHost = $cacheParams['cacheHost'];
        $db->memCachePort = 11211;
        break;
    }
}

/**
 * Set some global variables for the tests
 */
$ADODB_QUOTE_FIELDNAMES = false;
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
$ADODB_GETONE_EOF = null;
$ADODB_COUNTRECS = true;