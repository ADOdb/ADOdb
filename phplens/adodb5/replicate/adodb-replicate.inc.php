<?php

define('ADODB_REPLICATE',1);

include_once(ADODB_DIR.'/adodb-datadict.inc.php');

/*
	Note: this code assumes that comments such as  / *    * / are allowed which works with:
		 mssql, postgresql, oracle, mssql
		 
	Replication engine to 
	 	- copy table structures and data from different databases (e.g. mysql to oracle) 
		  for replication purposes
		- generate CREATE TABLE, CREATE INDEX, INSERT ... for installation scripts
		
	Table Structure copying includes
		- fields and limited subset of types
		- optional default values
		- indexes
		- but not constraints
		
		
	Two modes of data copy:
	
	ReplicateData
		- Copy from src to dest, with update of status of copy back to src, 
		  with configurable src SELECT where clause
	
	MergeData
		- Copy from src to dest based on last mod date field and/or copied flag field
		
	Default settings are
		- do not execute, generate sql ($rep->execute = false)
		- do not delete records in dest table first ($rep->deleteFirst = false).
			if $rep->deleteFirst is true and primary keys are defined, 
			then no deletion will occur unless *INSERTONLY* is defined in pkey array
		- only commit once at the end of every ReplicateData ($rep->commitReplicate = true)
		- do not autocommit every x records processed ($rep->commitRecs = -1)
		- even if error occurs on one record, continue copying remaining records ($rep->neverAbort = true)
		- debugging turned off ($rep->debug = false)
*/

class ADODB_Replicate {
	var $connSrc;
	var $connDest;
	var $ddSrc;
	var $ddDest;
	
	var $execute = false;
	var $debug = false;
	var $deleteFirst = false;
	var $commitReplicate = true; // commit at end of replicatedata
	var $commitRecs = -1; // only commit at end of ReplicateData()
	
	var $selFilter = false;
	var $fieldFilter = false;
	var $indexFilter = false;
	var $updateFilter = false;
	var $updateSrcFn = false;
	
	var $neverAbort = true;
	var $copyTableDefaults = false; // turn off because functions defined as defaults will not work when copied
	var $errHandler = false; // name of error handler function, if used.
	var $htmlSpecialChars = true; 	// if execute false, then output with htmlspecialchars enabled. 
									// Will autoconfigure itself. No need to modify
	var $updateFirst = true;
		// if true then code will try update before insert (better when refreshing old records), 
		// if false then insert tried first (better when most data copied is new records).
		
	var $oracleSequence = false;
	
	function ADODB_Replicate($connSrc, $connDest)
	{
		$this->connSrc = $connSrc;
		$this->connDest = $connDest;
		
		$this->ddSrc = NewDataDictionary($connSrc);
		$this->ddDest = NewDataDictionary($connDest);
		$this->htmlSpecialChars = isset($_SERVER['HTTP_HOST']);
	}
	
	function ExecSQL($sql)
	{
		if (!is_array($sql)) $sql[] = $sql;
		
		$ret = true;
		foreach($sql as $s) 
			if (!$this->execute) echo "<pre>",$s.";\n</pre>";
			else {
				$ok = $this->connDest->Execute($s);
				if (!$ok)
					if ($this->neverAbort) $ret = false;
					else return false;
			}
			
		return $ret;
	}
	
	/*
		We assume replication between $table and $desttable only works if the field names and types match for both tables.
		
		Also $table and desttable can have different names.
	*/
	
	function CopyTableStruct($table,$desttable='')
	{
		$sql = $this->CopyTableStructSQL($table,$desttable);
		if (empty($sql)) return false;
		return $this->ExecSQL($sql);
	}
	
	function RunFieldFilter(&$fld, $mode = '')
	{
		if ($this->fieldFilter) {
			$fn = $this->fieldFilter;
			return $fn($fld, $mode);
		} else
			return $fld;
	}
	
	function RunUpdateFilter($table, $fld, $val)
	{
		if ($this->updateFilter) {
			$fn = $this->updateFilter;
			return $fn($table, $fld, $val);
		} else
			return $val;
	}
	
