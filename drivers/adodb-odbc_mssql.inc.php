<?php
/*
@version   v5.21.0-dev  ??-???-2016
@copyright (c) 2000-2013 John Lim (jlim#natsoft.com). All rights reserved.
@copyright (c) 2014      Damien Regad, Mark Newnham and the ADOdb community
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.
Set tabs to 4 for best viewing.

  Latest version is available at http://adodb.org/

  MSSQL support via ODBC. Requires ODBC. Works on Windows and Unix.
  For Unix configuration, see http://phpbuilder.com/columns/alberto20000919.php3
*/

// security - hide paths
if (!defined('ADODB_DIR')) die();

if (!defined('_ADODB_ODBC_LAYER')) {
	include_once(ADODB_DIR."/drivers/adodb-odbc.inc.php");
}


class  ADODB_odbc_mssql extends ADODB_odbc {
	var $databaseType = 'odbc_mssql';
	var $fmtDate = "'Y-m-d'";
	var $fmtTimeStamp = "'Y-m-d\TH:i:s'";
	var $_bindInputArray = true;
	var $metaDatabasesSQL = "select name from sysdatabases where name <> 'master'";
	var $metaTablesSQL="select name,case when type='U' then 'T' else 'V' end from sysobjects where (type='U' or type='V') and (name not in ('sysallocations','syscolumns','syscomments','sysdepends','sysfilegroups','sysfiles','sysfiles1','sysforeignkeys','sysfulltextcatalogs','sysindexes','sysindexkeys','sysmembers','sysobjects','syspermissions','sysprotects','sysreferences','systypes','sysusers','sysalternates','sysconstraints','syssegments','REFERENTIAL_CONSTRAINTS','CHECK_CONSTRAINTS','CONSTRAINT_TABLE_USAGE','CONSTRAINT_COLUMN_USAGE','VIEWS','VIEW_TABLE_USAGE','VIEW_COLUMN_USAGE','SCHEMATA','TABLES','TABLE_CONSTRAINTS','TABLE_PRIVILEGES','COLUMNS','COLUMN_DOMAIN_USAGE','COLUMN_PRIVILEGES','DOMAINS','DOMAIN_CONSTRAINTS','KEY_COLUMN_USAGE'))";
	var $metaColumnsSQL = # xtype==61 is datetime
	"select c.name,t.name,c.length,c.isnullable, c.status,
		(case when c.xusertype=61 then 0 else c.xprec end),
		(case when c.xusertype=61 then 0 else c.xscale end)
		from syscolumns c join systypes t on t.xusertype=c.xusertype join sysobjects o on o.id=c.id where o.name='%s'";
	var $hasTop = 'top';		// support mssql/interbase SELECT TOP 10 * FROM TABLE
	var $sysDate = 'GetDate()';
	var $sysTimeStamp = 'GetDate()';
	var $leftOuter = '*=';
	var $rightOuter = '=*';
	var $substr = 'substring';
	var $length = 'len';
	var $ansiOuter = true; // for mssql7 or later
	var $identitySQL = 'select SCOPE_IDENTITY()'; // 'select SCOPE_IDENTITY'; # for mssql 2000
	var $hasInsertID = true;
	var $connectStmt = 'SET CONCAT_NULL_YIELDS_NULL OFF'; # When SET CONCAT_NULL_YIELDS_NULL is ON,
														  # concatenating a null value with a string yields a NULL result

	// crashes php...
	function ServerInfo()
	{
	global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		$row = $this->GetRow("execute sp_server_info 2");
		$ADODB_FETCH_MODE = $save;
		if (!is_array($row)) return false;
		$arr['description'] = $row[2];
		$arr['version'] = ADOConnection::_findvers($arr['description']);
		return $arr;
	}

	function IfNull( $field, $ifNull )
	{
		return " ISNULL($field, $ifNull) "; // if MS SQL Server
	}

	function _insertid()
	{
	// SCOPE_IDENTITY()
	// Returns the last IDENTITY value inserted into an IDENTITY column in
	// the same scope. A scope is a module -- a stored procedure, trigger,
	// function, or batch. Thus, two statements are in the same scope if
	// they are in the same stored procedure, function, or batch.
			return $this->GetOne($this->identitySQL);
	}


