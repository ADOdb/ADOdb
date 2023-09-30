<?php
include '../adodb.inc.php';
$default_timezone = 'UTC';
date_default_timezone_set($default_timezone);
$driver = 'sqlite3';
$db = adoNewConnection($driver);
$server = '';
$user = '';
$password = '';
$database = 'sqltest';
$db->connect($server, $user, $password, $database);

//==========================
// This code tests sqlite access
//==========================
$sql = 'select * from testtable order by id limit 4';
$rs = $db->execute($sql);
$actual = $rs->getRows();
$expected = array(
	array(
	  0 => 5,
	  'id' => 5,
	  1 => 'DEE658E8D9ACEB94A1E554CF7EDA6944',
	  'description' => 'DEE658E8D9ACEB94A1E554CF7EDA6944',
	  2 => '1728-02-28 09:17:52',
	  'whenithappened' => '1728-02-28 09:17:52',
	),
	array(
	  0 => 6,
	  'id' => 6,
	  1 => '367B5BAE40BEB89BA5AB22DFD79C93ED',
	  'description' => '367B5BAE40BEB89BA5AB22DFD79C93ED',
	  2 => '1933-05-14 01:29:16',
	  'whenithappened' => '1933-05-14 01:29:16',
	),
	array(
	  0 => 7,
	  'id' => 7,
	  1 => '4F67DEF4750E6B25FA339CAA10A82A6E',
	  'description' => '4F67DEF4750E6B25FA339CAA10A82A6E',
	  2 => '2071-06-19 15:05:25',
	  'whenithappened' => '2071-06-19 15:05:25',
	),
	array(
	  0 => 8,
	  'id' => 8,
	  1 => 'C0EB1A398C56D7CE70A04E702A43E402',
	  'description' => 'C0EB1A398C56D7CE70A04E702A43E402',
	  2 => '1667-06-05 17:23:17',
	  'whenithappened' => '1667-06-05 17:23:17',
	),
);
print "Test sqlite access first 4 rows: " . htmlspecialchars($sql);
testAssert($rs, $actual, $expected);

//==========================
// This code tests SQLDate
//==========================
$tests = [
	'',
	'1974-02-25',
	'1474-02-25 05:04:16',
	'2474-02-25 05:04:16',
];
foreach ($tests as $dt) {
	testDateFormatting($dt, false);
}
function testDateFormatting($dt, $debug = false) {
	global $db;
	if ($dt=='') {
		$date = $db->SQLDate('%d-%m-%Y Q %H:%M:%S');
	} else {
		$date = $db->SQLDate('%d-%m-%Y Q %H:%M:%S', $db->qStr($dt));
	}
	$sql = "SELECT $date";
	print "Test SQLDate: " . htmlspecialchars($sql);
	$db->debug = $debug;
	$rs = $db->SelectLimit($sql, 1);
	if ($dt=='') {
		$d = date('d-m-Y') . ' Q ' . date('H:i:s');
	} else {
		$ts = ADOConnection::UnixTimeStamp($dt);
		$d = date('d-m-Y', $ts) . ' Q ' . date('H:i:s', $ts);
	}
	testAssert($rs, reset($rs->fields), $d);
}

//==========================
// These are helper functions
//==========================

function Err($msg) {
	print "$msg\n";
	flush();
}

function Trace($Msg) {
	echo "\n".$Msg;
}

function DieTrace($Msg) {
	die("\n".$Msg);
}

function testAssert($rs, $actual, $expected) {
	global $db;
	if (!$rs) {
		Err("SQLDate query returned no recordset");
		echo $db->ErrorMsg(), "\n";
	} elseif ($expected != $actual) {
		Err("SQLDate 2 failed expected: \nact:$expected \nsql:" . $rs->fields[0] . " \n" . $db->ErrorMsg());
	} else {
		echo "  \u{2713}\n";
	}
}