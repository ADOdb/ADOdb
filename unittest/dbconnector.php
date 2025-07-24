<?php
/**
 * Test connection bootstrap file
 *
 * This must be called as part of the phpunit run to expose a database connection
 * The connection must be located in the parent of the ADOdb Source
 * and should be named adodb-connection-<driver>.php 
 * @example adodb-connection-sqlite3.php
 * If the driver is PDO, it should be named adodb-connection-<pdo-driver>.php
 * to call a driver dynamically for testing, use the following syntax:
 * phpunit unittest --bootstrap unittest/dbconnector.php sqlite3
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
require_once dirname(__FILE__) . '/../adodb-lib.inc.php';

global $argv;
global $db;

$shortopts = ''; 
$longopts = array('adodb-driver');
$errors   = '';
$options = getopt($shortopts, $longopts);

$o = (preg_grep('/dbconnector/',$argv));
if (!$o)
	die('unit tests must contain a dbconnector argument');
$o = array_keys($o);
$oIndex = $o[0] + 1;

if (!array_key_exists($oIndex,$argv))
	die('The dbconnector argument must be followed by the name of the driver');

/*
* Match the location of the bootstrap load
* the driver name is the next argument
*/

$adoDriver = strtolower($argv[$oIndex]);

$iniFile = stream_resolve_include_path ('adodb-unittest.ini');

if (!$iniFile)
{
	die ('could not find adodb-unittest.ini in the PHP include_path');
}

$availableCredentials = parse_ini_file($iniFile,true);

if (!isset($availableCredentials[$adoDriver]))
	die('login credentials not available for driver ' . $adoDriver); 

$iniParams = $availableCredentials['globals'];
if (is_array($iniParams))
{
	foreach($iniParams as $key => $value)
	{
				
		ini_set($key,$value);
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


$credentials = array_merge($template,$availableCredentials[$adoDriver]);

$loadDriver = str_replace('pdo-','PDO\\',$adoDriver);

$db = newAdoConnection($loadDriver);
$db->debug = $credentials['debug'];

if ($credentials['parameters'])
{
	$p = explode(';',$credentials['parameters']);
	$p = array_filter($p);
	foreach($p as $param)
	{
		$scp = explode('=',$param);
		if (preg_match('/^[0-9]+$/',$scp[0]))
			$scp[0] = (int)$scp[0];
		if (preg_match('/^[0-9]+$/',$scp[1]))
			$scp[1] = (int)$scp[1];
		
		$db->setConnectionParameter($scp[0],$scp[1]);
	}
}

if ($credentials['dsn'])
	$db->connect($credentials['dsn']);
else
	$db->connect(
		$credentials['host'],
		$credentials['user'],
		$credentials['password'],
		$credentials['database']
	);

if (!$db->isConnected()) {
	die(sprintf('%s database connection not established',$adoDriver));
}

/*
* This is now available to unittests
*/
$GLOBALS['ADOdbConnection'] = $db;
$GLOBALS['ADOdriver']       = $adoDriver;
$GLOBALS['TestingControl']  = $availableCredentials;

$db->startTrans();

$table1Schema = sprintf('%s/DatabaseSetup/%s/table1-schema.sql',dirname(__FILE__),$adoDriver);

if (!file_exists($table1Schema))
	die('Schema file for table 1 not found');

$table1Sql = file_get_contents($table1Schema);
$t1Sql = explode(';',$table1Sql);

foreach($t1Sql as $sql)
{
	if (trim($sql ?? ''))
		$db->execute($sql);
}
$db->completeTrans();

$db->startTrans();
/*
* Load Data into the table
*/
$table1Data = sprintf('%s/DatabaseSetup/table1-data.sql',dirname(__FILE__));
if (!file_exists($table1Data))
	die('Data file for table 1 not found');

$table1Sql = file_get_contents($table1Data);
$t1Sql = explode(';',$table1Sql);
foreach($t1Sql as $sql)
	if (trim($sql ?? ''))
		$db->execute($sql);

$db->completeTrans();