	/*
		$mode = INS or UPD
	*/
	function RunUpdateSrcFn($srcdb, $table, $fldoffsets, $row, $where, $mode, $dest_insertid=null)
	{
		if (!$this->updateSrcFn) return;
		
		$bindarr = array();
		foreach($fldoffsets as $k) {
			$bindarr[$k] = $row[$k];
		}
		$where = "WHERE $where";
		$fn = $this->updateSrcFn;
		
		if (is_array($fn) !== false) {
			if (sizeof($fn) == 1) $set = reset($fn);
			else $set = @$fn[$mode];
			if ($set) {
				
				if (strlen($dest_insertid) == 0) $dest_insertid = 'null';
				$set = str_replace('$INSERT_ID',$dest_insertid,$set);
					
				if (strpos($srcdb->databaseType,'odbtp') !== false) {
					$srcdb->_bindInputArray = false;  # bug in odbtp, binding fails
				}
				
				$sql = "UPDATE $table SET $set $where";
				$ok = $srcdb->Execute($sql,$bindarr);
				if (!$ok) adodb_backtrace();
			}
		} else $fn($srcdb, $table, $row, $where, $bindarr, $mode, $dest_insertid);
		
	}
	
	function CopyTableStructSQL($table, $desttable='',$dropdest =false)
	{
		if (!$desttable) {
			$desttable = $table;
			$prefixidx = '';
		} else
			$prefixidx = $desttable;
			
		$conn = $this->connSrc;
		$types = $conn->MetaColumns($table);
		if (!$types) {
			echo "$table does not exist in source db<br>\n";
			return array();
		}
		if (!$dropdest && $this->connDest->MetaColumns($desttable)) {
			echo "$desttable already exists in dest db<br>\n";
			return array();
		}
		if ($this->debug) var_dump($types);
		$sa = array();
		$idxcols = array();
		
		foreach($types as $name => $t) {
			$s = '';
			$mt = $this->ddSrc->MetaType($t->type);
			$len = $t->max_length;
			$fldname = $this->RunFieldFilter($t->name,'TABLE');
			if (!$fldname) continue;
			
			$s .= $fldname . ' '.$mt;
			if (isset($t->scale)) $precision = '.'.$t->scale;
			else $precision = '';
			if ($mt == 'C' or $mt == 'X') $s .= "($len)";
			else if ($mt == 'N' && $precision) $s .= "($len$precision)";
			
			if ($mt == 'R') $idxcols[] = $fldname;
			
			if ($this->copyTableDefaults) {
				if (isset($t->default_value)) {
					$v = $t->default_value;
					if ($mt == 'C' or $mt == 'X') $v = $this->connDest->qstr($v); // might not work as this could be function
					$s .= ' DEFAULT '.$v;
				}
			}
			
			$sa[] = $s;
		}
		
		$s = implode(",\n",$sa);

		// dump adodb intermediate data dictionary format
		if ($this->debug) echo '<pre>'.$s.'</pre>';
		
		$sqla =  $this->ddDest->CreateTableSQL($desttable,$s);
		
		/*
		if ($idxcols) {
			$idxoptions = array('UNIQUE'=>1);
			$sqla2 = $this->ddDest->_IndexSQL($table.'_'.$fldname.'_SERIAL', $desttable, $idxcols,$idxoptions); 
			$sqla = array_merge($sqla,$sqla2);
		}*/
		
		$idxs = $conn->MetaIndexes($table);
		if ($idxs)
		foreach($idxs as $name => $iarr) {
			$idxoptions = array();
			$fldnames = array();
			
			if(!empty($iarr['unique'])) {
				$idxoptions['UNIQUE'] = 1;
			}
			
			foreach($iarr['columns'] as $fld) {
				$fldnames[] = $this->RunFieldFilter($fld,'TABLE');
			}
			
			$idxname = $prefixidx.str_replace($table,$desttable,$name);
			
			if (!empty($this->indexFilter)) {
				$fn = $this->indexFilter;
				$idxname = $fn($desttable,$idxname,$fldnames,$idxoptions);
			}
			$sqla2 = $this->ddDest->_IndexSQL($idxname, $desttable, $fldnames,$idxoptions); 
			$sqla = array_merge($sqla,$sqla2);
		}
		
		return $sqla;
	}
	
	function _clearcache()
	{
	
	}
	
	function _concat($v)
	{ 
		return $this->connDest->concat("' ","chr(".ord($v).")","'");
	}
	
	function fixupbinary($v) 
	{
		return str_replace(
			array("\r","\n"), 
			array($this->_concat("\r"),$this->_concat("\n")),
			$v );
	}
	
