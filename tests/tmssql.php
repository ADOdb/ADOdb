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
error_reporting(E_ALL);
ini_set('mssql.datetimeconvert',0);

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
function tmssql()
{
	print "<h3>mssql</h3>";
	$db = mssql_connect('JAGUAR\vsdotnet','adodb','natsoft') or die('No Connection');
	mssql_select_db('northwind',$db);
	$rs = mssql_query('select getdate() as date',$db);
	$o = mssql_fetch_row($rs);
	print_r($o);
	mssql_free_result($rs);
	print "<p>Delete</p>"; flush();
	$rs2 = mssql_query('delete from adoxyz',$db);
	$p = mssql_num_rows($rs2);
	mssql_free_result($rs2);
}

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
function tpear()
{
include_once('DB.php');
	print "<h3>PEAR</h3>";
	$username = 'adodb';
	$password = 'natsoft';
	$hostname = 'JAGUAR\vsdotnet';
	$databasename = 'northwind';
	$dsn = "mssql://$username:$password@$hostname/$databasename";
	$conn = DB::connect($dsn);
	print "date=".$conn->GetOne('select getdate()')."<br>";
	@$conn->query('create table tester (id integer)');
	print "<p>Delete</p>"; flush();
	$rs = $conn->query('delete from tester');
	print "date=".$conn->GetOne('select getdate()')."<br>";
}

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
function tadodb()
{
include_once('../adodb.inc.php');
	print "<h3>ADOdb</h3>";
	$conn = NewADOConnection('mssql');
	$conn->Connect('JAGUAR\vsdotnet','adodb','natsoft','northwind');
//	$conn->debug=1;
	print "date=".$conn->GetOne('select getdate()')."<br>";
	$conn->Execute('create table tester (id integer)');
	print "<p>Delete</p>"; flush();
	$rs = $conn->Execute('delete from tester');
	print "date=".$conn->GetOne('select getdate()')."<br>";
}
$ACCEPTIP = '127.0.0.1';
$remote = $_SERVER["REMOTE_ADDR"];
if (!empty($ACCEPTIP))
 if ($remote != '127.0.0.1' && $remote != $ACCEPTIP)
 	die("Unauthorised client: '$remote'");
?>
<a href=tmssql.php?do=tmssql>mssql</a>
<a href=tmssql.php?do=tpear>pear</a>
<a href=tmssql.php?do=tadodb>adodb</a>
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
if (!empty($_GET['do'])) {
	$do = $_GET['do'];
	switch($do) {
	case 'tpear':
	case 'tadodb':
	case 'tmssql':
		$do();
	}
}
