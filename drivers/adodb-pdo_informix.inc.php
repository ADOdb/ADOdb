<?php
/**
 * PDO IBM INFORMIX driver
 * Minimum Informix version 12.0
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

final class ADODB_pdo_informix extends ADODB_pdo {

	var $concat_operator='||';
	var $sysTime = 'CURRENT';
	var $sysDate = 'TODAY';
	var $sysTimeStamp = 'CURRENT';
	var $fmtTimeStamp = "'Y-m-d H:i:s'";
	var $replaceQuote = "''"; // string to use to replace quotes

 	var $_initdate 			= true;
	public $_bindInputArray = true;
	public $_nestedSQL 		= true;
	
    public $substr = 'SUBSTR';

	public $metaTablesSQL = 
        "SELECT tabname,tabtype 
           FROM systables 
          WHERE tabtype IN ('T','V') 
            AND owner !='informix'"; //Don't get informix tables and pseudo-tables

	public $metaColumnsSQL =
		"SELECT c.colname, c.coltype, c.collength, d.default,c.colno, c.collength
		   FROM syscolumns c, systables t,
          OUTER sysdefaults d
		  WHERE c.tabid=t.tabid AND d.tabid=t.tabid AND d.colno=c.colno
		    AND tabname='%s' 
       ORDER BY c.colno";

	public $metaPrimaryKeySQL =
		"SELECT part1,part2,part3,part4,part5,part6,part7,part8 
           FROM	systables t,sysconstraints s,sysindexes i 
          WHERE t.tabname='%s' AND s.tabid=t.tabid AND s.constrtype='P'
		    AND i.idxname=s.idxname";


	/*
	* Sequence management statements
	*/
	public $_genIDSQL  = "";
	public $_genSeqSQL = "";
	public $_dropSeqSQL = "";

    public $hasTop = 'FIRST';
	public $ansiOuter = true;

	protected $typeCrossRef = array(
		0 => 'CHAR',
		1 => 'SMALLINT',
		2 => 'INTEGER',
		3 => 'FLOAT',
		4 => 'SMALLFLOAT',
		5 => 'DECIMAL',
		6 => 'SERIAL 1',
		7 => 'DATE',
		8 => 'MONEY',
		9 => 'NULL',
		10 => 'DATETIME',
		11 => 'BYTE',
		12 => 'TEXT',
		13 => 'VARCHAR',
		14 => 'INTERVAL',
		15 => 'NCHAR',
		16 => 'NVARCHAR',
		17 => 'INT8',
		18 => 'SERIAL8',
		19 => 'SET',
		20 => 'MULTISET',
		21 => 'LIST',
		22 => 'ROW (unnamed)',
		23 => 'COLLECTION',
		40 => 'LVARCHAR',
		41 => 'BLOB',
		43 => 'LVARCHAR',
		45 => 'BOOLEAN',
		52 => 'BIGINT',
		53 => 'BIGSERIAL',
		2061 => 'IDSSECURITYLABEL',
		4118 => 'ROW'
		);
		
	public function _init($parentDriver){}
		
    /**
     * Returns a database specific IF NULL 
     * 
     * @param string    $field
     * @param string    $ifNull
     * 
     * @return string
     */
    function IfNull( $field, $ifNull )
	{
		return " NVL($field, $ifNull) "; // if Informix 9.X or 10.X
	}

	/**
	 * Return a list of Primary Keys for a specified table
	 *
	 * @param string   $table
	 * @param bool     $primary    (optional) only return primary keys
	 * @param bool     $owner      (optional) not used in this driver
	 *
	 * @return string[]    Array of indexes
	 */
	public function  metaPrimaryKeys($table,$owner=false)
	{
		
		$primaryKeys = array();

		global $ADODB_FETCH_MODE;

		$schema = '';
		$this->_findschema($table,$schema);

		$savem 			  = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		$this->setFetchMode(ADODB_FETCH_NUM);
		
		$sql = "SELECT c.constrname, c.constrtype AS tp , c.idxname AS pk_idx , t2.tabname, c2.idxname
				  FROM sysconstraints c, systables t, 
				 OUTER (sysreferences r, systables t2, sysconstraints c2)
				 WHERE t.tabname = '%s'
				   AND t.tabid = c.tabid
				   AND r.constrid = c.constrid
				   AND t2.tabid = r.ptabid
				   AND c2.constrid = r.constrid
				   AND c.constrtype='P'";

		$rows = $this->getRow(sprintf($sql,$table));
		
		$primaryKey = $rows[2];
		
		$sql = "SELECT UNIQUE t.tabname, i.idxname, i.idxtype, 
		(SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part1 )
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part2 )
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part3 )
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part4 )
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part5 )
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part6 )
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part7 )
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part8 )
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part9 )
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part10)
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part11)
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part12)
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part13)
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part14)
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part15)
		  , (SELECT c.colname FROM syscolumns c WHERE c.tabid = i.tabid AND c.colno = i.part16)
		  FROM sysindexes i , systables t
		  WHERE i.tabid = t.tabid
			AND t.tabname = '%s'
			AND i.idxname = '%s'";
	
		$rows = $this->getAll(sprintf($sql,$table,$primaryKey));
	
		$this->setFetchMode($savem);
		$ADODB_FETCH_MODE = $savem;

		if (empty($rows))
			return false;

		foreach ($rows as $r)
		{
			for ($colIndex=3;$colIndex<=18;$colIndex++)
			{
				if ($r[$colIndex])
				{
					$primaryKeys[] = $r[$colIndex];
					break;
				}
			}
		}
		return $primaryKeys;
	}

	
	

