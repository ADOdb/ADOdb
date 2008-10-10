<?php
include_once('../adodb.inc.php');
include_once('adodb-replicate.inc.php');

set_time_limit(0);

function IndexFilter($dtable, $idxname,$flds,$options)
{
	if (strlen($idxname) > 28) $idxname = substr($idxname,0,24).rand(1000,9999);
	return $idxname;
}

function SELFILTER($table, &$arr, $delfirst)
{
	return true;
}

function FieldFilter(&$fld,$mode)
{
	$uf = strtoupper($fld);
	switch($uf) {
		case 'GROUP': 
			if ($mode == 'SELECT') $fld = '"Group"';
			return 'GroupFld';
	}
	return $fld;
}


$DB = ADONewConnection('odbtp');
#$ok = $DB->Connect('localhost','root','','northwind');
$ok = $DB->Connect('192.168.0.1','DRIVER={SQL Server};SERVER=(local);UID=sa;PWD=natsoft;DATABASE=OIR;','','');


$DB2 = ADONewConnection('oci8');
$ok2 = $DB2->Connect('192.168.0.2','tnb','natsoft','RAPTOR','');


if (!$ok || !$ok2) die("Failed connection DB=$ok DB2=$ok2<br>");

$tables =
"
# comment1
tblProtType  # id_pt
SelMVFuseType
selFuseSize
netRelay
SysListVolt
sysVoltLevel
sysRestoration
sysRepairMethod
tblRepRepairMethod
tblInterruptionType
sysInterruptionType
tblRepFailureMode
tblRepFailureCause
netTransformer
";

# net* are ERMS, need last updated field from LGBnet
# tblRep* are tables insert or update from Juris, need last updated field also
# The rest are lookup tables, can copy all from LGBnet

$tablesOld = 
"
#
#
tblReport
tblRepRestoration
tblRepResdetail
#
tblRepProtection
tblRepComponent # id_rc
tblRepWeather
tblRepEnvironment
tblRepSubstation
tblInstallationType
tblInstallationCat
tblFailureCause
tblFailureMode
tblProtection  # id_tp
tblComponent
tblProtdetail # id
tblInstallation  
sysComponent
sysCodecibs
sysCodeno
sysProtection
sysEquipment
sysAddress
sysWeather
sysEnvironment
sysPhase
sysFailureCause
sysFailureMode
SysSchOutageMode
SysOutageType
SysInstallation
SysInstallationCat
SysInstallationType
SysFaultCategory
SysResponsible
SysProtectionOperation
netCodename
netSubstation
netLvFeeder";

$tables = explode("\n",$tables);

$rep = new ADODB_Replicate($DB,$DB2);
$rep->fieldFilter = 'FieldFilter';
$rep->selFilter = 'SELFILTER';
$rep->indexFilter = 'IndexFilter';

if (0) {
	$rep->debug = 1;
	$DB->debug=1;
	$DB2->debug=1;
}

$cnt = sizeof($tables);
foreach($tables as $k => $table) {
	$table = trim($table);
	if (strlen($table) == 0) continue;
	if (strpos($table, '#') !== false) {
		$at = strpos($table, '#');
		$table = trim(substr($table,0,$at));
		if (strlen($table) == 0) continue;
	}
	$dtable = '';
	
	$kcnt = $k+1;
	echo "<h1>($kcnt/$cnt) $table</h1>\n";
	flush();@ob_flush();
	
	## CREATE TABLE
	#$DB2->Execute("drop table $table");
	
	$rep->execute = true;
	$ok = $rep->CopyTableStruct($table);
	if ($ok) echo "Table Created<br>\n";
	else {
		echo "<hr>Error: Cannot Create Table<hr>\n";
	}
	flush();@ob_flush();
	
	# COPY DATA
	$rep->execute = true;
	$secs = time();
	$rows = $rep->ReplicateData($table,$dtable);
	$secs = time() - $secs;
	if (!$rows || !$rows[0] || !$rows[1] || $rows[1] != $rows[2]+$rows[3]) {
		echo "<hr>Error: "; var_dump($rows);  echo " (secs=$secs) <hr>\n";
	} else
		echo date('H:i:s'),': ',$rows[1]," record(s) copied, ",$rows[2]," inserted, ",$rows[3]," updated (secs=$secs)<br>\n";
	flush();@ob_flush();
}

echo "<hr>",date('H:i:s'),": Done</hr>";
?>