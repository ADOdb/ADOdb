<?php
include_once('../adodb.inc.php');
include_once('adodb-replicate.inc.php');

set_time_limit(0);

function IndexFilter($dtable, $idxname,$flds,$options)
{
	if (strlen($idxname) > 28) $idxname = substr($idxname,0,24).rand(1000,9999);
	return $idxname;
}

function SelFilter($table, &$arr, $delfirst)
{
	return true;
}

function FieldFilter(&$fld,$mode)
{
	$uf = strtoupper($fld);
	switch($uf) {
		case 'SIZEFLD':
			break;
		case 'GROUPFLD':
			break;
		case 'GROUP': 
			if ($mode == 'SELECT') $fld = '"Group"';
			return 'GroupFld';
		case 'SIZE': 
			if ($mode == 'SELECT') $fld = '"Size"';
			return 'SizeFld';
	}
	return $fld;
}

function ParseTable(&$table, &$pkey)
{
	$table = trim($table);
	if (strlen($table) == 0) return false;
	if (strpos($table, '#') !== false) {
		$at = strpos($table, '#');
		$table = trim(substr($table,0,$at));
		if (strlen($table) == 0) return false;
	}
	
	$tabarr = explode(',',$table);
	if (sizeof($tabarr) == 1) {
		$table = $tabarr[0];
		$pkey = '';
		echo "No primary key for $table ****  **** <br>";
	} else {
		$table = trim($tabarr[0]);
		$pkey = trim($tabarr[1]);
		if (strpos($pkey,' ') !== false) echo "Bad PKEY for $table $pkey<br>";
	}
	
	return true;
}

function CreateTable($rep, $table)
{
## CREATE TABLE
	#$DB2->Execute("drop table $table");
	
	$rep->execute = true;
	$ok = $rep->CopyTableStruct($table);
	if ($ok) echo "Table Created<br>\n";
	else {
		echo "<hr>Error: Cannot Create Table<hr>\n";
	}
	flush();@ob_flush();
}

function CopyData($rep, $table, $pkey)
{
	$dtable = $table;
	
	$rep->execute = true;
	$rep->deleteFirst = true;
	
	$secs = time();
	$rows = $rep->ReplicateData($table,$dtable,$pkey);
	$secs = time() - $secs;
	if (!$rows || !$rows[0] || !$rows[1] || $rows[1] != $rows[2]+$rows[3]) {
		echo "<hr>Error: "; var_dump($rows);  echo " (secs=$secs) <hr>\n";
	} else
		echo date('H:i:s'),': ',$rows[1]," record(s) copied, ",$rows[2]," inserted, ",$rows[3]," updated (secs=$secs)<br>\n";
	flush();@ob_flush();
}

function MergeData($rep, $table, $pkey)
{
	$dtable = $table;
	$rep->MergeSrcSetup($table, $pkey,'UpdatedOn','CopyFlag');
	$ignoreflds = '';
	$set = '';
	$ok = $rep->Merge($table, $dtable, $pkey, $ignoreflds, $set, 'UpdatedOn','CopyFlag',array('Y','N'), 'CopyDate');
	var_dump($ok);
}

$DB = ADONewConnection('odbtp');
#$ok = $DB->Connect('localhost','root','','northwind');
$ok = $DB->Connect('192.168.0.1','DRIVER={SQL Server};SERVER=(local);UID=sa;PWD=natsoft;DATABASE=OIR;','','');


$DB2 = ADONewConnection('oci8');
$ok2 = $DB2->Connect('192.168.0.2','tnb','natsoft','RAPTOR','');


if (!$ok || !$ok2) die("Failed connection DB=$ok DB2=$ok2<br>");

$tables =
"
JohnTest,id
";

# net* are ERMS, need last updated field from LGBnet
# tblRep* are tables insert or update from Juris, need last updated field also
# The rest are lookup tables, can copy all from LGBnet

