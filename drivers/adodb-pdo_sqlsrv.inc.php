<?php
/**
 * PDO sqlsrv driver
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
 * @author Ned Andre
 */

class ADODB_pdo_sqlsrv extends ADODB_pdo
{
	var $hasTop = 'top';
	var $sysDate = 'convert(datetime,convert(char,GetDate(),102),102)';
	var $sysTimeStamp = 'GetDate()';
	var $arrayClass = 'ADORecordSet_array_pdo_sqlsrv';
	var $metaDatabasesSQL = "select name from sys.sysdatabases where name <> 'master'";
	var $metaTablesSQL="select name,case when type='U' then 'T' else 'V' end from sysobjects where (type='U' or type='V') and (name not in ('sysallocations','syscolumns','syscomments','sysdepends','sysfilegroups','sysfiles','sysfiles1','sysforeignkeys','sysfulltextcatalogs','sysindexes','sysindexkeys','sysmembers','sysobjects','syspermissions','sysprotects','sysreferences','systypes','sysusers','sysalternates','sysconstraints','syssegments','REFERENTIAL_CONSTRAINTS','CHECK_CONSTRAINTS','CONSTRAINT_TABLE_USAGE','CONSTRAINT_COLUMN_USAGE','VIEWS','VIEW_TABLE_USAGE','VIEW_COLUMN_USAGE','SCHEMATA','TABLES','TABLE_CONSTRAINTS','TABLE_PRIVILEGES','COLUMNS','COLUMN_DOMAIN_USAGE','COLUMN_PRIVILEGES','DOMAINS','DOMAIN_CONSTRAINTS','KEY_COLUMN_USAGE','dtproperties'))";
	var $metaColumnsSQL =
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

	public $hasTransactions = true;
	public $_bindInputArray = true;
	public $hasInsertID 	= true;
	public $fmtTimeStamp 	= "'Y-m-d H:i:s'";

	public $cachedSchemaFlush = false;

	public string $mssql_version = '';

	protected int $sequence = 0;

	protected array $sequences = [];

	function x_init(ADODB_pdo $parentDriver)
	{

	}

	/**
	 * Begins a granular transaction.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:begintrans
	 *
	 * @return bool Always returns true.
	 */
	public function beginTrans()
	{
		if ($this->transOff) 
			return true;
		$this->transCnt += 1;
		
		if (empty($this->_transmode))
		{
			$this->_connectionID->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
			return;
		}

		$transaction_mode = $this->_transmode;
		if (!stristr($this->_transmode,'isolation')) 
			$transaction_mode = 'ISOLATION LEVEL '.$transaction_mode;
		
		$this->_connectionID->query("SET TRANSACTION ".$transaction_mode);
	}

	/**
	 * List indexes on a table as an array.
	 * @param table  table name to query
	 * @param primary true to only show primary keys. Not actually used for most databases
	 *
	 * @return array of indexes on current table. Each element represents an index, and is itself an associative array.
	 *
	 * Array(
	 *   [name_of_index] => Array(
	 *     [unique] => true or false
	 *     [columns] => Array(
	 *       [0] => firstname
	 *       [1] => lastname
	 *     )
	 *   )
	 * )
	 */
	public function MetaIndexes($table,$primary=false, $owner = false)
	{
		$table = $this->qstr($table);

		$sql = "SELECT i.name AS ind_name, C.name AS col_name, USER_NAME(O.uid) AS Owner, c.colid, k.Keyno,
			CASE WHEN I.indid BETWEEN 1 AND 254 AND (I.status & 2048 = 2048 OR I.Status = 16402 AND O.XType = 'V') THEN 1 ELSE 0 END AS IsPK,
			CASE WHEN I.status & 2 = 2 THEN 1 ELSE 0 END AS IsUnique
			FROM dbo.sysobjects o INNER JOIN dbo.sysindexes I ON o.id = i.id
			INNER JOIN dbo.sysindexkeys K ON I.id = K.id AND I.Indid = K.Indid
			INNER JOIN dbo.syscolumns c ON K.id = C.id AND K.colid = C.Colid
			WHERE LEFT(i.name, 8) <> '_WA_Sys_' AND o.status >= 0 AND O.Name LIKE $table
			ORDER BY O.name, I.Name, K.keyno";

		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		if ($this->fetchMode !== FALSE) {
			$savem = $this->SetFetchMode(FALSE);
		}

		$rs = $this->Execute($sql);
		if (isset($savem)) {
			$this->SetFetchMode($savem);
		}
		$ADODB_FETCH_MODE = $save;

		if (!is_object($rs)) {
			return FALSE;
		}

		$indexes = array();
		while ($row = $rs->FetchRow()) {
			if (!$primary && $row[5]) continue;

			$indexes[$row[0]]['unique'] = $row[6];
			$indexes[$row[0]]['columns'][] = $row[1];
		}
		return $indexes;
	}

