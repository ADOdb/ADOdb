<?php

/**
 * Provided by Ned Andre to support sqlsrv library
 */
class ADODB_pdo_sqlsrv extends ADODB_pdo
{
	var $hasTop = 'top';
	var $sysDate = 'convert(datetime,convert(char,GetDate(),102),102)';
	var $sysTimeStamp = 'GetDate()';
	
	public $metaTablesSQL="select name,case when type='U' then 'T' else 'V' end from sysobjects where (type='U' or type='V') and (name not in ('sysallocations','syscolumns','syscomments','sysdepends','sysfilegroups','sysfiles','sysfiles1','sysforeignkeys','sysfulltextcatalogs','sysindexes','sysindexkeys','sysmembers','sysobjects','syspermissions','sysprotects','sysreferences','systypes','sysusers','sysalternates','sysconstraints','syssegments','REFERENTIAL_CONSTRAINTS','CHECK_CONSTRAINTS','CONSTRAINT_TABLE_USAGE','CONSTRAINT_COLUMN_USAGE','VIEWS','VIEW_TABLE_USAGE','VIEW_COLUMN_USAGE','SCHEMATA','TABLES','TABLE_CONSTRAINTS','TABLE_PRIVILEGES','COLUMNS','COLUMN_DOMAIN_USAGE','COLUMN_PRIVILEGES','DOMAINS','DOMAIN_CONSTRAINTS','KEY_COLUMN_USAGE','dtproperties'))";

	public $metaColumnsSQL =
		"select c.name,
		t.name as type,
		c.length,
		c.xprec as precision,
		c.xscale as scale,
		c.isnullable as nullable,
		c.cdefault as default_value,
		c.xtype,
		t.length as type_length,
		sc.is_identity
		from syscolumns c
		join systypes t on t.xusertype=c.xusertype
		join sysobjects o on o.id=c.id
		join sys.tables st on st.name=o.name
		join sys.columns sc on sc.object_id = st.object_id and sc.name=c.name
		where o.name='%s'";

	var $arrayClass = 'ADORecordSet_array_pdo_sqlsrv';
	
	public $cachedSchemaFlush = false;


	function _init(ADODB_pdo $parentDriver)
	{
		$parentDriver->hasTransactions = true;
		$parentDriver->_bindInputArray = true;
		$parentDriver->hasInsertID = true;
		$parentDriver->fmtTimeStamp = "'Y-m-d H:i:s'";
		$parentDriver->fmtDate = "'Y-m-d'";
		
		$this->pdoDriver = $parentDriver;
	}

	function BeginTrans()
	{
		$returnval = parent::BeginTrans();
		return $returnval;
	}

	function metaColumns($table, $upper=true, $schema=false){

		/*
		* A simple caching mechanism, to be replaced in ADOdb V6
		*/
		static $cached_columns = array();
		if ($this->cachedSchemaFlush)
			$cached_columns = array();

		if (array_key_exists($table,$cached_columns)){
			return $cached_columns[$table];
		}


		$this->_findschema($table,$schema);
		if ($schema) {
			$dbName = $this->database;
			$this->SelectDB($schema);
		}
		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

		if ($this->fetchMode !== false) $savem = $this->SetFetchMode(false);
		$rs = $this->Execute(sprintf($this->metaColumnsSQL,$table));

		if ($schema) {
			$this->SelectDB($dbName);
		}

		if (isset($savem)) $this->SetFetchMode($savem);
		$ADODB_FETCH_MODE = $save;
		if (!is_object($rs)) {
			$false = false;
			return $false;
		}

		$retarr = array();
		while (!$rs->EOF){

			$fld = new ADOFieldObject();
			if (array_key_exists(0,$rs->fields)) {
				$fld->name          = $rs->fields[0];
				$fld->type          = $rs->fields[1];
				$fld->max_length    = $rs->fields[2];
				$fld->precision     = $rs->fields[3];
				$fld->scale         = $rs->fields[4];
				$fld->not_null      =!$rs->fields[5];
				$fld->has_default   = $rs->fields[6];
				$fld->xtype         = $rs->fields[7];
				$fld->type_length   = $rs->fields[8];
				$fld->auto_increment= $rs->fields[9];
			} else {
				$fld->name          = $rs->fields['name'];
				$fld->type          = $rs->fields['type'];
				$fld->max_length    = $rs->fields['length'];
				$fld->precision     = $rs->fields['precision'];
				$fld->scale         = $rs->fields['scale'];
				$fld->not_null      =!$rs->fields['nullable'];
				$fld->has_default   = $rs->fields['default_value'];
				$fld->xtype         = $rs->fields['xtype'];
				$fld->type_length   = $rs->fields['type_length'];
				$fld->auto_increment= $rs->fields['is_identity'];
			}

			if ($save == ADODB_FETCH_NUM)
				$retarr[] = $fld;
			else
				$retarr[strtoupper($fld->name)] = $fld;

			$rs->MoveNext();

		}
		$rs->Close();
		$cached_columns[$table] = $retarr;

		return $retarr;
	}
	