$tablesOld = 
"
# Lookup table for Restoration Details screen
sysefi,ID # (not identity)
sysgenkva,ID #(not identity)
sysrestoredby,ID  #(not identity)
# Sel* table added on 24 Oct
SELSGManufacturer,ID 
SelABCCondSizeLV,ID
SelABCCondSizeMV,ID
SelArchingHornSize,ID
SelBallastSize,ID
SelBallastType,ID
SelBatteryType,ID #(not identity)
SelBreakerCapacity,ID
SelBreakerType,ID #(not identity)
SelCBreakerManuf,ID 
SelCTRatio,ID #(not identity)
SelCableBrand,ID
SelCableSize,ID
SelCableSizeLV,ID # (not identity)
SelCapacitorSize,ID
SelCapacitorType,ID
SelColourCode,ID 
SelCombineSealingChamberSize,ID
SelConductorBrand,ID
SelConductorSize4,ID
SelConductorSizeLV,ID
SelConductorSizeMV,ID
SelContactorSize,ID
SelContractor,ID
SelCoverType,ID
SelCraddleSize,ID
SelDeadEndClampBrand,ID
SelDeadEndClampSize,ID
SelDevTermination,ID
SelFPManuf,ID
SelFPillarRating,ID
SelFalseTrue,ID
SelFuseManuf,ID
SelFuseType,ID
SelIPCBrand,ID
SelIPCSize,ID
SelIgnitorSize,ID
SelIgnitorType,ID
SelInsulatorBrand,ID
SelJoint,ID
SelJointBrand,ID
SelJunctionBoxBrand,ID
SelLVBoardBrand,ID
SelLVBoardSize,ID
SelLVOHManuf,ID
SelLVVoltage,ID
SelLightningArresterBrand,ID
SelLightningShieldwireSize,ID
SelLineTapSize,ID
SelLocation,ID
SelMVVoltage,ID
SelMidSpanConnectorsSize,ID
SelMidSpanJointSize,ID
SelNERManuf,ID
SelNERType,ID
SelNLinkSize,ID
SelPVCCondSizeLV,ID
SelPoleBrand,ID
SelPoleConcreteSize,ID
SelPoleSize,ID
SelPoleSpunConcreteSize,ID
SelPoleSteelSize,ID
SelPoleType,ID
SelPoleWoodSize,ID
SelPorcelainFuseSize,ID
SelRatedFaultCurrentBreaker,ID
SelRatedVoltageSG,ID #(not identity)
SelRelayType,ID # (not identity)
SelResistanceValue,ID
SelSGEquipmentType,ID  # (not identity)
SelSGInsulationType,ID # (not identity)
SelSGManufacturer,ID
SelStayInsulatorSize,ID
SelSuspensionClampBrand,ID
SelSuspensionClampSize,ID
SelTSwitchType,ID
SelTowerType,ID
SelTransformerCapacity,ID
SelTransformerManuf,ID
SelTransformerType,ID #(not identity)
SelTypeOfArchingHorn,ID
SelTypeOfCable,ID     #(not identity)
SelTypeOfConductor,ID # (not identity)
SelTypeOfInsulationCB,ID # (not identity)
SelTypeOfMidSpanJoint,ID
SelTypeOfSTJoint,ID
SelTypeSTCable,ID
SelUGVoltage,ID # (not identity)
SelVoltageInOut,ID
SelWireSize,ID
SelWireType,ID
SelWonpieceBrand,ID
#
# Net* tables added on 24 Oct
NetArchingHorn,Idx
NetBatteryBank,Idx # identity, FunctLocation Pri
NetBiMetal,Idx
NetBoxFuse,Idx
NetCable,Idx # identity, FunctLocation Pri
NetCapacitorBank,Idx # identity, FunctLocation Pri
NetCircuitBreaker,Idx # identity, FunctLocation Pri
NetCombineSealingChamber,Idx
NetCommunication,Idx
NetCompInfras,Idx
NetControl,Idx
NetCraddle,Idx
NetDeadEndClamp,Idx
NetEarthing,Idx
NetFaultIndicator,Idx
NetFeederPillar,Idx # identity, FunctLocation Pri
NetGenCable,Idx # identity , FunctLocation Not Null
NetGenerator,Idx
NetGrid,Idx
NetHVOverhead,Idx #identity, FunctLocation Pri
NetHVUnderground,Idx #identity, FunctLocation Pri
NetIPC,Idx
NetInductorBank,Idx
NetInsulator,Idx
NetJoint,Idx
NetJunctionBox,Idx
NetLVDB,Idx #identity, FunctLocation Pri
NetLVOverhead,Idx
NetLVUnderground,Idx # identity, FunctLocation Not Null
NetLightningArrester,Idx
NetLineTap,Idx
NetMidSpanConnectors,Idx
NetMidSpanJoint,Idx
NetNER,Idx # identity , FunctLocation Pri
NetOilPump,Idx
NetOtherComponent,Idx
NetPole,Idx
NetRMU,Idx # identity, FunctLocation Pri
NetStreetLight,Idx
NetStrucSupp,Idx
NetSuspensionClamp,Idx
NetSwitchGear,Idx # identity, FunctLocation Pri
NetTermination,Idx
NetTransition,Idx
NetWonpiece,Idx
#
# comment1
SelMVFuseType,ID
selFuseSize,ID
netRelay,Idx # identity, FunctLocation Pri
SysListVolt,ID
sysVoltLevel,ID_SVL
sysRestoration,ID_SRE
sysRepairMethod,ID_SRM # (not identity)