	/**
	 * Returns a list of Foreign Keys associated with a specific table.
	 *
	 * If there are no foreign keys then the function returns false.
	 *
	 * @param string $table       The name of the table to get the foreign keys for.
	 * @param string $owner       Table owner/schema.
	 * @param bool   $upper       If true, only matches the table with the uppercase name.
	 * @param bool   $associative Returns the result in associative mode;
	 *                            if ADODB_FETCH_MODE is already associative, then
	 *                            this parameter is discarded.
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
				if (is_array($arr2[$a])) {	// a previous foreign key was define for this reference table, we merge the new one
					$arr2[$a] = array_merge($arr2[$a], $b);
				} else {
					$arr2[$a] = $b;
				}
			}
		}
		return $arr2;
	}

	/**
	 * Returns a list of databases
	 * 
	 * @return array
	 */
	public function metaDatabases()
	{
		$this->SelectDB("master");
		$savem = $this->fetchMode;
		$this->setFetchMode(ADODB_FETCH_NUM);
		$rs = $this->Execute($this->metaDatabasesSQL);
		$rows = $rs->GetRows();
		$ret = array();
		for($i=0;$i<count($rows);$i++) {
			$ret[] = $rows[$i][0];
		}
		$this->SelectDB($this->database);

		$this->setFetchMode($savem);
		if($ret)
			return $ret;
		else
			return false;
	}

