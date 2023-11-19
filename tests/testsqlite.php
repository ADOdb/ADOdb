<?php
include '../adodb.inc.php';
// set up timezone
$default_timezone = 'UTC';
date_default_timezone_set($default_timezone);
$database = 'sqltest.sqlite';

// Create SQLITE test database
$slitedb = new SQLite3($database);

// connect to test database
$driver = 'sqlite3';
$db = adoNewConnection($driver);
$server = '';
$user = '';
$password = '';
$db->connect($server, $user, $password, $database);

// drop table if it exits
$rs = $db->execute('drop table testtable;');

// create test table
$sql = 'CREATE TABLE IF NOT EXISTS testtable (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	description TEXT,
	whenithappened DATETIME
)';
$rs = $db->execute($sql);

// populate test table
$db->execute("INSERT INTO 'testtable' VALUES (5,'DEE658E8D9ACEB94A1E554CF7EDA6944','1728-02-28 09:17:52');");
$db->execute("INSERT INTO 'testtable' VALUES (6,'367B5BAE40BEB89BA5AB22DFD79C93ED','1933-05-14 01:29:16');");
$db->execute("INSERT INTO 'testtable' VALUES (7,'4F67DEF4750E6B25FA339CAA10A82A6E','2071-06-19 15:05:25');");
$db->execute("INSERT INTO 'testtable' VALUES (8,'C0EB1A398C56D7CE70A04E702A43E402','1667-06-05 17:23:17');");
$db->execute("INSERT INTO 'testtable' VALUES (9,'9A4E57224F44AC0799076C91062F9485','1453-06-29 03:05:28');");
$db->execute("INSERT INTO 'testtable' VALUES (10,'85B5837F5A27A11070CAFA199D4F0B1B','2073-07-30 00:18:08');");
$db->execute("INSERT INTO 'testtable' VALUES (11,'1D23F47779EB50AE8E1E1A83EE85AAF6','1819-02-12 19:24:06');");
$db->execute("INSERT INTO 'testtable' VALUES (12,'30AA2C1946647145CC1F600D933A629A','1961-11-03 08:02:49');");
$db->execute("INSERT INTO 'testtable' VALUES (13,'EB1ACD052F65CBC9AEBC7555A54FD2F8','1707-09-30 21:01:26');");
$db->execute("INSERT INTO 'testtable' VALUES (14,'AAAA1087D8579A32431E2A88D3C1BCF2','1444-12-03 09:21:53');");
$db->execute("INSERT INTO 'testtable' VALUES (15,'A4CAA5F87768299D2E54E66B7A1D826C','2007-09-01 03:35:32');");
$db->execute("INSERT INTO 'testtable' VALUES (16,'9F77801A55697494BF71F79372A42D68','1502-10-21 12:55:00');");
$db->execute("INSERT INTO 'testtable' VALUES (17,'603D8088154FE3B5630402F03F4239D4','1846-05-23 09:08:53');");
$db->execute("INSERT INTO 'testtable' VALUES (18,'2D1A9307C08ADA0D4343ABCEC445DC4B','1503-05-24 19:46:01');");
$db->execute("INSERT INTO 'testtable' VALUES (19,'495A03828CA0DF1051313EF9393B1C04','1671-04-05 17:47:42');");
$db->execute("INSERT INTO 'testtable' VALUES (20,'8A80F450A8B3FE1F2CE18644282614FC','1757-02-16 21:14:58');");
$db->execute("INSERT INTO 'testtable' VALUES (21,'0C68910EAF1785B8999C2A2F4308AD28','1973-02-01 20:35:10');");
$db->execute("INSERT INTO 'testtable' VALUES (22,'91A44DE32E0A49A7E5619FE244EB78E8','1924-06-03 03:24:22');");
$db->execute("INSERT INTO 'testtable' VALUES (23,'6588C425286C15465DD68E6459BB0037','1614-04-10 19:12:42');");
$db->execute("INSERT INTO 'testtable' VALUES (24,'256F51DEED8907B0CE55291E83A47345','1602-08-31 02:37:22');");
$db->execute("INSERT INTO 'testtable' VALUES (25,'00760C3E69F078DFD635ECE2C70141D8','2091-03-13 03:59:41');");
$db->execute("INSERT INTO 'testtable' VALUES (26,'98F58B63EC27E489045318334719D453','1490-09-06 02:50:04');");

//==========================
// This code tests sqlite access. It also validates that the commands above worked!
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
		echo "\n";
		Err("SQLDate query returned no recordset");
		echo $db->ErrorMsg(), "\n";
	} elseif ($expected != $actual) {
		echo "\n";
		Err("SQLDate failed\nexpected: $expected\nact:$actual\nerror: " . $db->ErrorMsg());
	} else {
		echo "  \u{2713}\n";
	}
}