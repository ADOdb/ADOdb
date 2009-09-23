<?php
/* 
V5.09 25 June 2009   (c) 2000-2009 John Lim (jlim#natsoft.com). All rights reserved.
  Released under both BSD license and Lesser GPL library license. 
  Whenever there is any discrepancy between the two licenses, 
  the BSD license will take precedence. 
Set tabs to 4 for best viewing.
  
  Latest version is available at http://adodb.sourceforge.net
  
  Microsoft Visual FoxPro data driver. Requires ODBC. Works only on MS Windows.
*/

// security - hide paths
if (!defined('ADODB_DIR')) die();
include(ADODB_DIR."/drivers/adodb-db2.inc.php");


if (!defined('ADODB_DB2OCI')){
define('ADODB_DB2OCI',1);


function _colontrack($p)
{
global $_COLONARR,$_COLONSZ;
	$v = (integer) substr($p,1);
	if ($v > $_COLONSZ) return $p;
	$_COLONARR[] = $v;
	return '?';
}

function _colonscope($sql,$arr)
{
global $_COLONARR,$_COLONSZ;

	$_COLONARR = array();
	$_COLONSZ = sizeof($arr);
	
	$sql2 = preg_replace("/(:[0-9]+)/e","_colontrack('\\1')",$sql);
	
	if (empty($_COLONARR)) return array($sql,$arr);
	
	foreach($_COLONARR as $k => $v) {
		$arr2[] = $arr[$v]; 
	}
	
	return array($sql2,$arr2);
}

class ADODB_db2oci extends ADODB_db2 {
	var $databaseType = "db2oci";	
	var $sysTimeStamp = 'sysdate';
	var $sysDate = 'trunc(sysdate)';
	var $_bindInputArray = true;
	
	function ADODB_db2oci()
	{
		parent::ADODB_db2();
	}
	
	
		function MetaTables($ttype=false,$schema=false)
	{
	global $ADODB_FETCH_MODE;
	
		$savem = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		$qid = db2_tables($this->_connectionID);
		
		$rs = new ADORecordSet_db2($qid);
		
		$ADODB_FETCH_MODE = $savem;
		if (!$rs) {
			$false = false;
			return $false;
		}
		
		$arr = $rs->GetArray();
		$rs->Close();
		$arr2 = array();
	//	adodb_pr($arr);
		if ($ttype) {
			$isview = strncmp($ttype,'V',1) === 0;
		}
		for ($i=0; $i < sizeof($arr); $i++) {
			if (!$arr[$i][2]) continue;
			$type = $arr[$i][3];
			$schemaval = ($schema) ? $arr[$i][1].'.' : '';
			$name = $schemaval.$arr[$i][2];
			$owner = $arr[$i][1];
			if (substr($name,0,8) == 'EXPLAIN_') continue;
			if ($ttype) { 
				if ($isview) {
					if (strncmp($type,'V',1) === 0) $arr2[] = $name;
				} else if (strncmp($type,'T',1) === 0 && strncmp($owner,'SYS',3) !== 0) $arr2[] = $name;
			} else if (strncmp($type,'T',1) === 0 && strncmp($owner,'SYS',3) !== 0) $arr2[] = $name;
		}
		return $arr2;
	}
	
	function _Execute($sql, $inputarr=false	)
	{
		if ($inputarr) list($sql,$inputarr) = _colonscope($sql, $inputarr);
		return parent::_Execute($sql, $inputarr);
	}
};
 

class  ADORecordSet_db2oci extends ADORecordSet_db2 {	
	
	var $databaseType = "db2oci";		
	
	function ADORecordSet_db2oci($id,$mode=false)
	{
		return $this->ADORecordSet_db2($id,$mode);
	}
}

} //define
?>