	function SwapDBs()
	{
		$o = $this->connSrc;
		$this->connSrc = $this->connDest;
		$this->connDest = $o;
		
		$o = $this->ddSrc;
		$this->ddSrc = $this->ddDest;
		$this->ddDest = $o;
	}
	
	/*
	// if no uniqflds defined, then all desttable recs will be deleted before insert
	// $where clause must include the WHERE word if used
	// if $this->commitRecs is set to a +ve value, then it will autocommit every $this->commitRecs records 
	//		-- this should never be done with 7x24 db's
	
	
	ReplicateData() params:
	
	$table = src table name
	$desttable = dest table name, leave blank to use src table name
	$uniqflds = array() = an array. If set, then inserts and updates will occur. eg. array('PK1', 'PK2');
		To prevent updates to desttable (allow only to src table), add '*INSERTONLY*' or '*ONLYINSERT*' to array. 
	$where = where clause for SELECT from $table $where. Include the WHERE reserved word in beginning. 
		You can put ORDER BY at the end also
	$ignoreflds = array(), list of fields to ignore. e.g. array('FLD1',FLD2');
	$dstCopyDateFld = date field on $desttable to update with current date
	$extraflds allows you to add additional flds to insert/update. Format
		array(fldname => $fldval)
		$fldval itself can be an array or a string. If an array, then
		$extraflds = array($fldname => array($insertval, $updateval))
	
	Thus we have the following behaviours:
	
	a. Delete all data in $desttable then insert from src $table
		
		$rep->execute = true;
		$rep->ReplicateData($table, $desttable)
	
	b. Update $desttable if record exists (based on $uniqflds), otherwise insert.
	
		$rep->execute = true;
		$rep->ReplicateData($table, $desttable, $array($pkey1, $pkey2))
		
	c. Select from src $table all data modified since a date. Then update $desttable 
		if record exists (based on $uniqflds), otherwise insert
	
		$rep->execute = true;
		$rep->ReplicateData($table, $desttable, array($pkey1, $pkey2), "WHERE update_datetime_fld > $LAST_REFRESH")
		
	d. Insert all records into $desttable modified after a certain id (or time) in src $table:
	
		$rep->execute = true;
		$rep->ReplicateData($table, $desttable, false, "WHERE id_fld > $LAST_ID_SAVED", true);
		
	
	For (a) to (d), returns array: array($boolean_ok_fail, $no_recs_selected_from_src_db, $no_recs_inserted, $no_recs_updated);
	
	e. Generate sample SQL:
	
		$rep->execute = false;
		$rep->ReplicateData(....);
		
		This returns $array, which contains:
		 
			$array['SEL'] = select stmt from src db
			$array['UPD'] = update stmt to dest db
			$array['INS'] = insert stmt to dest db
			
			
	Error-handling 
	==============
	Default is never abort if error occurs. You can set $rep->neverAbort = false; to force replication to abort if an error occurs.
		
		
	Value Filtering
	========
	Sometimes you might need to modify/massage the data before the code works. Assume that the value used for True and False is 
	'T' and 'F' in src DB, but is 'Y' and 'N' in dest DB for field[2] in select stmt. You can do this by
	
		$rep->filterSelect = 'filter';
		$rep->ReplicateData(...);
		
		function filter($table,& $fields, $deleteFirst)
		{
			if ($table == 'SOMETABLE') {
				if ($fields[2] == 'T') $fields[2] = 'Y';
				else if ($fields[2] == 'F') $fields[2] = 'N';
			}
		}
		
	We pass in $deleteFirst as that determines the order of the fields (which are numeric-based):
		TRUE: the order of fields matches the src table order
		FALSE: the order of fields is all non-primary key fields first, followed by primary key fields. This is because it needs
				to match the UPDATE statement, which is UPDATE $table SET f2 = ?, f3 = ? ... WHERE f1 = ?
				
	Name Filtering
	=========	
	Sometimes field names that are legal in one RDBMS can be illegal in another. 
	We allow you to handle this using a field filter. 
	Also if you don't want to replicate certain fields, just return false.
	
		$rep->fieldFilter = 'ffilter';	

			function ffilter(&$fld,$mode)
			{
				$uf = strtoupper($fld);
				switch($uf) {
					case 'GROUP': 
						if ($mode == 'SELECT') $fld = '"Group"';
						return 'GroupFld';
						
					case 'PRIVATEFLD':  # do not replicate
						return false;
				}
				return $fld;
			}
			
			
	UPDATE FILTERING
	================
	Sometimes, when we want to update
		UPDATE table SET fld = val WHERE ....
		
	we want to modify val. To do so, define
	
		$rep->updateFilter = 'ufilter';
		
		function ufilter($table, $fld, $val)
		{
			return "nvl($fld, $val)";
		}
		
		
	Sending back audit info back to src Table
	=========================================
	
	Use $rep->updateSrcFn. This can be an array of strings, or the name of a php function to call.
	
	If an array of strings is defined, then it will perform an update statement...
	
		UPDATE srctable SET $string WHERE ....
	
	With $string set to the array you define. If a new record was inserted into desttable, then the
	'INS' string is used ($INSERT_ID will be replaced with the real INSERT_ID, if any), 
	and if an update then use the 'UPD' string.
	
		array(
			'INS' => 'insertid = $INSERT_ID, copieddate=getdate(), copied = 1',
			'UPD' => 'copieddate=getdate(), copied = 1'
		)
	
	If a single string array is defined, then it will be used for both insert and update.
		array('copieddate=getdate(), copied = 1')
		
	Note that the where clause is automatically defined by the system.
	
	If $rep->updateSrcFn is a PHP function name, then it will be called with the following params:
	
		$fn($srcConnection, $tableName, $row, $where, $bindarr, $mode, $dest_insertid)
	
	$srcConnection - source db connection
	$tableName	- source tablename
	$row - array holding records updated into dest
	$where - where clause to be used (uses bind vars)
	$bindarr - array holding bind variables for where clause
	$mode - INS or UPD
	$dest_insertid - when mode=INS, then the insert_id is stored here.
	
		
		oracle  mssql
		        ---> insert
		mssqlid	<--- insert_id
		       ----> update with mssqlid
			   <---- update with mssqlid
		  
	
	TODO: add src pkey and dest pkey for updates. Also sql stmt needs to be tuned, so dest pkey, src pkey
	*/
	
	
	function ReplicateData($table, $desttable = '',  $uniqflds = array(), $where = '',$ignore_flds = array(), 
		$dstCopyDateFld='', $extraflds = array())
	{
		$updateFirst = $this->updateFirst;
		$dstCopyDateName = $dstCopyDateFld;
		$dstCopyDateFld = strtoupper($dstCopyDateFld);
		
		$this->_clearcache();
		if (is_string($uniqflds) && strlen($uniqflds)) $uniqflds = array($uniqflds);
		if (!$desttable) $desttable = $table;
		
		$uniq = array();
		if ($uniqflds) {
			if (is_array(reset($uniqflds))) {
				/*
					 primary key of src and dest tables differ. This means when we perform the select stmts
					 we retrieve both keys. Then any insert statement will have to ignore one array element.
					 Any update statement will need to use a different where clause
				*/
				$destuniqflds = $uniqflds[0];
				if (sizeof($uniqflds)>1)
					$srcuniqflds = $uniqflds[1];
				else
					$srcuniqflds = array();
			} else {
				$destuniqflds = $uniqflds;
				$srcuniqflds = array();
			}
			$onlyInsert = false;
			foreach($destuniqflds as $k => $u) {
				if ($u == '*INSERTONLY*' || $u == '*ONLYINSERT*') {
					$onlyInsert = true;
					continue;
				}
				$uniq[strtoupper($u)] = $k;
			}
			$deleteFirst = ($this->deleteFirst && $onlyInsert);
		} else {
			$deleteFirst = true;
			$onlyInsert = true;
		}
		
		if ($ignore_flds) {
			foreach($ignore_flds as $u) {
				$ignoreflds[strtoupper($u)] = 1;
			}
		} else
			$ignoreflds = array();
		
		$src = $this->connSrc;
		$dest = $this->connDest;
		$dest->noNullStrings = false;
		$src->noNullStrings = false;
		
		if ($src === $dest) $this->execute = false;
		
		$types = $src->MetaColumns($table);
		if (!$types) {
			echo "Source $table does not exist<br>\n";
			return array();
		}
		$dtypes = $this->connDest->MetaColumns($desttable);
		if (!$dtypes) {
			echo "Destination $desttable does not exist<br>\n";
			return array();
		}
		$sa = array();
		$selflds = array();
		$wheref = array();
		$wheres = array();
		$srcwheref = array();
		
		$k = 0;
		foreach($types as $name => $t) {
			$name2 = strtoupper($this->RunFieldFilter($name,'SELECT'));
			if (!isset($dtypes[($name2)]) || !$name2) {
				if ($this->debug) echo " Skipping $name as not in destination $desttable<br>";
				continue;
			}
			
			if ($name2 == $dstCopyDateFld) {
				$dstCopyDateName = $t->name;
				continue;
			}
			
			$fld = $t->name;
			$fldval = $t->name;
			$mt = $src->MetaType($t->type);
			if ($mt == 'D') $fldval = $dest->DBDate($fldval);
			elseif ($mt == 'T') $fldval = $dest->DBTimeStamp($fldval);
			$ufld = strtoupper($fld);
			
			if (isset($ignoreflds[($name2)]) && !isset($uniq[$ufld])) {
				continue;
			}
			
			if ($this->debug) echo " field=$fld type=$mt fldval=$fldval<br>";
			
			if (!isset($uniq[$ufld])) {
				
				$selfld = $fld;
				$fld = $this->RunFieldFilter($selfld,'SELECT');
				$selflds[] = $selfld;
				
				$p = $dest->Param($k);
				
				if ($mt == 'D') $p = $dest->DBDate($p, true);
				else if ($mt == 'T') $p = $dest->DBTimeStamp($p, true);
				
				# UPDATES
				$sets[] = "$fld = ".$this->RunUpdateFilter($desttable, $fld, $p);
				
				# INSERTS
				$insflds[] = $fld; $params[] = $p;
				$k++;
			} else {
				$fld = $this->RunFieldFilter($fld);
				$wheref[] = $fld;
				if (!empty($srcuniqflds)) $srcwheref[] = $srcuniqflds[$uniq[$ufld]];
			}
		}
		
		if (!empty($srcuniqflds) && sizeof($srcuniqflds) > sizeof($srcwheref))
			$srcPKDest = $srcuniqflds[sizeof($srcuniqflds)-1];
		
		foreach($extraflds as $fld => $evals) {
			if (!is_array($evals)) $evals = array($evals, $evals);
			$insflds[] = $fld; $params[] = $evals[0];
			$sets[] = "$fld = ".$evals[1];
		}
		
		if ($dstCopyDateFld) { 
			$sets[] = "$dstCopyDateName = ".$dest->sysTimeStamp;

			$insflds[] = $dstCopyDateName; $params[] = $dest->sysTimeStamp;
		}

		// mssql ==> oracle
		// $pkeyarr = array(array('ID'),array('ORA_ID', 'MSSQL_ID'));
		foreach($wheref as $uu => $fld) {

			if(!empty($srcPKDest)) {
				if ($uu > 1) die("Only one primary key for srcwheref allowed currently");
				$srcff = $srcwheref[$uu];  # ORA_ID
				$selflds[] = $fld;   # ID
				$params[] = $dest->Param($k);
				$srcPKDestIdx = $k;
				$srcwheres[] = $fld.' = '.$src->Param($k);
				
			} else {
				$selflds[] = $fld;
				$wheres[] = $fld.' = '.$dest->Param($k);
			}
			
			if (!isset($ignoreflds[strtoupper($fld)])) {
				$insflds[] = $fld;
				$params[] = $dest->Param($k);
			}
			$fldoffsets[] = $k;
			$k++;
		}
		
		if (!empty($srcPKDest)) {
		
			$selflds[] = $srcff;
			
			$wheres[] = $srcPKDest.' = '.$dest->Param($k-1);
			$insflds[] = $srcPKDest;
			
			$fldoffsets = array($srcPKDestIdx);
			
			$srcPKDestIdx = $k;
			$k++;
		} else	
			$fldoffsets = array();	
					
		$insfldss = implode(', ', $insflds);
		$fldss = implode(', ', $selflds);
		$setss = implode(', ', $sets);
		$paramss = implode(', ', $params);
		$wheress = implode(' AND ', $wheres);
		if (isset($srcwheres))
			$srcwheress = implode(' AND ',$srcwheres);
		
		$sa['SEL'] = "SELECT $fldss FROM $table $where";
		$sa['INS'] = "INSERT INTO $desttable ($insfldss) VALUES ($paramss)";
		$sa['UPD'] = "UPDATE $desttable SET $setss WHERE $wheress";
		
		$DB1 = "/* <font color=green> Source DB - sample sql in case you need to adapt code\n\n";
		$DB2 = "/* <font color=green> Dest DB - sample sql in case you need to adapt code\n\n";
		
		if (!$this->execute) echo '/*<style>
pre {
white-space: pre-wrap; /* css-3 */
white-space: -moz-pre-wrap !important; /* Mozilla, since 1999 */
white-space: -pre-wrap; /* Opera 4-6 */
white-space: -o-pre-wrap; /* Opera 7 */
word-wrap: break-word; /* Internet Explorer 5.5+ */
}
</style><pre>*/
';
		if ($deleteFirst && $this->deleteFirst) {
			$sql = "DELETE FROM $desttable\n";
			if (!$this->execute) echo $DB2,'</font>*/',$sql,"\n";
			else $dest->Execute($sql);
		}
		
		global $ADODB_COUNTRECS;
		$err = false;
		$src->setFetchMode(ADODB_FETCH_NUM);
		$ADODB_COUNTRECS = false;		
		
		if (!$this->execute) {
			echo $DB1,$sa['SEL'],"</font>\n*/\n\n";
			echo $DB2,$sa['INS'],"</font>\n*/\n\n";
			$suffix = ($onlyInsert) ? ' PRIMKEY=?' : '';
			echo $DB2,$sa['UPD'],"$suffix</font>\n*/\n\n";
			
			$src->setFetchMode(ADODB_FETCH_NUM);
			$rs = $src->Execute($sa['SEL']);
			$cnt = 1;
			$upd = 0;
			$ins = 0;
			while ($rs && !$rs->EOF) {
				$INS = $sa['INS'];
				$arr = array_reverse($rs->fields);
				foreach($arr as $k => $v) {
					$k = sizeof($arr)-$k-1;
					$INS = str_replace(':'.$k,$this->fixupbinary($dest->qstr($v)),$INS);
				}
				if ($this->htmlSpecialChars) $INS = htmlspecialchars($INS);
				echo "-- $cnt\n",$INS,";\n\n";
				$cnt += 1;
				$ins += 1;
				$rs->MoveNext();
			}
			
			return $sa;
		} else {
			
			$rs = $src->Execute($sa['SEL']);
			if (!$rs) {
				if ($this->errHandler) $this->_doerr('SEL',array());
				return array(0,0,0,0);
			}
			
			if ($this->commitReplicate || $commitRecs > 0) {
				$dest->BeginTrans();
				if ($this->updateSrcFn) $src->BeginTrans();
			}
			
			if ($this->updateSrcFn && strpos($src->databaseType,'mssql') !== false) {
				# problem is writers interfere with readers in mssql
				$rs = $src->_rs2rs($rs);
			}
			$cnt = 0;
			$upd = 0;
			$ins = 0;
			
			$sizeofrow = sizeof($selflds);
			
			$fn = $this->selFilter;
			$commitRecs = $this->commitRecs;
			
			$saved = $dest->debug;
			
			while ($origrow = $rs->FetchRow()) {
				#var_dump($row);
				if ($dest->debug) {flush(); @ob_flush();}
				
				if ($fn) {
					if (!$fn($desttable, $row,$deleteFirst,$this)) continue;
				}
				$doinsert = true;
				$row = $origrow;
				
				if (!$onlyInsert) {
					$doinsert = false;
					$upderr = false;
					if (isset($srcPKDestIdx)) {var_dump($origrow);
						if(is_null($origrow[$srcPKDestIdx])) {
							$upderr = true;
						} else {
							$row = array_slice($origrow,0,$sizeofrow-1);
						}
					} 
					
					if (!$upderr && !$dest->Execute($sa['UPD'],$row)) {
						$err = true;
						$upderr = true;
						if ($this->errHandler) $this->_doerr('UPD',$row);
						if (!$this->neverAbort) break;
					}
					
				 	if ($upderr || $dest->Affected_Rows() == 0) {
						$doinsert = true;
					} else {
						if (!empty($uniqflds)) $this->RunUpdateSrcFn($src, $table, $fldoffsets, $origrow, $srcwheress, 'UPD');
						$upd += 1;
					}
				} 
				
				if ($doinsert) {
					$inserr = false;
					if (isset($srcPKDestIdx)) {
						$row = array_slice($origrow,0,$sizeofrow-1);
					}
					
					if (! $dest->Execute($sa['INS'],$row)) {
						$err = true;
						$inserr = true;
						if ($this->errHandler) $this->_doerr('INS',$row);
						if ($this->neverAbort) continue;
						else break;
					} else {
						if ($dest->dataProvider == 'oci8') {
							if ($this->oracleSequence) $lastid = $dest->GetOne("select ".$this->oracleSequence.".currVal from dual");
						 	else $lastid = 'null';
						} else { 	
							$lastid = $dest->Insert_ID();
						}
					
						if (!$inserr && !empty($uniqflds)) {
							$this->RunUpdateSrcFn($src, $table, $fldoffsets, $origrow, $srcwheress, 'INS', $lastid);
						}
						$ins += 1;
					} 
				}
				$cnt += 1;

				if ($commitRecs > 0 && ($cnt % $commitRecs) == 0) {
					$dest->CommitTrans();
					$dest->BeginTrans();
					
					if ($this->updateSrcFn) {
						$src->CommitTrans();
						$src->BeginTrans();
					}
				}
				
			} // while 
			
			
			if ($this->commitReplicate || $commitRecs > 0) {
				if (!$this->neverAbort && $err) {
					$dest->RollbackTrans();
					if ($this->updateSrcFn) $src->RollbackTrans();
				} else {
					$dest->CommitTrans();
					if ($this->updateSrcFn) $src->CommitTrans();
				}
			}
		}
		if ($cnt != $ins + $upd) echo "<p>ERROR: $cnt != INS $ins + UPD $upd</p>";
		return array(!$err, $cnt, $ins, $upd);
	}
	// trigger support only for sql server and oracle
	// need to add 
	function MergeSrcSetup($srcTable,  $pkeys, $srcUpdateDateFld, $srcCopyFlagFld, 
		$srcCopyFlagType='C(1)', $srcCopyFlagVals = array('Y','N','P','='))
	{
		$sqla = array();
		$src = $this->connSrc;
		$idx = $srcTable.'_adodb_Merge';
		$cols = $src->MetaColumns($srcTable);
		#adodb_pr($cols);
		if (!isset($cols[strtoupper($srcUpdateDateFld)])) {
			$sqla = $this->ddSrc->AddColumnSQL($srcTable, "$srcUpdateDateFld T DEFTIMESTAMP");
			foreach($sqla as $sql) $src->Execute($sql);
		}
		
		
		$sysdate = $src->sysTimeStamp;
		$arrv0 = $src->qstr($srcCopyFlagVals[0]);
		$arrv1 = $src->qstr($srcCopyFlagVals[1]);
		$arrv2 = $src->qstr($srcCopyFlagVals[2]);
		$arrv3 = $src->qstr($srcCopyFlagVals[3]);
		
		if ($srcCopyFlagFld && !isset($cols[strtoupper($srcCopyFlagFld)])) {
			$sqla = $this->ddSrc->AddColumnSQL($srcTable, "$srcCopyFlagFld  $srcCopyFlagType DEFAULT $arrv1");
			foreach($sqla as $sql) $src->Execute($sql);
		}
		
		$sqla = array();
		$name = "{$srcTable}_adodb_merge_tr";
		if (is_array($pkeys) && strpos($src->databaseType,'mssql') !== false) {		
			$pk = reset($pkeys);
			
			$sqla[] = "DROP TRIGGER $name";
			$sqla[] =" 
	CREATE TRIGGER $name
	ON $srcTable /* for data replication and merge */
	AFTER UPDATE
	AS
	  UPDATE $srcTable
	  SET 
	  	$srcUpdateDateFld = $sysdate,
	  	$srcCopyFlagFld = case 
			when I.$srcCopyFlagFld = $arrv2 then $arrv0 
			when I.$srcCopyFlagFld = $arrv3 then D.$srcCopyFlagFld
			else $arrv1 end
	  FROM $srcTable S Join Inserted AS I on I.$pk = S.$pk
	  JOIN Deleted as D ON I.$pk = D.$pk 
		WHERE I.$srcCopyFlagFld = D.$srcCopyFlagFld or I.$srcCopyFlagFld = $arrv2
	";
		} else if (strpos($src->databaseType,'oci') !== false) {
			
			$sqla[] = "
CREATE OR REPLACE TRIGGER $name /* for data replication and merge */
BEFORE UPDATE ON $srcTable REFERENCING NEW AS NEW OLD AS OLD
FOR EACH ROW
BEGIN
	if :new.$srcCopyFlagFld = $arrv2 then
		:new.$srcCopyFlagFld := $arrv0;
	elsif :new.$srcCopyFlagFld = $arrv3 then
		:new.$srcCopyFlagFld := :old.$srcCopyFlagFld;
	elsif :old.$srcCopyFlagFld = :new.$srcCopyFlagFld then
	 :new.$srcUpdateDateFld := $sysdate;
	 :new.$srcCopyFlagFld := $arrv1;
	end if;
END;
";
		}
		foreach($sqla as $sql) $src->Execute($sql);
		
		if ($srcCopyFlagFld) $srcCopyFlagFld .= ', ';
		$src->Execute("CREATE INDEX {$idx}_idx on $srcTable ($srcCopyFlagFld$srcUpdateDateFld)");
	}
	
	
	/*
		Perform Merge by copying all data modified from src to dest
			then update src copied flag if present.
			
		Returns the current time of the src database before the replication
		
		$srcTable = src table 
		$dstTable = dest table
		$pkeys    = primary keys array. if empty, then only inserts will occur
		$srcignoreflds = ignore these flds (must be upper cased)
		$setsrc        = updateSrcFn string
		$srcUpdateDateFld = field in src with the last update date
		$srcCopyFlagFld = false = optional field that holds the copied indicator
		$flagvals=array('Y','N','P') = array of values indicating array(copied, not copied). 
			Null is assumed to mean not copied. The 3rd value 'P' indicates that we want to force 'Y', bypassing
			default trigger behaviour to reset the COPIED='N' when the record is replicated from other side.
		$srcCopyDateFld = field that holds last copy date in src table, which will be updated on Merge()
		$dstCopyDateFld = field that holds last copy date in dst table, which will be updated on Merge()
		$defaultDestRaiseErrorFn = The adodb raiseErrorFn handler. Default is to not raise an error.
								 	Just output error message to stdout
		
	*/
	function Merge($srcTable, $dstTable, $pkeys, $srcignoreflds, $setsrc,
		$srcUpdateDateFld, 
		$srcCopyFlagFld,  $flagvals=array('Y','N','P','='),
		$srcCopyDateFld = false,
		$dstCopyDateFld = false,
		$defaultDestRaiseErrorFn = '')
	{
		$src = $this->connSrc;
		$dest = $this->connDest;
		
		$time = $src->Time();
		
		$delfirst = $this->deleteFirst;
		$upd = $this->updateSrcFn;
		
		$this->deleteFirst = false;
		$this->updateFirst = true;
		
		$srcignoreflds[] = $srcUpdateDateFld;
		$srcignoreflds[] = $srcCopyFlagFld;
		$srcignoreflds[] = $srcCopyDateFld;
		
		$where = " WHERE ($srcCopyFlagFld is null or $srcCopyFlagFld = ".$src->qstr($flagvals[1]).') ORDER BY '.$srcUpdateDateFld; 
		
		if ($setsrc) $set[] = $setsrc;
		else $set = array();
		
		if ($srcCopyFlagFld) $set[] = "$srcCopyFlagFld = ".$src->qstr($flagvals[2]);
		if ($srcCopyDateFld) $set[]= "$srcCopyDateFld = ".$src->sysTimeStamp;
		if ($set) $this->updateSrcFn = array(implode(', ',$set));
		else $this->updateSrcFn = '';
		
		
		$extra[$srcCopyFlagFld] = array($dest->qstr($flagvals[0]),$dest->qstr($flagvals[3]));
		
		$saveraise = $dest->raiseErrorFn; 
		$dest->raiseErrorFn = '';
		$arr = $this->ReplicateData($srcTable, $dstTable, $pkeys, $where, $srcignoreflds, $dstCopyDateFld,$extra);
		$ok = isset($arr[0]) ? $arr[0] : true;
		
		$dest->raiseErrorFn = $saveraise;
		
		$this->updateSrcFn = $upd;
		$this->deleteFirst = $delfirst;
		
		return $ok;
	}

	function _doerr($reason, $selflds)
	{
		$fn = $this->errHandler;
		if ($fn) $fn($this, $reason, $selflds); // set $this->neverAbort to true or false as required inside $fn
	}
}

?>
