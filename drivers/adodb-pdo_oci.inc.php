<?php
/**
 * PDO Oracle (oci) driver
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @package ADOdb
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v2.1 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-2.1-or-later
 *
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
 */

class ADODB_pdo_oci extends ADODB_pdo {

	var $concat_operator='||';
	var $sysDate = "TRUNC(SYSDATE)";
	var $sysTimeStamp = 'SYSDATE';
	var $NLS_DATE_FORMAT = 'YYYY-MM-DD';  // To include time, use 'RRRR-MM-DD HH24:MI:SS'
	var $random = "abs(mod(DBMS_RANDOM.RANDOM,10000001)/10000000)";
	var $metaTablesSQL = "select table_name,table_type from cat where table_type in ('TABLE','VIEW')";
	var $metaColumnsSQL = "select cname,coltype,width, SCALE, PRECISION, NULLS, DEFAULTVAL from col where tname='%s' order by colno";
	var $metaDatabasesSQL = "SELECT USERNAME FROM ALL_USERS WHERE USERNAME NOT IN ('SYS','SYSTEM','DBSNMP','OUTLN') ORDER BY 1";

	/*
	* Sequence management statements
	*/
	public $_genIDSQL 		 = 'SELECT (%s.nextval) FROM DUAL';
	public $_genSeqSQL 	 	 = 'DECLARE	PRAGMA AUTONOMOUS_TRANSACTION;
BEGIN
	execute immediate \'CREATE SEQUENCE %s START WITH %s\';
END;';
	public $_dropSeqSQL 	 = 'DROP SEQUENCE %s';

 	var $_initdate = true;
	public $_bindInputArray = true;
	public $_nestedSQL = true;

	public function _init($parentDriver)
	{
		
		if ($this->_initdate) {
			$this->Execute("ALTER SESSION SET NLS_DATE_FORMAT='".$this->NLS_DATE_FORMAT."'");
		}
	}

	/**
	 * Return the database server's current date and time.
	 * @return int|false
	 */
	public function time()
	{
		$sql = "select $this->sysTimeStamp from dual";
		
		$rs = $this->_Execute($sql);
		if ($rs && !$rs->EOF) 
		{
			return $this->UnixTimeStamp(reset($rs->fields));
		}

		return false;
	}

	/**
	 * Retrieves a list of tables based on given criteria
	 *
	 * @param string|bool $ttype (Optional) Table type = 'TABLE', 'VIEW' or false=both (default)
	 * @param string|bool $showSchema (Optional) schema name, false = current schema (default)
	 * @param string|bool $mask (Optional) filters the table by name
	 *
	 * @return array list of tables
	 */
	public function metaTables($ttype=false,$showSchema=false,$mask=false)
	{
		if ($mask) {
			$save = $this->metaTablesSQL;
			$mask = $this->qstr(strtoupper($mask));
			$this->metaTablesSQL .= " AND table_name like $mask";
		}
		$ret = ADOConnection::MetaTables($ttype,$showSchema);

		if ($mask) {
			$this->metaTablesSQL = $save;
		}
		return $ret;
	}
	
	/**
	 * Returns a list of Foreign Keys associated with a specific table.
	 *
	 * @param string $table
	 * @param string $owner
	 * @param bool   $upper       discarded
	 * @param bool   $associative discarded
	 *
	 * @return string[]|false An array where keys are tables, and values are foreign keys;
	 *                        false if no foreign keys could be found.
	 */
	public function metaForeignKeys($table, $owner = '', $upper = false, $associative = false)
	{
		global $ADODB_FETCH_MODE;

		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		$table = $this->qstr(strtoupper($table));
		if (!$owner) {
			$owner = $this->user;
			$tabp = 'user_';
		} else
			$tabp = 'all_';

		$owner = ' and owner='.$this->qstr(strtoupper($owner));

		$sql =
"select constraint_name,r_owner,r_constraint_name
	from {$tabp}constraints
	where constraint_type = 'R' and table_name = $table $owner";

		$constraints = $this->GetArray($sql);
		$arr = false;
		foreach($constraints as $constr) {
			$cons = $this->qstr($constr[0]);
			$rowner = $this->qstr($constr[1]);
			$rcons = $this->qstr($constr[2]);
			$cols = $this->GetArray("select column_name from {$tabp}cons_columns where constraint_name=$cons $owner order by position");
			$tabcol = $this->GetArray("select table_name,column_name from {$tabp}cons_columns where owner=$rowner and constraint_name=$rcons order by position");

			if ($cols && $tabcol)
				for ($i=0, $max=sizeof($cols); $i < $max; $i++) {
					$arr[$tabcol[$i][0]] = $cols[$i][0].'='.$tabcol[$i][1];
				}
		}
		$ADODB_FETCH_MODE = $save;

		return $arr;
	}


