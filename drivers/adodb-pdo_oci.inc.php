<?php


/*
@version   v5.21.0-dev  ??-???-2016
@copyright (c) 2000-2013 John Lim (jlim#natsoft.com). All rights reserved.
@copyright (c) 2014      Damien Regad, Mark Newnham and the ADOdb community
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
		
		$this->pdoDriver = $parentDriver;
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
	
	function MetaIndexes ($table, $primary = FALSE, $owner=false)
	{
		/*
		* save old fetch mode
		*/
		global $ADODB_FETCH_MODE;

		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

		$parent = $this->pdoDriver;


		if ($parent->fetchMode !== FALSE) {
			$savem = $parent->SetFetchMode(FALSE);
		}

		// get index details
		$table = strtoupper($table);

		/*
		* get Primary index
		*/
		$primary_key = '';

		//$p1 = $parent->param('p1');
		//$bind = array('p1'=>$table);
		$table = $parent->quote($table);
		/*
		* Separate statement to retrieve the primary key
		*/
		$sql = "SELECT CONSTRAINT_NAME
		         FROM ALL_CONSTRAINTS 
				 WHERE UPPER(TABLE_NAME)=$table
				 AND CONSTRAINT_TYPE='P'";
				 
		$primary_key = $parent->getOne($sql);
		if (!$primary_key) {
			if (isset($savem)) {
				$parent->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			return false;
		}

		if ($primary==TRUE && $primary_key=='') {
			if (isset($savem)) {
				$parent->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			return false; //There is no primary key
		}
		if ($this->suppressExtendedMetaIndexes)
			$sql = "SELECT ALL_INDEXES.INDEX_NAME, 
						   ALL_INDEXES.UNIQUENESS, 
						   ALL_IND_COLUMNS.COLUMN_POSITION, 
						   ALL_IND_COLUMNS.COLUMN_NAME
					  FROM ALL_INDEXES,ALL_IND_COLUMNS 
					 WHERE UPPER(ALL_INDEXES.TABLE_NAME)=$table`
					   AND ALL_IND_COLUMNS.INDEX_NAME=ALL_INDEXES.INDEX_NAME
					   ORDER BY ALL_IND_COLUMNS.INDEX_NAME,
					   ALL_IND_COLUMNS.COLUMN_POSITION";
		else				
			$sql = "SELECT ALL_IND_COLUMNS.*,
						   ALL_INDEXES.*
					  FROM ALL_INDEXES,ALL_IND_COLUMNS 
					 WHERE UPPER(ALL_INDEXES.TABLE_NAME)=$table 
					   AND ALL_IND_COLUMNS.INDEX_NAME=ALL_INDEXES.INDEX_NAME
					   ORDER BY ALL_IND_COLUMNS.INDEX_NAME,
					   ALL_IND_COLUMNS.COLUMN_POSITION";

		
		$rs = $parent->execute($sql);

		if (!is_object($rs)) {
			if (isset($savem)) {
				$parent->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			return false;
		}
		
		$indexes = array();
		/*
		* These items describe the entire record
		*/
		$extendedAttributeNames = array_flip(array(
		'index_name','uniqueness','column_position','column_name' 
		,'index_owner','table_owner','table_name','char_length'
		,'descend','collated_column_id','owner','index_type'
		,'table_type','compression','prefix_length'
		,'tablespace_name','ini_trans','max_trans','initial_extent','next_extent'
		,'min_extents','max_extents','pct_increase','pct_threshold' 
		,'include_column','freelists','freelist_groups','pct_free'
		,'logging','blevel','leaf_blocks','distinct_keys','avg_leaf_blocks_per_key'
		,'avg_data_blocks_per_key','clustering_factor','status','num_rows'
		,'sample_size','last_analyzed','degree','instances','partitioned'
		,'temporary','generated','secondary','buffer_pool','flash_cache'
		,'cell_flash_cache','user_stats','duration','pct_direct_access'
		,'ityp_owner','ityp_name','parameters','global_stats'
		,'domidx_status','domidx_opstatus','funcidx_status','join_index'
		,'iot_redundant_pkey_elim','dropped','visibility','domidx_management' 
		,'segment_created','orphaned_entries','indexing'
		));
		
		/*
		* These items describe the index itself
		*/
		$indexExtendedAttributeNames = array_flip(array(
		'index_name','uniqueness','column_position','column_name' 
		,'index_owner','table_owner','table_name','char_length'
		,'descend','collated_column_id'));
		
		/*
		* These items describe the column attributes in the index
		*/
		$columnExtendedAttributeNames = array_flip(array(
		'owner','index_type','table_type','compression','prefix_length'
		,'tablespace_name','ini_trans','max_trans','initial_extent','next_extent'
		,'min_extents','max_extents','pct_increase','pct_threshold' 
		,'include_column','freelists','freelist_groups','pct_free'
		,'logging','blevel','leaf_blocks','distinct_keys','avg_leaf_blocks_per_key'
		,'avg_data_blocks_per_key','clustering_factor','status','num_rows'
		,'sample_size','last_analyzed','degree','instances','partitioned'
		,'temporary','generated','secondary','buffer_pool','flash_cache'
		,'cell_flash_cache','user_stats','duration','pct_direct_access'
		,'ityp_owner','ityp_name','parameters','global_stats'
		,'domidx_status','domidx_opstatus','funcidx_status','join_index'
		,'iot_redundant_pkey_elim','dropped','visibility','domidx_management' 
		,'segment_created','orphaned_entries','indexing'));
		
		$indexes = array ();
		/*
		* parse index data into array
		*/
		
		while ($row = $rs->FetchRow()) {
			
			$row = array_change_key_case($row);
			
			if ($primary && $row['index_name'] != $primary_key) 
				continue;
						
					
			if (!isset($indexes[$row['index_name']])) {
				
				if ($this->suppressExtendedMetaIndexes)
					$indexes[$row['index_name']] = $this->legacyMetaIndexFormat;
				else
					$indexes[$row['index_name']] = $this->extendedMetaIndexFormat;
				
				$indexes[$row['index_name']]['unique'] = ($row['uniqueness'] == 'UNIQUE');
				$indexes[$row['index_name']]['primary'] = ($row['index_name'] == $primary_key);
			
				if (!$this->suppressExtendedMetaIndexes)
				{
					/*
					* We need to extract the 'index' specific itema
					* from the extended attributes
					*/
					$iAttributes = array_intersect_key($row,$indexExtendedAttributeNames);
					$indexes[$row['index_name']]['index-attributes'] = $iAttributes;
				}
			}
			$indexes[$row['index_name']]['columns'][] = $row['column_name'];
			
			if (!$this->suppressExtendedMetaIndexes)
			{
				/*
				* We need to extract the 'column' specific itema
				* from the extended attributes
				*/
				$cAttributes = array_intersect_key($row,$columnExtendedAttributeNames);
				$indexes[$row['index_name']]['column-attributes'][$row['column_name']] = $cAttributes;
			}
		}

		if (isset($savem)) {
			$this->SetFetchMode($savem);
			$ADODB_FETCH_MODE = $save;
		}
		return $indexes;
	}

    /**
     * @param bool $auto_commit
     * @return void
     */
    function SetAutoCommit($auto_commit)
    {
        $this->_connectionID->setAttribute(PDO::ATTR_AUTOCOMMIT, $auto_commit);
    }
}
