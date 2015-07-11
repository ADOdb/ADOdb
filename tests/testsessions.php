<?php
/** 
* This is the short description placeholder for the generic file docblock 
* 
* This is the long description placeholder for the generic file docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @author     John Lim 
* @copyright  2014-      The ADODB project 
* @copyright  2000-2014 John Lim 
* @license    BSD License    (Primary) 
* @license    Lesser GPL License    (Secondary) 
* @version    5.21.0 
* @package    ADODB 
* @category   FIXME 
* 
* @adodb-filecheck-status: FIXME
* @adodb-codesniffer-status: FIXME
* @adodb-documentor-status: FIXME
* 
*/ 
/*
V5.20dev  ??-???-2014  (c) 2000-2014 John Lim (jlim#natsoft.com). All rights reserved.
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.
  Set tabs to 4 for best viewing.
  Latest version is available at http://adodb.sourceforge.net
*/

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
function NotifyExpire($ref,$key)
{
	print "<p><b>Notify Expiring=$ref, sessionkey=$key</b></p>";
}
//-------------------------------------------------------------------
error_reporting(E_ALL);
ob_start();
include('../session/adodb-cryptsession2.php');
$options['debug'] = 1;
$db = 'postgres';
#### CONNECTION
switch($db) {
case 'oci8':
	$options['table'] = 'adodb_sessions2';
	ADOdb_Session::config('oci8', 'mobydick', 'jdev', 'natsoft', 'mobydick',$options);
	break;
case 'postgres':
	$options['table'] = 'sessions2';
	ADOdb_Session::config('postgres', 'localhost', 'postgres', 'natsoft', 'northwind',$options);
	break;
case 'mysql':
default:
	$options['table'] = 'sessions2';
	ADOdb_Session::config('mysql', 'localhost', 'root', '', 'xphplens_2',$options);
	break;
}
#### SETUP NOTIFICATION
	$USER = 'JLIM'.rand();
	$ADODB_SESSION_EXPIRE_NOTIFY = array('USER','NotifyExpire');
	adodb_session_create_table();
	session_start();
	adodb_session_regenerate_id();
### SETUP SESSION VARIABLES
	if (empty($_SESSION['MONKEY'])) $_SESSION['MONKEY'] = array(1,'abc',44.41);
	else $_SESSION['MONKEY'][0] += 1;
	if (!isset($_GET['nochange'])) @$_SESSION['AVAR'] += 1;
### START DISPLAY
	print "<h3>PHP ".PHP_VERSION."</h3>";
	print "<p><b>\$_SESSION['AVAR']={$_SESSION['AVAR']}</b></p>";
	print "<hr /> <b>Cookies</b>: ";
	print_r($_COOKIE);
	var_dump($_SESSION['MONKEY']);
### RANDOMLY PERFORM Garbage Collection
### In real-production environment, this is done for you
### by php's session extension, which calls adodb_sess_gc()
### automatically for you. See php.ini's
### session.cookie_lifetime and session.gc_probability
	if (rand() % 5 == 0) {
		print "<hr /><p><b>Garbage Collection</b></p>";
		adodb_sess_gc(10);
		if (rand() % 2 == 0) {
			print "<p>Random own session destroy</p>";
			session_destroy();
		}
	} else {
		$DB = ADODB_Session::_conn();
		$sessk = $DB->qstr('%AZ'.rand().time());
		$olddate = $DB->DBTimeStamp(time()-30*24*3600);
		$rr = $DB->qstr(rand());
		$DB->Execute("insert into {$options['table']} (sesskey,expiry,expireref,sessdata,created,modified) values ($sessk,$olddate, $rr,'',$olddate,$olddate)");
	}