	/**
	 * List columns in a database as an array of ADOFieldObjects.
	 * See top of file for definition of object.
	 *
	 * @param $table	table name to query
	 * @param $normalize	makes table name case-insensitive (required by some databases)
	 * @schema is optional database schema to use - not supported by all databases.
	 *
	 * @return  array of ADOFieldObjects for current table.
	 */
	public function metaColumns($table,$normalize=true)
	{
	global $ADODB_FETCH_MODE;

		$false = false;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		if ($this->fetchMode !== false) $savem = $this->SetFetchMode(false);

		$rs = $this->Execute(sprintf($this->metaColumnsSQL,strtoupper($table)));

		if (isset($savem)) $this->SetFetchMode($savem);
		$ADODB_FETCH_MODE = $save;
		if (!$rs) {
			return $false;
		}
		$retarr = array();
		while (!$rs->EOF) { //print_r($rs->fields);
			$fld = new ADOFieldObject();
			$fld->name = $rs->fields[0];
			$fld->type = $rs->fields[1];
			$fld->max_length = $rs->fields[2];
			$fld->scale = $rs->fields[3];
			if ($rs->fields[1] == 'NUMBER' && $rs->fields[3] == 0) {
				$fld->type ='INT';
				$fld->max_length = $rs->fields[4];
			}
			$fld->not_null = (strncmp($rs->fields[5], 'NOT',3) === 0);
			$fld->binary = (strpos($fld->type,'BLOB') !== false);
			$fld->default_value = $rs->fields[6];

			if ($ADODB_FETCH_MODE == ADODB_FETCH_NUM) $retarr[] = $fld;
			else $retarr[strtoupper($fld->name)] = $fld;
			$rs->MoveNext();
		}
		$rs->Close();
		if (empty($retarr))
			return  $false;
		else
			return $retarr;
	}

	/**
	 * @param bool $auto_commit
	 * @return void
	 */
	public function setAutoCommit($auto_commit)
	{
		$this->_connectionID->setAttribute(PDO::ATTR_AUTOCOMMIT, $auto_commit);
	}

	/**
	 * Returns a driver-specific format for a bind parameter
	 *
	 * @param string $name
	 * @param string $type (ignored in driver)
	 *
	 * @return string
	 */
	public function param($name,$type='C')
	{
		return sprintf(':%s', $name);
	}

	/**
	 * Returns the server information
	 * 
	 * @return array()
	 */
	public function serverInfo() 
	{

		global $ADODB_FETCH_MODE;
		static $arr = false;
		if (is_array($arr))
			return $arr;
		if ($this->fetchMode === false) {
			$savem = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		} elseif ($this->fetchMode >=0 && $this->fetchMode <=2) {
			$savem = $this->fetchMode;
		} else
			$savem = $this->SetFetchMode(ADODB_FETCH_NUM);

		$arr = array();
		$arr['version'] 	=  $this->_connectionID->getAttribute(constant("PDO::ATTR_SERVER_VERSION"));
		$arr['description'] = $this->_connectionID->getAttribute(constant("PDO::ATTR_SERVER_INFO"));

		$ADODB_FETCH_MODE = $savem;
		return $arr;
	}
}