sysInterruptionType,ID_SIN
netTransformer,Idx # identity, FunctLocation Pri
#
#
sysComponent,ID_SC
sysCodecibs #-- no idea, UpdatedOn(the only column is unique),Ermscode,Cibscode is unique but got null value
sysCodeno,id
sysProtection,ID_SP
sysEquipment,ID_SEQ
sysAddress #-- no idea, ID_SAD(might be auto gen No)
sysWeather,ID_SW
sysEnvironment,ID_SE
sysPhase,ID_SPH
sysFailureCause,ID_SFC
sysFailureMode,ID_SFM
SysSchOutageMode,ID_SSM
SysOutageType,ID_SOT
SysInstallation,ID_SI
SysInstallationCat,ID_SIC
SysInstallationType,ID_SIT
SysFaultCategory,ID_SF #(not identity)
SysResponsible,ID_SR
SysProtectionOperation,ID_SPO #(not identity)
netCodename,CodeNo #(not identity)
netSubstation,Idx #identity, FunctLocation Pri
netLvFeeder,Idx # identity, FunctLocation Pri
#
#
tblReport,ReportNo
tblRepRestoration,ID_RR
tblRepResdetail,ID_RRD
tblRepFailureMode,ID_RFM
tblRepFailureCause,ID_RFC
tblRepRepairMethod,ReportNo # (not identity)
tblInterruptionType,ID_TIN
tblProtType,ID_PT #--capital letter
tblRepProtection,ID_RP
tblRepComponent,ID_RC
tblRepWeather,ID_RW
tblRepEnvironment,ID_RE
tblRepSubstation,ID_RSS
tblInstallationType,ID_TIT
tblInstallationCat,ID_TIC
tblFailureCause,ID_TFC
tblFailureMode,ID_TFM
tblProtection,ID_TP 
tblComponent,ID_TC
tblProtdetail,Id # (Id)--capital letter for I
tblInstallation,ID_TI
#
";


$tables = explode("\n",$tables);

$rep = new ADODB_Replicate($DB,$DB2);
$rep->fieldFilter = 'FieldFilter';
$rep->selFilter = 'SELFILTER';
$rep->indexFilter = 'IndexFilter';

if (1) {
	$rep->debug = 1;
	$DB->debug=1;
	$DB2->debug=1;
}

$cnt = sizeof($tables);
foreach($tables as $k => $table) {
	$pkey = '';
	if (!ParseTable($table, $pkey)) continue;
	
	####################### 
	
	$kcnt = $k+1;
	echo "<h1>($kcnt/$cnt) $table -- $pkey</h1>\n";
	flush();@ob_flush();
	
	CreateTable($rep,$table);
	
	
	# COPY DATA
	if ($pkey) $parr = array($pkey);
	else $parr = array();
	CopyData($rep, $table,$parr);
}

echo "<hr>",date('H:i:s'),": Done</hr>";
?>