/*
coltype	SMALLINT	Code indicating the data type of the column:
0 = CHAR
1 = SMALLINT
2 = INTEGER
3 = FLOAT
4 = SMALLFLOAT
5 = DECIMAL
6 = SERIAL 1
7 = DATE
8 = MONEY
9 = NULL
10 = DATETIME
11 = BYTE
12 = TEXT
13 = VARCHAR
14 = INTERVAL
15 = NCHAR
16 = NVARCHAR
17 = INT8
18 = SERIAL8 1
19 = SET
20 = MULTISET
21 = LIST
22 = ROW (unnamed)
23 = COLLECTION
40 = LVARCHAR fixed-length opaque types 2
41 = BLOB, BOOLEAN, CLOB variable-length opaque types 2
43 = LVARCHAR (client-side only)
45 = BOOLEAN
52 = BIGINT
53 = BIGSERIAL 1
2061 = IDSSECURITYLABEL 2, 3
4118 = ROW (named)
*/

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
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
		//if ($this->fetchMode !== false) 
			$savem = $this->SetFetchMode(ADODB_FETCH_ASSOC);

		$SQL = sprintf($this->metaColumnsSQL,strtolower($table));
		$rs = $this->Execute($SQL);
		if (isset($savem)) 
			$this->SetFetchMode($savem);
		$ADODB_FETCH_MODE = $save;
		if (!$rs) {
			return $false;
		}
		
		$retarr = array();
		while (!$rs->EOF) { 
			/*
			[colname] => order_num
            [coltype] => 262
            [collength] => 4
            [default] => 
            [colno] => 1
			
			HIDDEN
1 - Hidden column
ROWVER
2 - Row version column
ROW_CHKSUM
4 - Row key column
ER_CHECKVER
8 - ER row version column
UPGRD1_COL
16 - ER auto primary key column
UPGRD2_COL
32 - ER auto primary key column
UPGRD3_COL
64 - ER auto primary key column
PK_NOTNULL
128 - NOT NULL by PRIMARY KEY
			*/
			
			$fld = new ADOFieldObject();
			
			$fld->name = $rs->fields['colname'];
			
			$notNull = 0;
			
			if ($rs->fields['coltype'] > 256 && $rs->fields['coltype'] < 512)
			{
				$notNull = 1;
				$rs->fields['coltype'] = $rs->fields['coltype'] - 256; 
			}
			
			$fld->type = $this->typeCrossRef[$rs->fields['coltype']];
			
			$fld->max_length = $rs->fields['collength'];
			
			$fld->scale = 0; //$rs->fields[3];
			
			
			/*
			if ($rs->fields[1] == 'NUMBER' && $rs->fields[3] == 0) {
				$fld->type ='INT';
				$fld->max_length = $rs->fields['colmax'];
		
			$fld->binary = (strpos($fld->type,'BLOB') !== false);
			
		
				}
			*/
			$fld->not_null 		= $notNull;
			$fld->default_value = $rs->fields['default'];
			
			if ($ADODB_FETCH_MODE == ADODB_FETCH_NUM) 
				$retarr[] = $fld;
			else 
				$retarr[strtoupper($fld->name)] = $fld;
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
	 * Begin a Transaction.
	 *
	 * Must be followed by CommitTrans() or RollbackTrans().
	 *
	 * @return bool true if succeeded or false if database does not support transactions
	 */
    public function beginTrans()
	{
		if ($this->transOff) 
            return true;
		
        $this->transCnt += 1;
		$this->Execute('BEGIN');
		$this->_autocommit = false;
		return true;
	}

    /**
	 * Commits a transaction.
	 *
	 * If database does not support transactions, return true as data is
	 * always committed.
	 *
	 * @param bool $ok True to commit, false to rollback the transaction.
	 *
	 * @return bool true if successful
	 */
	function CommitTrans($ok=true)
	{
		if (!$ok) return $this->RollbackTrans();
		if ($this->transOff) return true;
		if ($this->transCnt) $this->transCnt -= 1;
		$this->Execute('COMMIT');
		$this->_autocommit = true;
		return true;
	}

    
	/**
	 * Rolls back a transaction.
	 *
	 * If database does not support transactions, return false as rollbacks
	 * always fail.
	 *
	 * @return bool true if successful
	 */
	public function rollbackTrans()
	{
		if ($this->transOff) 
            return true;
		if ($this->transCnt) 
            $this->transCnt -= 1;
		
        $this->Execute('ROLLBACK');
		$this->_autocommit = true;
		return true;
	}

     /**
	 * Lock a row.
	 * Will escalate and lock the table if row locking is not supported.
	 * Will normally free the lock at the end of the transaction.
	 *
	 * @param string $table name of table to lock
	 * @param string $where where clause to use, eg: "WHERE row=12". If left empty, will escalate to table lock
	 * @param string $col
	 *
	 * @return bool
	 */
	public function rowLock($tables,$where,$col='1 as adodbignore')
	{
		if ($this->_autocommit) 
            $this->BeginTrans();

        $SQL = sprintf('SELECT %s FROM %s WHERE %s FOR UPDATE',
                       $col,$tables, $where);

		return $this->GetOne($SQL);
	}
	
	/**
	 * Returns the server information
	 * 
	 * @return array()
	 */
	public function serverInfo() 
	{

        static $arr = false;
		if (is_array($arr))
			return $arr;

        $SQL = "SELECT DBINFO('version','full') 
                  FROM systables 
                 WHERE tabid = 1";

	    $arr['description'] = $this->GetOne($SQL);

        $SQL = "SELECT DBINFO('version','major') || DBINFO('version','minor') 
                  FROM systables 
                 WHERE tabid = 1";

	    $arr['version'] = $this->GetOne($SQL);
 
	    return $arr;
	}


	/**
	  * Lists databases. Because instances are independent, we only know about
	  * the current database name
	  *
	  * @return string[]
	  */
	  public function metaDatabases(){

		$dbName = $this->databaseName;

		return (array)$dbName;

	}

    /**
	 * List procedures or functions in an array.
	 * @param procedureNamePattern  a procedure name pattern; must match the procedure name as it is stored in the database
	 * @param catalog a catalog name; must match the catalog name as it is stored in the database;
	 * @param schemaPattern a schema name pattern;
	 *
	 * @return array of procedures on current database.
	 *
	 * Array(
	 *   [name_of_procedure] => Array(
	 *     [type] => PROCEDURE or FUNCTION
	 *     [catalog] => Catalog_name
	 *     [schema] => Schema_name
	 *     [remarks] => explanatory comment on the procedure
	 *   )
	 * )
	 */
    public function metaProcedures($NamePattern = false, $catalog  = null, $schemaPattern  = null)
    {
        // save old fetch mode
        global $ADODB_FETCH_MODE;

        $false = false;
        $save = $ADODB_FETCH_MODE;
        $ADODB_FETCH_MODE = ADODB_FETCH_NUM;
        if ($this->fetchMode !== FALSE) {
               $savem = $this->SetFetchMode(FALSE);

        }
        $procedures = array ();

        // get index details

        $likepattern = '';
        if ($NamePattern) {
           $likepattern = " WHERE procname LIKE '".$NamePattern."'";
        }

        $rs = $this->Execute('SELECT procname, isproc FROM sysprocedures'.$likepattern);

        if (is_object($rs)) {
            // parse index data into array

            while ($row = $rs->FetchRow()) {
                $procedures[$row[0]] = array(
                        'type' => ($row[1] == 'f' ? 'FUNCTION' : 'PROCEDURE'),
                        'catalog' => '',
                        'schema' => '',
                        'remarks' => ''
                    );
            }
	    }

        // restore fetchmode
        if (isset($savem)) {
                $this->SetFetchMode($savem);
        }
        $ADODB_FETCH_MODE = $save;

        return $procedures;
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
	
		$savem 			  = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
		$this->setFetchMode(ADODB_FETCH_ASSOC);
		
		$sql = "
			SELECT tr.tabname,updrule,delrule,
			i.part1 o1,i2.part1 d1,i.part2 o2,i2.part2 d2,i.part3 o3,i2.part3 d3,i.part4 o4,i2.part4 d4,
			i.part5 o5,i2.part5 d5,i.part6 o6,i2.part6 d6,i.part7 o7,i2.part7 d7,i.part8 o8,i2.part8 d8
			FROM systables t,sysconstraints s,sysindexes i,
			sysreferences r,systables tr,sysconstraints s2,sysindexes i2
			WHERE t.tabname='$table'
			AND s.tabid=t.tabid AND s.constrtype='R' AND r.constrid=s.constrid
			AND i.idxname=s.idxname AND tr.tabid=r.ptabid
			AND s2.constrid=r.primary AND i2.idxname=s2.idxname";
			
		$rs = $this->execute($sql);
		if (!$rs || $rs->EOF)  
            return false;
		
        $arr = $rs->getArray();
		$this->setFetchMode($savem);
		$a = array();
		foreach($arr as $v) 
        {

			$coldest=$this->metaColumnNames($v["tabname"]);
			$coldestValues = array_values($coldest);
			$colorig=$this->metaColumnNames($table);
			$colorigValues = array_values($colorig);
			
			$colnames=array();
			
			for($i=1;$i<=8 && $v["o{$i}"] ;$i++) {
				$colnames[]=$coldestValues[$v["d{$i}"]-1]."=".$colorigValues[$v["o{$i}"]-1];
			}
			if($upper)
				$a[strtoupper($v["tabname"])] =  $colnames;
			else
				$a[$v["tabname"]] =  $colnames;
		}
		return $a;
	}

}
