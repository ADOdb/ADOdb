<?php
/**
 * PDO IBM DB2 driver
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
 * @copyright 2022 Damien Regad, Mark Newnham and the ADOdb community
 */

final class ADODB_pdo_ibm extends ADODB_pdo {

	var $concat_operator='||';
	var $sysTime = 'CURRENT TIME';
	var $sysDate = 'CURRENT DATE';
	var $sysTimeStamp = 'CURRENT TIMESTAMP';
	var $fmtTimeStamp = "'Y-m-d H:i:s'";
	var $replaceQuote = "''"; // string to use to replace quotes

 	var $_initdate 			= true;
	public $_bindInputArray = true;
	public $_nestedSQL 		= true;
	
	public $metaColumnsSQL = "SELECT 
       colname, typename,length, scale,default, remarks, 
       case when nulls='Y' then 1 else 0 end as nullable,
       case when identity ='Y' then 1 else 0 end as is_identity,
       case when generated ='' then 0 else 1 end as  is_computed,
       text as computed_formula
		FROM syscat.columns
		WHERE tabname = '%s'
		ORDER BY colno";
	
	

	const TABLECASE_LOWER    =  0;
	const TABLECASE_UPPER    =  1;
	const TABLECASE_DEFAULT  =  2;

	/**
	 * Controls the casing of the table provided to the meta functions
	 */
	private $tableCase = 2;

	public function _init($parentDriver){}
	
	
	/**
	 * Select a limited number of rows.
	 *
	 * @param string     $sql
	 * @param int        $offset     Row to start calculations from (1-based)
	 * @param int        $nrows      Number of rows to get
	 * @param array|bool $inputarr   Array of bind variables
	 * @param int        $secs2cache Private parameter only used by jlim
	 *
	 * @return ADORecordSet The recordset ($rs->databaseType == 'array')
	 */
	public function selectLimit($sql,$nrows=-1,$offset=-1,$inputArr=false,$secs2cache=0)
	{
		$nrows = (integer) $nrows;

		if ($offset <= 0)
		{
			if ($nrows >= 0)
				$sql .=  " FETCH FIRST $nrows ROWS ONLY ";

			$rs = $this->execute($sql,$inputArr);

		}
		else
		{
			if ($offset > 0 && $nrows < 0);

			else
			{
				$nrows += $offset;
				$sql .=  " FETCH FIRST $nrows ROWS ONLY ";
			}

			/*
			 * DB2 has no native support for mid table offset
			 */
			$rs = ADOConnection::selectLimit($sql,$nrows,$offset,$inputArr);

		}

		return $rs;
	}
	
	/**
	 * Returns a list of tables
	 *
	 * @param string	$ttype (optional)
	 * @param	string	$schema	(optional)
	 * @param	string	$mask	(optional)
	 *
	 * @return array
	 */
	public function metaTables($ttype=false,$schema=false,$mask=false)
	{

		global $ADODB_FETCH_MODE;

		$savem 			  = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

		/*
		* Values for TABLE_TYPE
		* ---------------------------
		* ALIAS, HIERARCHY TABLE, INOPERATIVE VIEW, NICKNAME,
		* MATERIALIZED QUERY TABLE, SYSTEM TABLE, TABLE,
		* TYPED TABLE, TYPED VIEW, and VIEW
		*
		* If $ttype passed as '', match 'TABLE' and 'VIEW'
		* If $ttype passed as 'T' it is assumed to be 'TABLE'
		* if $ttype passed as 'V' it is assumed to be 'VIEW'
		*/
		
		$sqlArgs = array();
		
		$ttype = trim(strtoupper(substr($ttype,0,1)));
		
		$ttypeSql = '';
		if ($ttype) 
		{
			$sqlArgs[] = "type='$ttype'";
		}

		if (!$schema)
		{
			$sqlArgs[] = "name NOT LIKE 'SYS%'";
		}
		else
		{
			$sqlArgs[] = "name LIKE '$schema'";
		}
		
		if ($mask)
		{
			$sqlArgs = "tbspace LIKE '$mask'";
		}
		
		$sqlOptions = '';
		
		if (count($sqlArgs) > 0)
			$sqlOptions = 'WHERE ' . implode(' AND ' ,$sqlArgs);
		

		$SQL = "SELECT * 
				  FROM sysibm.systables 
				  $sqlOptions";
		
		$rs = $this->execute($SQL);

		$ADODB_FETCH_MODE = $savem;

		if (!$rs)
			return false;

		$arr = $rs->getArray();
		
		$rs->Close();

		$tableList = array();

		/*
		* Array items
		* ---------------------------------
		* 0 TABLE_CAT	The catalog that contains the table.
		*				The value is NULL if this table does not have catalogs.
		* 1 TABLE_SCHEM	Name of the schema that contains the table.
		* 2 TABLE_NAME	Name of the table.
		* 3 TABLE_TYPE	Table type identifier for the table.
		* 4 REMARKS		Description of the table.
		*/

		for ($i=0; $i < sizeof($arr); $i++)
		{

			$tableRow = $arr[$i];
			$tableName = $tableRow['NAME'];
			$tableType = $tableRow['TYPE'];

			if (!$tableName)
				continue;

			if ($ttype == '' && (strcmp($tableType,'T') <> 0 && strcmp($tableType,'V') <> 0))
				continue;

			/*
			 * Set metacasing if required
			 */
			$tableName = $this->getMetaCasedValue($tableName);

			/*
			 * If we requested a schema, we prepend the schema
			   name to the table name
			 */
			if (strcmp($schema,'%') <> 0)
				$tableName = $schema . '.' . $tableName;

			$tableList[] = $tableName;

		}
		return $tableList;
	}
	
