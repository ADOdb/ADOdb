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
function getmicrotime()
{
	$t = microtime();
	$t = explode(' ',$t);
	return (float)$t[1]+ (float)$t[0];
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
function doloop()
{
global $db,$MAX;
	$sql = "select id,firstname,lastname from adoxyz where
		firstname not like ? and lastname not like ? and id=?";
	$offset = 0;
	/*$sql = "select * from juris9.employee join juris9.emp_perf_plan on epp_empkey = emp_id
		where emp_name not like ? and emp_name not like ? and emp_id=28000+?";
	$offset = 28000;*/
	for ($i=1; $i <= $MAX; $i++) {
		$db->Param(false);
		$x = (rand() % 10) + 1;
		$db->debug= ($i==1);
		$id = $db->GetOne($sql,
			array('Z%','Z%',$x));
		if($id != $offset+$x) {
			print "<p>Error at $x";
			break;
		}
	}
}
include_once('../adodb.inc.php');
$db = NewADOConnection('postgres7');
$db->PConnect('localhost','tester','test','test') || die("failed connection");
$enc = "GIF89a%01%00%01%00%80%FF%00%C0%C0%C0%00%00%00%21%F9%04%01%00%00%00%00%2C%00%00%00%00%01%00%01%00%00%01%012%00%3Bt_clear.gif%0D";
$val = rawurldecode($enc);
$MAX = 1000;
adodb_pr($db->ServerInfo());
echo "<h4>Testing PREPARE/EXECUTE PLAN</h4>";
$db->_bindInputArray = true; // requires postgresql 7.3+ and ability to modify database
$t = getmicrotime();
doloop();
echo '<p>',$MAX,' times, with plan=',getmicrotime() - $t,'</p>';
$db->_bindInputArray = false;
$t = getmicrotime();
doloop();
echo '<p>',$MAX,' times, no plan=',getmicrotime() - $t,'</p>';
echo "<h4>Testing UPDATEBLOB</h4>";
$db->debug=1;
### TEST BEGINS
$db->Execute("insert into photos (id,name) values(9999,'dot.gif')");
$db->UpdateBlob('photos','photo',$val,'id=9999');
$v = $db->GetOne('select photo from photos where id=9999');
### CLEANUP
$db->Execute("delete from photos where id=9999");
### VALIDATION
if ($v !== $val) echo "<b>*** ERROR: Inserted value does not match downloaded val<b>";
else echo "<b>*** OK: Passed</b>";
echo "<pre>";
echo "INSERTED: ", $enc;
echo "<hr />";
echo"RETURNED: ", rawurlencode($v);
echo "<hr /><p>";
echo "INSERTED: ", $val;
echo "<hr />";
echo "RETURNED: ", $v;