	function metaIndexes($table,$primary=false, $owner = false)
	{
		
		$table = $this->quote($table);
		$sql = "
		SELECT 
			ind.name IndexName,
			col.name ColumnName,
			ind.is_primary_key PrimaryKey,
			ind.is_unique IsUnique,
			ind.*,
			ic.*,
			col.*
		FROM 
			 sys.indexes ind 
		INNER JOIN 
			 sys.index_columns ic ON  ind.object_id = ic.object_id and ind.index_id = ic.index_id 
		INNER JOIN 
			 sys.columns col ON ic.object_id = col.object_id and ic.column_id = col.column_id 
		INNER JOIN 
			 sys.tables t ON ind.object_id = t.object_id and t.name=$table
		
		ORDER BY 
			 t.name, ind.name, ind.index_id, ic.index_column_id";
		
		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		if ($this->fetchMode !== FALSE) {
			$savem = $this->pdoDriver->SetFetchMode(FALSE);
		}

		$rs = $this->pdoDriver->execute($sql);
		if (isset($savem)) {
			$this->pdoDriver->SetFetchMode($savem);
		}
		//$ADODB_FETCH_MODE = $save;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

		if (!is_object($rs)) {
			return FALSE;
		}

		$indexes = array();
		
		/*
		* These items describe the index itself
		*/
		$indexExtendedAttributeNames = array_flip(array(
		'indexname','columnname','primarykey','isunique','object_id',
		'name','index_id','type','type_desc','is_unique','data_space_id',
		'ignore_dup_key','is_primary_key','is_unique_constraint',
		'fill_factor','is_padded','is_disabled','is_hypothetical',
		'allow_row_locks','allow_page_locks','has_filter','filter_definition'
		));
       
		/*
		* These items describe the column attributes in the index
		*/
		$columnExtendedAttributeNames = array_flip(array(
		'index_column_id','column_id','key_ordinal','partition_ordinal',
		'is_descending_key','is_included_column','system_type_id','user_type_id',
		'max_length','precision','scale','collation_name','is_nullable',
		'is_ansi_padded','is_rowguidcol','is_identity','is_computed',
		'is_filestream','is_replicated','is_non_sql_subscribed',
		'is_merge_published','is_dts_replicated','is_xml_document',
		'xml_collection_id','default_object_id','rule_object_id',
		'is_sparse' ,'is_column_set'
		));
	
		while ($row = $rs->FetchRow()) {
			
			/*
			* Dont know what casing is set on the driver, so artificially
			* convert keys to lower case
			*/
			$row  = array_change_key_case($row);
						
			if (!$primary && $row['primarykey']) 
				continue;

			/*
			* First iteration of index, build format
			*/
			if (!isset($indexes[$row['indexname']])) 
			{
				if ($this->suppressExtendedMetaIndexes)
					$indexes[$row['indexname']] = $this->legacyMetaIndexFormat;
				else
					$indexes[$row['indexname']] = $this->extendedMetaIndexFormat;
				
				$indexes[$row['indexname']]['unique']    = $row['isunique'];
				$indexes[$row['indexname']]['primary']   = $row['primarykey'];

				
				if (!$this->suppressExtendedMetaIndexes)
				{
					/*
					* We need to extract the 'index' specific itema
					* from the extended attributes
					*/
					$iAttributes = array_intersect_key($row,$indexExtendedAttributeNames);
					$indexes[$row['indexname']]['index-attributes'] = $iAttributes;
				}
			}
			
			
			$indexes[$row['indexname']]['columns'][] = $row['columnname'];
			
			if (!$this->suppressExtendedMetaIndexes)
			{
				/*
				* We need to extract the 'column' specific itema
				* from the extended attributes
				*/
				$cAttributes = array_intersect_key($row,$columnExtendedAttributeNames);
				$indexes[$row['indexname']]['column-attributes'][$row['columnname']] = $cAttributes;
			}
		}
		return $indexes;
	}
	