	/**
	 * Return a list of Primary Keys for a specified table
	 *
	 * We don't use db2_statistics as the function does not seem to play
	 * well with mixed case table names
	 *
	 * @param string   $table
	 * @param bool     $primary    (optional) only return primary keys
	 * @param bool     $owner      (optional) not used in this driver
	 *
	 * @return string[]    Array of indexes
	 */
	public function metaPrimaryKeys($table,$owner=false)
	{

		$primaryKeys = array();

		global $ADODB_FETCH_MODE;

		$schema = '';
		$this->_findschema($table,$schema);

		$table = $this->getTableCasedValue($table);

		$savem 			  = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		$this->setFetchMode(ADODB_FETCH_NUM);


		$sql = "SELECT *
				  FROM syscat.indexes
				 WHERE tabname='$table'";

		$rows = $this->getAll($sql);

		$this->setFetchMode($savem);
		$ADODB_FETCH_MODE = $savem;

		if (empty($rows))
			return false;

		foreach ($rows as $r)
		{
			if ($r[7] != 'P')
				continue;

			$cols = explode('+',$r[6]);
			foreach ($cols as $colIndex=>$col)
			{
				if ($colIndex == 0)
					continue;
				$columnName = $this->getMetaCasedValue($col);
				$primaryKeys[] = $columnName;
			}
			break;
		}
		return $primaryKeys;
	}

	/**
	 * List procedures or functions in an array.
	 *
	 * We interrogate syscat.routines instead of calling the PHP
	 * function procedures because ADOdb requires the type of procedure
	 * this is not available in the php function
	 *
	 * @param	string $procedureNamePattern (optional)
	 * @param	string $catalog				 (optional)
	 * @param	string $schemaPattern		 (optional)

	 * @return array of procedures on current database.
	 *
	 */
	public function metaProcedures($procedureNamePattern = null, $catalog  = null, $schemaPattern  = null) {


		global $ADODB_FETCH_MODE;

		$metaProcedures = array();
		$procedureSQL   = '';
		$catalogSQL     = '';
		$schemaSQL      = '';

		$savem 			  = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

		if ($procedureNamePattern)
			$procedureSQL = "AND ROUTINENAME LIKE " . strtoupper($this->qstr($procedureNamePattern));

		if ($catalog)
			$catalogSQL = "AND OWNER=" . strtoupper($this->qstr($catalog));

		if ($schemaPattern)
			$schemaSQL = "AND ROUTINESCHEMA LIKE {$this->qstr($schemaPattern)}";


		$fields = "
		ROUTINENAME,
		CASE ROUTINETYPE
			 WHEN 'P' THEN 'PROCEDURE'
			 WHEN 'F' THEN 'FUNCTION'
			 ELSE 'METHOD'
			 END AS ROUTINETYPE_NAME,
		ROUTINESCHEMA,
		REMARKS";

		$SQL = "SELECT $fields
				  FROM syscat.routines
				 WHERE OWNER IS NOT NULL
				  $procedureSQL
				  $catalogSQL
				  $schemaSQL
				ORDER BY ROUTINENAME
				";

		$result = $this->execute($SQL);

		$ADODB_FETCH_MODE = $savem;

		if (!$result)
			return false;

		while ($r = $result->fetchRow()){
			$procedureName = $this->getMetaCasedValue($r[0]);
			$schemaName    = $this->getMetaCasedValue($r[2]);
			$metaProcedures[$procedureName] = array('type'=> $r[1],
												   'catalog' => '',
												   'schema'  => $schemaName,
												   'remarks' => $r[3]
													);
		}

		return $metaProcedures;

	}
	