	function MetaForeignKeys($table, $owner=false, $upper=false)
	{
	global $ADODB_FETCH_MODE;

		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		$table = $this->qstr(strtoupper($table));

		$sql =
"select object_name(constid) as constraint_name,
	col_name(fkeyid, fkey) as column_name,
	object_name(rkeyid) as referenced_table_name,
   	col_name(rkeyid, rkey) as referenced_column_name
from sysforeignkeys
where upper(object_name(fkeyid)) = $table
order by constraint_name, referenced_table_name, keyno";

		$constraints = $this->GetArray($sql);

		$ADODB_FETCH_MODE = $save;

		$arr = false;
		foreach($constraints as $constr) {
			//print_r($constr);
			$arr[$constr[0]][$constr[2]][] = $constr[1].'='.$constr[3];
		}
		if (!$arr) return false;

		$arr2 = false;

		foreach($arr as $k => $v) {
			foreach($v as $a => $b) {
				if ($upper) $a = strtoupper($a);
				$arr2[$a] = $b;
			}
		}
		return $arr2;
	}

	function MetaTables($ttype=false,$showSchema=false,$mask=false)
	{
		if ($mask) {//$this->debug=1;
			$save = $this->metaTablesSQL;
			$mask = $this->qstr($mask);
			$this->metaTablesSQL .= " AND name like $mask";
		}
		$ret = ADOConnection::MetaTables($ttype,$showSchema);

		if ($mask) {
			$this->metaTablesSQL = $save;
		}
		return $ret;
	}

	function MetaColumns($table, $normalize=true)
	{

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
			$fld->name = $rs->fields[0];
			$fld->type = $rs->fields[1];

			$fld->not_null = (!$rs->fields[3]);
			$fld->auto_increment = ($rs->fields[4] == 128);		// sys.syscolumns status field. 0x80 = 128 ref: http://msdn.microsoft.com/en-us/library/ms186816.aspx


			if (isset($rs->fields[5]) && $rs->fields[5]) {
				if ($rs->fields[5]>0) $fld->max_length = $rs->fields[5];
				$fld->scale = $rs->fields[6];
				if ($fld->scale>0) $fld->max_length += 1;
			} else
				$fld->max_length = $rs->fields[2];


			if ($save == ADODB_FETCH_NUM) {
				$retarr[] = $fld;
			} else {
				$retarr[strtoupper($fld->name)] = $fld;
			}
				$rs->MoveNext();
			}

