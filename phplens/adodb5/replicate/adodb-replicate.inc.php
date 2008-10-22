<?php

define('ADODB_REPLICATE',1);

include_once(ADODB_DIR.'/adodb-datadict.inc.php');

/*
	Note: this code assumes that the comments allow / *    * / which works with:
		 mssql, postgresql, oracle, mssql
*/

class ADODB_Replicate {
	var $connSrc;
	var $connDest;
	var $ddSrc;
	var $ddDest;
	
	var $execute = false;
	var $debug = false;
	var $deleteFirst = true;
	
	var $selFilter = false;
	var $fieldFilter = false;
	var $indexFilter = false;
	var $updateFilter = false;
	var $updateSrcFn = false;
	
	var $commitRecs = -1; // only commit at end of ReplicateData()
	var $neverAbort = true;
	var $copyTableDefaults = false; // turn off because functions defined as defaults will not work when copied
	var $errHandler = false; // name of error handler function, if used.
	var $htmlSpecialChars = true; // if execute false, then output with htmlspecialchars enabled
	var $_mergeNulls = false; // if merging and null field, then set
	
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
			return $fld;
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
				
				if ($mode == 'INS') {
					if (strlen($dest_insertid)) == 0) $dest_insertid = 'null';
					$set = str_replace('$INSERT_ID',$dest_insertid,$set);
				}
				$srcdb->Execute("UPDATE $table SET $set $where",$bindarr);
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
	
	/*
	// if no uniqflds defined, then all desttable recs will be deleted before insert
	// $where clause must include the WHERE word if used
	// if $this->commitRecs is set to a +ve value, then it will autocommit every $this->commitRecs records 
	//		-- this should never be done with 7x24 db's
	
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
	*/
	
	
	function ReplicateData($table, $desttable = '',  $uniqflds = array(), $where = '')
	{
		$this->_clearcache();
		
		if (!$desttable) $desttable = $table;
		
		$uniq = array();
		if ($uniqflds) {
			foreach($uniqflds as $u) {
				$uniq[strtoupper($u)] = 1;
			}
			$deleteFirst = false;
			$onlyInsert = false;
		} else {
			$deleteFirst = true;
			$onlyInsert = true;
		}
		
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
		$flds = array();
		$wheref = array();
		$wheres = array();
		
		$k = 0;
		foreach($types as $name => $t) {
			$name2 = strtoupper($this->RunFieldFilter($name,'SELECT'));
			if (!isset($dtypes[strtoupper($name2)]) || !$name2) {
				if ($this->debug) echo " Skipping $name as not in destination $desttable<br>";
				continue;
			}
			
			$fld = $t->name;
			$fldval = $t->name;
			$mt = $src->MetaType($t->type);
			if ($mt == 'D') $fldval = $dest->DBDate($fldval);
			elseif ($mt == 'T') $fldval = $dest->DBTimeStamp($fldval);
			
			if ($this->debug) echo " field=$fld type=$mt fldval=$fldval<br>";
			if (!isset($uniq[strtoupper($fld)])) {
				
				$selfld = $fld;
				$fld = $this->RunFieldFilter($selfld,'SELECT');
				$flds[] = $selfld;
				
				$p = $dest->Param($k);
				
				if ($mt == 'D') $p = $dest->DBDate($p, true);
				else if ($mt == 'T') $p = $dest->DBTimeStamp($p, true);
				
				$sets[] = "$fld = ".$this->RunUpdateFilter($desttable, $fld, $p);
				$vals[] = $fldval;
				$params[] = $p;
				$insflds[] = $fld;
				$k++;
			} else {
				$fld = $this->RunFieldFilter($fld);
				$wheref[] = $fld;
			}
		}
		
		
		foreach($wheref as $fld) {
			$flds[] = $fld;
			$params[] = $dest->Param($k);
			$srcwheres[] = $fld.' = '.$src->Param($k);
			$wheres[] = $fld.' = '.$dest->Param($k);
			$insflds[] = $fld;
			$fldoffsets[] = $k;
			$k++;
		}
		
		$insfldss = implode(', ', $insflds);
		$fldss = implode(', ', $flds);
		$valss = implode(', ', $vals);
		$setss = implode(', ', $sets);
		$paramss = implode(', ', $params);
		$wheress = implode('AND ', $wheres);
		$srcwheress = implode('AND ',$srcwheres);
		
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
			$dest->BeginTrans();
			if ($this->updateSrcFn) $src->BeginTrans();
			
			$rs = $src->Execute($sa['SEL']);
			if (!$rs) {
				if ($this->errHandler) $this->_doerr('SEL',array());
				return array(0,0,0,0);
			}
			
			$cnt = 0;
			$upd = 0;
			$ins = 0;
			$fn = $this->selFilter;
			$commitRecs = $this->commitRecs;
			
			while ($row = $rs->FetchRow()) {
				#var_dump($row);
				if ($dest->debug) {flush(); @ob_flush();}
				
			
				if ($fn) {
					if (!$fn($desttable, $row,$deleteFirst,$this)) continue;
				}
				if (!$onlyInsert) {
					if (!$dest->Execute($sa['UPD'],$row)) {
						$err = true;
						if ($this->errHandler) $this->_doerr('UPD',$row);
						if ($this->neverAbort) continue;
						else break;
					}
				 	if ($dest->Affected_Rows() == 0) {
						if (!$dest->Execute($sa['INS'],$row)) {
							$err = true;
							if ($this->errHandler) $this->_doerr('INS',$row);
							if ($this->neverAbort) continue;
							else break;
						}
						$ins += 1;
					} else {
						$this->RunUpdateSrcFn($src, $table,$fldoffsets, $row, $srcwheress,'UPD');
						$upd += 1;
					}
				}  else {
					if (! $dest->Execute($sa['INS'],$row)) {
						$err = true;
						if ($this->errHandler) $this->_doerr('INS',$row);
						if ($this->neverAbort) continue;
						else break;
					} 
					$lastid = $dest->Insert_ID();
					$this->RunUpdateSrcFn($src, $table, $fldoffsets, $row, $srcwheress,'INS',$lastid);
					$ins += 1;
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
			
			if (!$this->neveAbort && $err) {
				$dest->RollbackTrans();
				if ($this->updateSrcFn) $src->RollbackTrans();
			} else {
				$dest->CommitTrans();
				if ($this->updateSrcFn) $src->CommitTrans();
			}
		}		
		if ($cnt != $ins + $upd) echo "<p>ERROR: $cnt != INS $ins + UPD $upd</p>";
		return array(!$err, $cnt, $ins, $upd);
	}
	
	/*
		Perform Merge by copying all data modified from src to dest
			then update copied flag if present.
	*/
	function Merge($srcTable, $dstTable, $pkeys, $lastCopyDate, $insertOnly, $srcUpdateDateFld, $dstUpdateDateFld, 
		$srcCopyFlagFld = false, $dstCopyFlagFld = false, $flagvals=array('Y','N'))
	{
		$delfirst = $this->deleteFirst;
		$this->deleteFirst = false;
		$src = $this->connSrc;
		$dest = $this->connDest;
		
		$this->ReplicateData($srcTable, $dstTable, $pkeys, 'where ');
		
		$this->deleteFirst = $delfirst;
	}
	
	function _doerr($reason, $flds)
	{
		$fn = $this->errHandler;
		if ($fn) $fn($this, $reason, $flds); // set $this->neverAbort to true or false as required inside $fn
	}
}

?>