	/**
	 * Returns a list of Foreign Keys associated with a specific table.
	 *
	 * @param string $table
	 * @param string $owner       discarded
	 * @param bool   $upper       discarded
	 * @param bool   $associative discarded
	 *
	 * @return string[]|false An array where keys are tables, and values are foreign keys;
	 *                        false if no foreign keys could be found.
	 */
	public function metaForeignKeys($table, $owner = '', $upper = false, $associative = false)
	{

		global $ADODB_FETCH_MODE;

		$schema = '';
		$this->_findschema($table,$schema);

		$savem = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

		$this->setFetchMode(ADODB_FETCH_NUM);

		$sql = "SELECT SUBSTR(tabname,1,20) table_name,
					   SUBSTR(constname,1,20) fk_name,
					   SUBSTR(REFTABNAME,1,12) parent_table,
					   SUBSTR(refkeyname,1,20) pk_orig_table,
					   fk_colnames
				 FROM syscat.references
				WHERE tabname = '$table'";

		$results = $this->getAll($sql);

		$ADODB_FETCH_MODE = $savem;
		$this->setFetchMode($savem);

		if (empty($results))
			return false;

		$foreignKeys = array();

		foreach ($results as $r)
		{
			$parentTable = trim($this->getMetaCasedValue($r[2]));
			$keyName     = trim($this->getMetaCasedValue($r[1]));
			$foreignKeys[$parentTable] = $keyName;
		}

		return $foreignKeys;
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

		$sql = "SELECT service_level, fixpack_num
				  FROM TABLE(sysproc.env_get_inst_info())
					AS INSTANCEINFO";
		$row = $this->GetRow($sql);

		$ADODB_FETCH_MODE = $savem;
		$info = array();
		
		if ($row) {
			$info['version'] = $row['SERVICE_LEVEL'].':'.$row['FIXPACK_NUM'];
			$info['fixpack'] = $row['FIXPACK_NUM'];
			$info['description'] = $row['SERVICE_LEVEL'];
			return $info;
		} else 
		

		$arr = array();
		$arr['version'] 	=  '';
		$arr['description'] = $this->_connectionID->getAttribute(constant("PDO::ATTR_SERVER_INFO"));

		
		return $arr;
	}
	
	/**
	 * Gets a meta cased parameter
	 *
	 * Receives an input variable to be processed per the metaCasing
	 * rule, and returns the same value, processed
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	private function getMetaCasedValue($value)
	{
		global $ADODB_ASSOC_CASE;

		switch($ADODB_ASSOC_CASE)
		{
		case ADODB_ASSOC_CASE_LOWER:
			$value = strtolower($value);
			break;
		case ADODB_ASSOC_CASE_UPPER:
			$value = strtoupper($value);
			break;
		}
		return $value;
	}


	/**
	 * Sets the table case parameter
	 *
	 * @param int $caseOption
	 * @return null
	 */
	final public function setTableCasing($caseOption)
	{
		$this->tableCase = $caseOption;
	}

	/**
	 * Gets the table casing parameter
	 *
	 * @return int $caseOption
	 */
	final public function getTableCasing()
	{
		return $this->tableCase;
	}

	/**
	 * Gets a table cased parameter
	 *
	 * Receives an input variable to be processed per the tableCasing
	 * rule, and returns the same value, processed
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	final public function getTableCasedValue($value)
	{
		switch($this->tableCase)
		{
		case self::TABLECASE_LOWER:
			$value = strtolower($value);
			break;
		case self::TABLECASE_UPPER:
			$value = strtoupper($value);
			break;
		}
		return $value;
	}

	/**
	  * Lists databases. Because instances are independent, we only know about
	  * the current database name
	  *
	  * @return string[]
	  */
	  public function metaDatabases(){

		$dbName = $this->getMetaCasedValue($this->databaseName);

		return (array)$dbName;

	}

}