			$rs->Close();
			return $retarr;

	}


	function metaIndexes($table,$primary=false, $owner = false)
	{
		$table = $this->addq($table);
		$p1 = $this->param('p1');
		$bind = array('p1'=>$table);
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
			 sys.tables t ON ind.object_id = t.object_id and t.name=$p1
		
		ORDER BY 
			 t.name, ind.name, ind.index_id, ic.index_column_id";
		
		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		if ($this->fetchMode !== FALSE) {
			$savem = $this->SetFetchMode(FALSE);
		}

		$rs = $this->execute($sql,$bind);
		if (isset($savem)) {
			$this->SetFetchMode($savem);
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

	function _query($sql,$inputarr=false)
	{
		if (is_string($sql)) $sql = str_replace('||','+',$sql);
		return ADODB_odbc::_query($sql,$inputarr);
	}

	function SetTransactionMode( $transaction_mode )
	{
		$this->_transmode  = $transaction_mode;
		if (empty($transaction_mode)) {
			$this->Execute('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
			return;
		}
		if (!stristr($transaction_mode,'isolation')) $transaction_mode = 'ISOLATION LEVEL '.$transaction_mode;
		$this->Execute("SET TRANSACTION ".$transaction_mode);
	}

	// "Stein-Aksel Basma" <basma@accelero.no>
	// tested with MSSQL 2000
	function MetaPrimaryKeys($table, $owner = false)
	{
	global $ADODB_FETCH_MODE;

		$schema = '';
		$this->_findschema($table,$schema);
		//if (!$schema) $schema = $this->database;
		if ($schema) $schema = "and k.table_catalog like '$schema%'";

		$sql = "select distinct k.column_name,ordinal_position from information_schema.key_column_usage k,
		information_schema.table_constraints tc
		where tc.constraint_name = k.constraint_name and tc.constraint_type =
		'PRIMARY KEY' and k.table_name = '$table' $schema order by ordinal_position ";

		$savem = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
		$a = $this->GetCol($sql);
		$ADODB_FETCH_MODE = $savem;

		if ($a && sizeof($a)>0) return $a;
		$false = false;
		return $false;
	}

	function SelectLimit($sql,$nrows=-1,$offset=-1, $inputarr=false,$secs2cache=0)
	{
		$nrows = (int) $nrows;
		$offset = (int) $offset;
		if ($nrows > 0 && $offset <= 0) {
			$sql = preg_replace(
				'/(^\s*select\s+(distinctrow|distinct)?)/i','\\1 '.$this->hasTop." $nrows ",$sql);
			$rs = $this->Execute($sql,$inputarr);
		} else
			$rs = ADOConnection::SelectLimit($sql,$nrows,$offset,$inputarr,$secs2cache);

		return $rs;
	}

	// Format date column in sql string given an input format that understands Y M D
	function SQLDate($fmt, $col=false)
	{
		if (!$col) $col = $this->sysTimeStamp;
		$s = '';

		$len = strlen($fmt);
		for ($i=0; $i < $len; $i++) {
			if ($s) $s .= '+';
			$ch = $fmt[$i];
			switch($ch) {
			case 'Y':
			case 'y':
				$s .= "datename(yyyy,$col)";
				break;
			case 'M':
				$s .= "convert(char(3),$col,0)";
				break;
			case 'm':
				$s .= "replace(str(month($col),2),' ','0')";
				break;
			case 'Q':
			case 'q':
				$s .= "datename(quarter,$col)";
				break;
			case 'D':
			case 'd':
				$s .= "replace(str(day($col),2),' ','0')";
				break;
			case 'h':
				$s .= "substring(convert(char(14),$col,0),13,2)";
				break;

			case 'H':
				$s .= "replace(str(datepart(hh,$col),2),' ','0')";
				break;

			case 'i':
				$s .= "replace(str(datepart(mi,$col),2),' ','0')";
				break;
			case 's':
				$s .= "replace(str(datepart(ss,$col),2),' ','0')";
				break;
			case 'a':
			case 'A':
				$s .= "substring(convert(char(19),$col,0),18,2)";
				break;

			default:
				if ($ch == '\\') {
					$i++;
					$ch = substr($fmt,$i,1);
				}
				$s .= $this->qstr($ch);
				break;
			}
		}
		return $s;
	}
	
	/**
	* Returns a substring of a varchar type field
	*
	* The SQL server version varies because the length is mandatory, so
	* we append a reasonable string length
	*
	* @param	string	$fld	The field to sub-string
	* @param	int		$start	The start point
	* @param	int		$length	An optional length
	*
	* @return	The SQL text
	*/
	function substr($fld,$start,$length=0)
	{
		if ($length == 0)
			/*
		     * The length available to varchar is 2GB, but that makes no
			 * sense in a substring, so I'm going to arbitrarily limit 
			 * the length to 1K, but you could change it if you want
			 */
			$length = 1024;
		
		$text = "SUBSTRING($fld,$start,$length)";
		return $text;
	}
	
	/**
	* Returns the maximum size of a MetaType C field. Because of the 
	* database design, SQL Server places no limits on the size of data inserted
	* Although the actual limit is 2^31-1 bytes.
	*
	* @return int
	*/
	function charMax()
	{
		return ADODB_STRINGMAX_NOLIMIT;
	}

	/**
	* Returns the maximum size of a MetaType X field. Because of the 
	* database design, SQL Server places no limits on the size of data inserted
	* Although the actual limit is 2^31-1 bytes.
	*
	* @return int
	*/
	function textMax()
	{
		return ADODB_STRINGMAX_NOLIMIT;
	}
	
	// returns concatenated string
	// MSSQL requires integers to be cast as strings
	// automatically cast every datatype to VARCHAR(255)
	// @author David Rogers (introspectshun)
	function Concat()
	{
		$s = "";
		$arr = func_get_args();

		// Split single record on commas, if possible
		if (sizeof($arr) == 1) {
			foreach ($arr as $arg) {
				$args = explode(',', $arg);
			}
			$arr = $args;
		}

		array_walk(
			$arr,
			function(&$value, $key) {
				$value = "CAST(" . $value . " AS VARCHAR(255))";
			}
		);
		$s = implode('+',$arr);
		if (sizeof($arr) > 0) return "$s";

		return '';
	}

}

class  ADORecordSet_odbc_mssql extends ADORecordSet_odbc {

	var $databaseType = 'odbc_mssql';

}
