<?php


/*
V5.20dev  ??-???-2014  (c) 2000-2014 John Lim (jlim#natsoft.com). All rights reserved.
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.
  Set tabs to 8.

*/

class ADODB_pdo_oci extends ADODB_pdo_base {

	var $concat_operator='||';
	var $sysDate = "TRUNC(SYSDATE)";
	var $sysTimeStamp = 'SYSDATE';
	var $NLS_DATE_FORMAT = 'YYYY-MM-DD';  // To include time, use 'RRRR-MM-DD HH24:MI:SS'
	var $random = "abs(mod(DBMS_RANDOM.RANDOM,10000001)/10000000)";
	var $metaTablesSQL = "select table_name,table_type from cat where table_type in ('TABLE','VIEW')";
	var $metaColumnsSQL = "select cname,coltype,width, SCALE, PRECISION, NULLS, DEFAULTVAL from col where tname='%s' order by colno";

 	var $_initdate = true;
	var $_hasdual = true;

	function _init($parentDriver)
	{
		$parentDriver->_bindInputArray = true;
		$parentDriver->_nestedSQL = true;
		if ($this->_initdate) {
			$parentDriver->Execute("ALTER SESSION SET NLS_DATE_FORMAT='".$this->NLS_DATE_FORMAT."'");
		}
	}

	function MetaTables($ttype=false,$showSchema=false,$mask=false)
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

	function MetaColumns($table,$normalize=true)
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

	//VERBATIM COPY FROM "adodb-oci8.inc.php"
	function MetaIndexes ($table, $primary = FALSE, $owner=false)
	{
		// save old fetch mode
		global $ADODB_FETCH_MODE;

		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

		if ($this->fetchMode !== FALSE) {
			$savem = $this->SetFetchMode(FALSE);
		}

		// get index details
		$table = strtoupper($table);

		// get Primary index
		$primary_key = '';

		$rs = $this->Execute(sprintf("SELECT * FROM ALL_CONSTRAINTS WHERE UPPER(TABLE_NAME)='%s' AND CONSTRAINT_TYPE='P'",$table));
		if (!is_object($rs)) {
			if (isset($savem)) {
				$this->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			return false;
		}

		if ($row = $rs->FetchRow()) {
			$primary_key = $row[1]; //constraint_name
		}

		if ($primary==TRUE && $primary_key=='') {
			if (isset($savem)) {
				$this->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			return false; //There is no primary key
		}

		$rs = $this->Execute(sprintf("SELECT ALL_INDEXES.INDEX_NAME, ALL_INDEXES.UNIQUENESS, ALL_IND_COLUMNS.COLUMN_POSITION, ALL_IND_COLUMNS.COLUMN_NAME FROM ALL_INDEXES,ALL_IND_COLUMNS WHERE UPPER(ALL_INDEXES.TABLE_NAME)='%s' AND ALL_IND_COLUMNS.INDEX_NAME=ALL_INDEXES.INDEX_NAME",$table));


		if (!is_object($rs)) {
			if (isset($savem)) {
				$this->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			return false;
		}

		$indexes = array ();
		// parse index data into array

		while ($row = $rs->FetchRow()) {
			if ($primary && $row[0] != $primary_key) {
				continue;
			}
			if (!isset($indexes[$row[0]])) {
				$indexes[$row[0]] = array(
					'unique' => ($row[1] == 'UNIQUE'),
					'columns' => array()
				);
			}
			$indexes[$row[0]]['columns'][$row[2] - 1] = $row[3];
		}

		// sort columns by order in the index
		foreach ( array_keys ($indexes) as $index ) {
			ksort ($indexes[$index]['columns']);
		}

		if (isset($savem)) {
			$this->SetFetchMode($savem);
			$ADODB_FETCH_MODE = $save;
		}
		return $indexes;
	}
}
