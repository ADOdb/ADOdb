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
// BASIC ADO test
	include_once('../adodb.inc.php');
	$db = ADONewConnection("ado_access");
	$db->debug=1;
	$access = 'd:\inetpub\wwwroot\php\NWIND.MDB';
	$myDSN =  'PROVIDER=Microsoft.Jet.OLEDB.4.0;'
		. 'DATA SOURCE=' . $access . ';';
	echo "<p>PHP ",PHP_VERSION,"</p>";
	$db->Connect($myDSN) || die('fail');
	print_r($db->ServerInfo());
	try {
	$rs = $db->Execute("select $db->sysTimeStamp,* from adoxyz where id>02xx");
	print_r($rs->fields);
	} catch(exception $e) {
	print_r($e);
	echo "<p> Date m/d/Y =",$db->UserDate($rs->fields[4],'m/d/Y');
	}
