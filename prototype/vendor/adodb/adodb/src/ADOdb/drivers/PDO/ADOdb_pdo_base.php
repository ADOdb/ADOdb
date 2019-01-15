<?php
namespace ADOdb\drivers\PDO;

use ADOdb;

class ADOdb_pdo_base extends ADOdb_pdo {

	var $sysDate = "'?'";
	var $sysTimeStamp = "'?'";


	function _init($parentDriver)
	{
		$parentDriver->_bindInputArray = true;
		#$parentDriver->_connectionID->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
	}

	function ServerInfo()
	{
		return ADOConnection::ServerInfo();
	}

	function SelectLimit($sql,$nrows=-1,$offset=-1,$inputarr=false,$secs2cache=0)
	{
		$ret = ADOConnection::SelectLimit($sql,$nrows,$offset,$inputarr,$secs2cache);
		return $ret;
	}

	//function MetaTables($ttype=false,$showSchema=false,$mask=false)
	//{
	//	return false;
	//}

	function MetaColumns($table,$normalize=true)
	{
		return false;
	}
}