	/**
	 * Returns information about a tables primary keys
	 *
	 * @param string $table The table to check
	 * @param null $owner (Optional) Unused.
	 *
	 * @return mixed
	 */
	public function metaPrimaryKeys($table, $owner=false)
	{
		global $ADODB_FETCH_MODE;

		$schema = '';
		$this->_findschema($table,$schema);
		if (!$schema) $schema = $this->database;
		if ($schema) $schema = "and k.table_catalog like '$schema%'";

		$sql = "select distinct k.column_name,ordinal_position from information_schema.key_column_usage k,
		information_schema.table_constraints tc
		where tc.constraint_name = k.constraint_name and tc.constraint_type =
		'PRIMARY KEY' and k.table_name = '$table' $schema order by ordinal_position ";

		$savem = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		$a = $this->GetCol($sql);
		$ADODB_FETCH_MODE = $savem;

		if ($a && sizeof($a)>0) return $a;
		$false = false;
		return $false;
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
	public function metaColumns($table, $normalize = true)
	{
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

	/**
	 * Retrieves a list of tables based on given criteria
	 *
	 * @param string|bool $ttype (Optional) Table type = 'TABLE', 'VIEW' or false=both (default)
	 * @param string|bool $showSchema (Optional) schema name, false = current schema (default)
	 * @param string|bool $mask (Optional) filters the table by name
	 *
	 * @return array list of tables
	 */
	public function metaTables($ttype = false, $showSchema = false, $mask = false)
	{
		if ($mask) {
			$save = $this->metaTablesSQL;
			$mask = $this->qstr(($mask));
			$this->metaTablesSQL .= " AND name like $mask";
		}
		$ret = ADOConnection::MetaTables($ttype,$showSchema);

		if ($mask) {
			$this->metaTablesSQL = $save;
		}
		return $ret;
	}

	/**
	 * Lists procedures, functions and methods in an array.
	 *
	 * @param	string $procedureNamePattern (optional)
	 * @param	string $catalog				 (optional)
	 * @param	string $schemaPattern		 (optional)

	 * @return array of stored objects in current database.
	 *
	 */
	public function metaProcedures($procedureNamePattern = null, $catalog  = null, $schemaPattern  = null)
	{
		$metaProcedures = array();
		$procedureSQL   = '';
		$catalogSQL     = '';
		$schemaSQL      = '';

		if ($procedureNamePattern)
			$procedureSQL = "AND ROUTINE_NAME LIKE " . strtoupper($this->qstr($procedureNamePattern));

		if ($catalog)
			$catalogSQL = "AND SPECIFIC_SCHEMA=" . strtoupper($this->qstr($catalog));

		if ($schemaPattern)
			$schemaSQL = "AND ROUTINE_SCHEMA LIKE {$this->qstr($schemaPattern)}";

		$fields = "	ROUTINE_NAME,ROUTINE_TYPE,ROUTINE_SCHEMA,ROUTINE_CATALOG";

		$SQL = "SELECT $fields
			FROM {$this->database}.information_schema.routines
			WHERE 1=1
				$procedureSQL
				$catalogSQL
				$schemaSQL
			ORDER BY ROUTINE_NAME
			";

		$result = $this->execute($SQL);

		if (!$result)
			return false;
		while ($r = $result->fetchRow()){
			if (!isset($r[0]))
				/*
				* Convert to numeric
				*/
				$r = array_values($r);

			$procedureName = $r[0];
			$schemaName    = $r[2];
			$routineCatalog= $r[3];
			$metaProcedures[$procedureName] = array('type'=> $r[1],
												   'catalog' => $routineCatalog,
												   'schema'  => $schemaName,
												   'remarks' => '',
												    );
		}

		return $metaProcedures;
	}

	/**
	 * Executes a provided SQL statement and returns a handle to the result, with the ability to supply a starting
	 * offset and record count.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:selectlimit
	 *
	 * @param string $sql The SQL to execute.
	 * @param int $nrows (Optional) The limit for the number of records you want returned. By default, all results.
	 * @param int $offset (Optional) The offset to use when selecting the results. By default, no offset.
	 * @param array|bool $inputarr (Optional) Any parameter values required by the SQL statement, or false if none.
	 * @param int $secs (Optional) If greater than 0, perform a cached execute. By default, normal execution.
	 *
	 * @return ADORecordSet|false The query results, or false if the query failed to execute.
	 */
	public function selectLimit($sql, $nrows = -1, $offset = -1, $inputarr = false, $secs2cache = 0)
	{
		return ADOConnection::SelectLimit($sql, $nrows, $offset, $inputarr, $secs2cache);
	}

	/**
	 * Initializes the SQL Server version.
	 * Dies if connected to a non-supported version (2000 and older)
	 */
	public function serverVersion() {
		
		$data = $this->serverInfo();
		preg_match('/^\d{2}/', $data['version'], $matches);
		$version = (int)reset($matches);

		// We only support SQL Server 2005 and up
		if($version < 9) {
			die("SQL SERVER VERSION {$data['version']} NOT SUPPORTED IN pdo_sqlsrv DRIVER");
		}

		$this->mssql_version = $version;
	}

	/**
	 * Returns the server information
	 * 
	 * @return array()
	 */
	public function serverInfo() {

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

		$arrServerInfo = $this->_connectionID->getAttribute(constant("PDO::ATTR_SERVER_INFO"));
	
		$ADODB_FETCH_MODE = $savem;
		
		$arr['description'] = $arrServerInfo['SQLServerName'].' connected to '.$arrServerInfo['CurrentDatabase'];
		$arr['version']     = $arrServerInfo['SQLServerVersion'];//ADOConnection::_findvers($arr['description']);
		return $arr;
	}
	
	/**
	 * Proper Sequences Only available to Server 2012 and up
	 */
	public function createSequence($seq='adodbseq',$start=1)
	{
		if (!$this->sequences)
		{
			$sql = "SELECT name FROM sys.sequences";
			$this->sequences = $this->GetCol($sql);
		}
		
		$ok = $this->Execute("CREATE SEQUENCE $seq START WITH $start INCREMENT BY 1");
		if (!$ok)
			die("CANNOT CREATE SEQUENCE");
		
		$this->sequences[] = $seq;
	}

	/**
	 * Only available to Server 2012 and up
	 * Cannot do this the normal adodb way by trapping an error if the
	 * sequence does not exist because sql server will auto create a
	 * sequence with the starting number of -9223372036854775808
	 *
	 * @param string $seq
	 * @param int	 $start
	 * 
	 * @return int
	 */
	public function genID($seq='adodbseq',$start=1)
	{

		/*
		* First time in create an array of sequence names that we
		* can use in later requests to see if the sequence exists
		* the overhead is creating a list of sequences every time
		* we need access to at least 1. If we really care about
		* performance, we could maybe flag a 'nocheck' class variable
		*/
		if (!$this->sequences){
			$sql = "SELECT name FROM sys.sequences";
			$this->sequences = $this->GetCol($sql);
		}
		if (!is_array($this->sequences)
		|| is_array($this->sequences) && !in_array($seq,$this->sequences)){
			$this->createSequence($seq, $start);

		}
		$num = $this->GetOne("SELECT NEXT VALUE FOR $seq");
		return $num;
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
	
	/**
	 * Sets the isolation level of a transaction.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:settransactionmode
	 *
	 * @param string $transaction_mode The transaction mode to set.
	 *
	 * @return void
	 */
	public function setTransactionMode( $transaction_mode )
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
