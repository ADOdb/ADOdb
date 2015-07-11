<?php
/** 
* This is the short description placeholder for the generic file docblock 
* 
* This is the long description placeholder for the generic file docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @category   FIXME
* @package    ADODB 
* @author     John Lim 
* @copyright  2014-      The ADODB project 
* @copyright  2000-2014 John Lim 
* @license    BSD License    (Primary) 
* @license    Lesser GPL License    (Secondary) 
* @version    5.21.0 
* 
* @adodb-filecheck-status: FIXME
* @adodb-codesniffer-status: FIXME
* @adodb-documentor-status: FIXME
* 
*/ 
include_once('../adodb-perf.inc.php');
error_reporting(E_ALL);
session_start();
if (isset($_GET)) {
	foreach($_GET as $k => $v) {
		if (strncmp($k,'test',4) == 0) $_SESSION['_db'] = $k;
	}
}
if (isset($_SESSION['_db'])) {
	$_db = $_SESSION['_db'];
	$_GET[$_db] = 1;
	$$_db = 1;
}
echo "<h1>Performance Monitoring</h1>";
include_once('testdatabases.inc.php');

/** 
* This is the short description placeholder for the function docblock 
*  
* This is the long description placeholder for the function docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @version 5.21.0 
* @param   FIXME 
* @return  FIXME 
* 
* @adodb-visibility  FIXME
* @adodb-function-status FIXME
* @adodb-api FIXME 
*/
function testdb($db)
{
	if (!$db) return;
	echo "<font size=1>";print_r($db->ServerInfo()); echo " user=".$db->user."</font>";
	$perf = NewPerfMonitor($db);
	# unit tests
	if (0) {
		//$DB->debug=1;
		echo "Data Cache Size=".$perf->DBParameter('data cache size').'<p>';
		echo $perf->HealthCheck();
		echo($perf->SuspiciousSQL());
		echo($perf->ExpensiveSQL());
		echo($perf->InvalidSQL());
		echo $perf->Tables();
		echo "<pre>";
		echo $perf->HealthCheckCLI();
		$perf->Poll(3);
		die();
	}
	if ($perf) $perf->UI(3);
}
