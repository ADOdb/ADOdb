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

	/**
	 * Retrieve last inserted ID
	 * Don't use OIDs, since as per {@link http://php.net/function.pg-last-oid php manual }
	 * they won't be there in Postgres 8.1
	 * (and they're not what the application wants back, anyway).
	 * @param string $table
	 * @param string $column
	 * @return int last inserted ID for given table/column, or the most recently
	 *             returned one if $table or $column are empty
	 */
	function _insertid($table,$column)
	{
		return empty($table) || empty($column)
			? $this->GetOne("SELECT lastval()")
			: $this->GetOne("SELECT currval(pg_get_serial_sequence('$table', '$column'))");
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
