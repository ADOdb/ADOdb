<?php
/*
@version   v5.21.0-dev  ??-???-2016
@copyright (c) 2000-2013 John Lim (jlim#natsoft.com). All rights reserved.
@copyright (c) 2014      Damien Regad, Mark Newnham and the ADOdb community
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence. See License.txt.
  Set tabs to 4 for best viewing.

  Latest version is available at http://adodb.org/

  Microsoft Access data driver. Requires ODBC. Works only on Microsoft Windows.
*/
if (!defined('_ADODB_ODBC_LAYER')) {
	if (!defined('ADODB_DIR')) die();

	include_once(ADODB_DIR."/drivers/adodb-odbc.inc.php");
}

if (!defined('_ADODB_ACCESS')) {
	define('_ADODB_ACCESS',1);

class  ADODB_access extends ADODB_odbc {
	var $databaseType = 'access';
	var $hasTop = 'top';		// support mssql SELECT TOP 10 * FROM TABLE
	var $fmtDate = "#Y-m-d#";
	var $fmtTimeStamp = "#Y-m-d h:i:sA#"; // note not comma
	var $_bindInputArray = false; // strangely enough, setting to true does not work reliably
	var $sysDate = "FORMAT(NOW,'yyyy-mm-dd')";
	var $sysTimeStamp = 'NOW';
	var $hasTransactions = false;
	var $upperCase = 'ucase';

	function Time()
	{
		return time();
	}

	function BeginTrans() { return false;}

	function IfNull( $field, $ifNull )
	{
		return " IIF(IsNull($field), $ifNull, $field) "; // if Access
	}
}


class  ADORecordSet_access extends ADORecordSet_odbc {

	var $databaseType = "access";

} // class

}