	function metaTables($ttype=false,$showSchema=false,$mask=false)
	{
		if ($mask) {
			$save = $this->metaTablesSQL;
			$mask = $this->pdoDriver->qstr(($mask));
			$this->metaTablesSQL .= " AND name like $mask";
		}
		
		$ret = ADOConnection::MetaTables($ttype,$showSchema);

		if ($mask) {
			$this->metaTablesSQL = $save;
		}
		return $ret;
	}
	
	function SelectLimit($sql, $nrows = -1, $offset = -1, $inputarr = false, $secs2cache = 0)
	{
		$ret = ADOConnection::SelectLimit($sql, $nrows, $offset, $inputarr, $secs2cache);
		return $ret;
	}

	function ServerInfo()
	{
		return ADOConnection::ServerInfo();
	}
}

class ADORecordSet_pdo_sqlsrv extends ADORecordSet_pdo
{

	public $databaseType = "pdo_sqlsrv";

	/**
	 * returns the field object
	 *
	 * @param  int $fieldOffset Optional field offset
	 *
	 * @return object The ADOFieldObject describing the field
	 */
	public function fetchField($fieldOffset = 0)
	{

		// Default behavior allows passing in of -1 offset, which crashes the method
		if ($fieldOffset == -1) {
			$fieldOffset++;
		}

		$o = new ADOFieldObject();
		$arr = @$this->_queryID->getColumnMeta($fieldOffset);

		if (!$arr) {
			$o->name = 'bad getColumnMeta()';
			$o->max_length = -1;
			$o->type = 'VARCHAR';
			$o->precision = 0;
			return $o;
		}
		$o->name = $arr['name'];
		if (isset($arr['sqlsrv:decl_type']) && $arr['sqlsrv:decl_type'] <> "null") {
			// Use the SQL Server driver specific value
			$o->type = $arr['sqlsrv:decl_type'];
		} else {
			$o->type = adodb_pdo_type($arr['pdo_type']);
		}
		$o->max_length = $arr['len'];
		$o->precision = $arr['precision'];

		switch (ADODB_ASSOC_CASE) {
			case ADODB_ASSOC_CASE_LOWER:
				$o->name = strtolower($o->name);
				break;
			case ADODB_ASSOC_CASE_UPPER:
				$o->name = strtoupper($o->name);
				break;
		}

		return $o;
	}
}

class ADORecordSet_array_pdo_sqlsrv extends ADORecordSet_array_pdo
{

	/**
	 * returns the field object
	 *
	 * Note that this is a direct copy of the ADORecordSet_pdo_sqlsrv method
	 *
	 * @param  int $fieldOffset Optional field offset
	 *
	 * @return object The ADOfieldobject describing the field
	 */
	public function fetchField($fieldOffset = 0)
	{
		// Default behavior allows passing in of -1 offset, which crashes the method
		if ($fieldOffset == -1) {
			$fieldOffset++;
		}

		$o = new ADOFieldObject();
		$arr = @$this->_queryID->getColumnMeta($fieldOffset);

		if (!$arr) {
			$o->name = 'bad getColumnMeta()';
			$o->max_length = -1;
			$o->type = 'VARCHAR';
			$o->precision = 0;
			return $o;
		}
		$o->name = $arr['name'];
		if (isset($arr['sqlsrv:decl_type']) && $arr['sqlsrv:decl_type'] <> "null") {
			// Use the SQL Server driver specific value
			$o->type = $arr['sqlsrv:decl_type'];
		} else {
			$o->type = adodb_pdo_type($arr['pdo_type']);
		}
		$o->max_length = $arr['len'];
		$o->precision = $arr['precision'];

		switch (ADODB_ASSOC_CASE) {
			case ADODB_ASSOC_CASE_LOWER:
				$o->name = strtolower($o->name);
				break;
			case ADODB_ASSOC_CASE_UPPER:
				$o->name = strtoupper($o->name);
				break;
		}

		return $o;
	}
	
	function SetTransactionMode( $transaction_mode )
	{
		$this->_transmode  = $transaction_mode;
		if (empty($transaction_mode)) {
			$this->_connectionID->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
			return;
		}
		if (!stristr($transaction_mode,'isolation')) $transaction_mode = 'ISOLATION LEVEL '.$transaction_mode;
		$this->_connectionID->query("SET TRANSACTION ".$transaction_mode);
	}
}
