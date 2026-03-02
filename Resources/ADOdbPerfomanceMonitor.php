<?php
namespace ADOdb\Resources;

class ADOdbPerformanceMonitor {
// $perf == true means called by NewPerfMonitor(), otherwise for data dictionary
	function _adodb_getdriver($provider,$drivername,$perf=false) {
		switch ($provider) {
			case 'odbtp':
				if (strncmp('odbtp_',$drivername,6)==0) {
					return substr($drivername,6);
				}
			case 'odbc' :
				if (strncmp('odbc_',$drivername,5)==0) {
					return substr($drivername,5);
				}
			case 'ado'  :
				if (strncmp('ado_',$drivername,4)==0) {
					return substr($drivername,4);
				}
			case 'native':
				break;
			default:
				return $provider;
		}

		switch($drivername) {
			case 'mysqlt':
			case 'mysqli':
				$drivername='mysql';
				break;
			case 'postgres7':
			case 'postgres8':
				$drivername = 'postgres';
				break;
			case 'firebird15':
				$drivername = 'firebird';
				break;
			case 'oracle':
				$drivername = 'oci8';
				break;
			case 'access':
				if ($perf) {
					$drivername = '';
				}
				break;
			case 'db2'   :
			case 'sapdb' :
				break;
			default:
				$drivername = 'generic';
				break;
		}
		return $drivername;
	}

	function NewPerfMonitor(&$conn) {
		$drivername = _adodb_getdriver($conn->dataProvider,$conn->databaseType,true);
		if (!$drivername || $drivername == 'generic') {
			return false;
		}
		include_once(ADODB_DIR.'/adodb-perf.inc.php');
		@include_once(ADODB_DIR."/perf/perf-$drivername.inc.php");
		$class = "Perf_$drivername";
		if (!class_exists($class)) {
			return false;
		}

		return new $class($conn);
	}
}