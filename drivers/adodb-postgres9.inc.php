<?php
/*
 V5.19dev  ??-???-2014  (c) 2000-2014 John Lim (jlim#natsoft.com). All rights reserved.
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.
  Set tabs to 4.

  Postgres9 support.
  01 Dec 2011: gherteg added support for retrieving insert IDs from tables without OIDs
*/

// security - hide paths
if (!defined('ADODB_DIR')) die();

include_once(ADODB_DIR."/drivers/adodb-postgres7.inc.php");

class ADODB_postgres9 extends ADODB_postgres7
{
	var $databaseType = 'postgres9';

	// Don't use OIDs, as they typically won't be there, and
	// they're not what the application wants back, anyway.
	function _insertid($table,$column)
	{
		if ( empty($table) || empty($column) ) {
			if (!is_resource($this->_resultid) || get_resource_type($this->_resultid) !== 'pgsql result') return false;
			if (function_exists('pg_getlastoid')) $oid = pg_getlastoid($this->_resultid);
			else $oid = false;
			return $oid;
		}

		return	$this->GetOne("SELECT currval(pg_get_serial_sequence('$table','$column'))");
	}
}

class ADORecordSet_postgres9 extends ADORecordSet_postgres7
{
	var $databaseType = "postgres9";
}

class ADORecordSet_assoc_postgres9 extends ADORecordSet_assoc_postgres7
{
	var $databaseType = "postgres9";
}
