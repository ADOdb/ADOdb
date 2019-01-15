<?php
namespace ADOdb\drivers\sqlite;

use ADOdb;
	
class adoConnection extends ADOdb\adoConnection
{
	var $databaseType = "sqlite3";
	var $replaceQuote = "''"; // string to use to replace quotes
	var $concat_operator='||';
	var $_errorNo = 0;
	var $hasLimit = true;
	var $hasInsertID = true; 		/// supports autoincrement ID?
	var $hasAffectedRows = true; 	/// supports affected rows for update/delete?
	var $metaTablesSQL = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name";
	var $sysDate = "adodb_date('Y-m-d')";
	var $sysTimeStamp = "adodb_date('Y-m-d H:i:s')";
	var $fmtTimeStamp = "'Y-m-d H:i:s'";

 
	public function __construct($driver='')
	{
		parent::__construct();
	}
	
	/**
	 * Connect to database
	 *
	 * @param [argHostname]		Host to connect to
	 * @param [argUsername]		Userid to login
	 * @param [argPassword]		Associated password
	 * @param [argDatabaseName]	database
	 * @param [forceNew]		force new connection
	 *
	 * @return true or false
	 */
	function Connect($argHostname = "", $argUsername = "", $argPassword = "", $argDatabaseName = "", $forceNew = false) 
	{
		if ($argHostname != "") {
			$this->host = $argHostname;
		}
		// Overwrites $this->host and $this->port if a port is specified.
		$this->parseHostNameAndPort();

		if ($argUsername != "") {
			$this->user = $argUsername;
		}
		if ($argPassword != "") {
			$this->password = 'not stored'; // not stored for security reasons
		}
		
		if ($argDatabaseName != "") {
			$this->database = $argDatabaseName;
		}

		$this->_isPersistentConnection = false;


		if ($forceNew) {
			if ($rez=$this->_nconnect($this->host, $this->user, $argPassword, $this->database)) {
				return $this;
			}
		} else {
			if ($rez=$this->_connect($this->host, $this->user, $argPassword, $this->database)) {
				return $this;
			}
		}
		if (isset($rez)) {
			$err = $this->ErrorMsg();
			$errno = $this->ErrorNo();
			if (empty($err)) {
				$err = "Connection error to server '$argHostname' with user '$argUsername'";
			}
		} else {
			$err = "Missing extension for ".$this->dataProvider;
			$errno = 0;
		}
		if ($fn = $this->raiseErrorFn) {
			$fn($this->databaseType, 'CONNECT', $errno, $err, $this->host, $this->database, $this);
		}

		$this->_connectionID = false;
		if ($this->debug) {
			ADOConnection::outp( $this->host.': '.$err);
		}
		return false;
	}

	
	function ServerInfo()
	{
		$version = SQLite3::version();
		$arr['version'] = $version['versionString'];
		$arr['description'] = 'SQLite 3';
		return $arr;
	}

	function BeginTrans()
	{
		if ($this->transOff) {
			return true;
		}
		$ret = $this->Execute("BEGIN TRANSACTION");
		$this->transCnt += 1;
		return true;
	}

	function CommitTrans($ok=true)
	{
		if ($this->transOff) {
			return true;
		}
		if (!$ok) {
			return $this->RollbackTrans();
		}
		$ret = $this->Execute("COMMIT");
		if ($this->transCnt > 0) {
			$this->transCnt -= 1;
		}
		return !empty($ret);
	}

	function RollbackTrans()
	{
		if ($this->transOff) {
			return true;
		}
		$ret = $this->Execute("ROLLBACK");
		if ($this->transCnt > 0) {
			$this->transCnt -= 1;
		}
		return !empty($ret);
	}

	function metaType($t,$len=-1,$fieldobj=false)
	{

		if (is_object($t))
		{
			$fieldobj = $t;
			$t = $fieldobj->type;
			$len = $fieldobj->max_length;
		}

		$t = strtoupper($t);

		/*
		* We are using the Sqlite affinity method here
		* @link https://www.sqlite.org/datatype3.html
		*/
		$affinity = array(
		'INT'=>'INTEGER',
		'INTEGER'=>'INTEGER',
		'TINYINT'=>'INTEGER',
		'SMALLINT'=>'INTEGER',
		'MEDIUMINT'=>'INTEGER',
		'BIGINT'=>'INTEGER',
		'UNSIGNED BIG INT'=>'INTEGER',
		'INT2'=>'INTEGER',
		'INT8'=>'INTEGER',

		'CHARACTER'=>'TEXT',
		'VARCHAR'=>'TEXT',
		'VARYING CHARACTER'=>'TEXT',
		'NCHAR'=>'TEXT',
		'NATIVE CHARACTER'=>'TEXT',
		'NVARCHAR'=>'TEXT',
		'TEXT'=>'TEXT',
		'CLOB'=>'TEXT',

		'BLOB'=>'BLOB',

		'REAL'=>'REAL',
		'DOUBLE'=>'REAL',
		'DOUBLE PRECISION'=>'REAL',
		'FLOAT'=>'REAL',

		'NUMERIC'=>'NUMERIC',
		'DECIMAL'=>'NUMERIC',
		'BOOLEAN'=>'NUMERIC',
		'DATE'=>'NUMERIC',
		'DATETIME'=>'NUMERIC'
		);

		if (!isset($affinity[$t]))
			return ADODB_DEFAULT_METATYPE;

		$subt = $affinity[$t];
		/*
		* Now that we have subclassed the provided data down
		* the sqlite 'affinity', we convert to ADOdb metatype
		*/

		$subclass = array('INTEGER'=>'I',
						  'TEXT'=>'X',
						  'BLOB'=>'B',
						  'REAL'=>'N',
						  'NUMERIC'=>'N');

		return $subclass[$subt];
	}
	// mark newnham
	function MetaColumns($table, $normalize=true)
	{
		global $ADODB_FETCH_MODE;
		$false = false;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
		if ($this->fetchMode !== false) {
			$savem = $this->SetFetchMode(false);
		}
		$rs = $this->Execute("PRAGMA table_info('$table')");
		if (isset($savem)) {
			$this->SetFetchMode($savem);
		}
		if (!$rs) {
			$ADODB_FETCH_MODE = $save;
			return $false;
		}
		$arr = array();
		while ($r = $rs->FetchRow()) {
			$type = explode('(',$r['type']);
			$size = '';
			if (sizeof($type)==2) {
				$size = trim($type[1],')');
			}
			$fn = strtoupper($r['name']);
			$fld = new ADOFieldObject;
			$fld->name = $r['name'];
			$fld->type = $type[0];
			$fld->max_length = $size;
			$fld->not_null = $r['notnull'];
			$fld->default_value = $r['dflt_value'];
			$fld->scale = 0;
			if (isset($r['pk']) && $r['pk']) {
				$fld->primary_key=1;
			}
			if ($save == ADODB_FETCH_NUM) {
				$arr[] = $fld;
			} else {
				$arr[strtoupper($fld->name)] = $fld;
			}
		}
		$rs->Close();
		$ADODB_FETCH_MODE = $save;
		return $arr;
	}

	function metaForeignKeys( $table, $owner = FALSE, $upper = FALSE, $associative = FALSE )
	{
	    global $ADODB_FETCH_MODE;
		if ($ADODB_FETCH_MODE == ADODB_FETCH_ASSOC
		|| $this->fetchMode == ADODB_FETCH_ASSOC)
		$associative = true;

	    /*
		* Read sqlite master to find foreign keys
		*/
		$sql = "SELECT sql
				 FROM (
				SELECT sql sql, type type, tbl_name tbl_name, name name
				  FROM sqlite_master
			          )
				WHERE type != 'meta'
				  AND sql NOTNULL
		          AND LOWER(name) ='" . strtolower($table) . "'";

		$tableSql = $this->getOne($sql);

		$fkeyList = array();
		$ylist = preg_split("/,+/",$tableSql);
		foreach ($ylist as $y)
		{
			if (!preg_match('/FOREIGN/',$y))
				continue;

			$matches = false;
			preg_match_all('/\((.+?)\)/i',$y,$matches);
			$tmatches = false;
			preg_match_all('/REFERENCES (.+?)\(/i',$y,$tmatches);

			if ($associative)
			{
				if (!isset($fkeyList[$tmatches[1][0]]))
					$fkeyList[$tmatches[1][0]]	= array();
				$fkeyList[$tmatches[1][0]][$matches[1][0]] = $matches[1][1];
			}
			else
				$fkeyList[$tmatches[1][0]][] = $matches[1][0] . '=' . $matches[1][1];
		}

		if ($associative)
		{
			if ($upper)
				$fkeyList = array_change_key_case($fkeyList,CASE_UPPER);
			else
				$fkeyList = array_change_key_case($fkeyList,CASE_LOWER);
		}
		return $fkeyList;
	}


	function _init($parentDriver)
	{
		$parentDriver->hasTransactions = false;
		$parentDriver->hasInsertID = true;
	}

	function _insertid()
	{
		return $this->_connectionID->lastInsertRowID();
	}

	function _affectedrows()
	{
		return $this->_connectionID->changes();
	}

	function ErrorMsg()
 	{
		if ($this->_logsql) {
			return $this->_errorMsg;
		}
		return ($this->_errorNo) ? $this->ErrorNo() : ''; //**tochange?
	}

	function ErrorNo()
	{
		return $this->_connectionID->lastErrorCode(); //**tochange??
	}

	function SQLDate($fmt, $col=false)
	{
		/*
		* In order to map the values correctly, we must ensure the proper
		* casing for certain fields
		* Y must be UC, because y is a 2 digit year
		* d must be LC, because D is 3 char day
		* A must be UC  because a is non-portable am
		* Q must be UC  because q means nothing
		*/
		$fromChars = array('y','D','a','q');
		$toChars   = array('Y','d','A','Q');
		$fmt       = str_replace($fromChars,$toChars,$fmt);

		$fmt = $this->qstr($fmt);
		return ($col) ? "adodb_date2($fmt,$col)" : "adodb_date($fmt)";
	}

	function _createFunctions()
	{
		$this->_connectionID->createFunction('adodb_date', 'adodb_date', 1);
		$this->_connectionID->createFunction('adodb_date2', 'adodb_date2', 2);
	}


	// returns true or false
	function _connect($argHostname, $argUsername, $argPassword, $argDatabasename)
	{
		if (empty($argHostname) && $argDatabasename) {
			$argHostname = $argDatabasename;
		}
		
		$this->_connectionID = new \SQLite3($argHostname);
		
		$this->_createFunctions();

		return true;
	}

	// returns true or false
	function _pconnect($argHostname, $argUsername, $argPassword, $argDatabasename)
	{
		// There's no permanent connect in SQLite3
		return $this->_connect($argHostname, $argUsername, $argPassword, $argDatabasename);
	}

	// returns query ID if successful, otherwise false
	function _query($sql,$inputarr=false)
	{
		$rez = @$this->_connectionID->query($sql);
		if ($rez === false) 
		{
			$this->_errorNo = $this->_connectionID->lastErrorCode();
						
		}
		// If no data was returned, we don't need to create a real recordset
		elseif ($rez->numColumns() == 0) {
			$rez->finalize();
			$rez = true;
		}

		return $rez;
	}

	function SelectLimit($sql,$nrows=-1,$offset=-1,$inputarr=false,$secs2cache=0)
	{
		$nrows = (int) $nrows;
		$offset = (int) $offset;
		$offsetStr = ($offset >= 0) ? " OFFSET $offset" : '';
		$limitStr  = ($nrows >= 0)  ? " LIMIT $nrows" : ($offset >= 0 ? ' LIMIT 999999999' : '');
		if ($secs2cache) {
			$rs = $this->CacheExecute($secs2cache,$sql."$limitStr$offsetStr",$inputarr);
		} else {
			$rs = $this->Execute($sql."$limitStr$offsetStr",$inputarr);
		}

		return $rs;
	}

	/*
		This algorithm is not very efficient, but works even if table locking
		is not available.

		Will return false if unable to generate an ID after $MAXLOOPS attempts.
	*/
	var $_genSeqSQL = "create table %s (id integer)";

	function GenID($seq='adodbseq',$start=1)
	{
		// if you have to modify the parameter below, your database is overloaded,
		// or you need to implement generation of id's yourself!
		$MAXLOOPS = 100;
		//$this->debug=1;
		while (--$MAXLOOPS>=0) {
			@($num = $this->GetOne("select id from $seq"));
			if ($num === false) {
				$this->Execute(sprintf($this->_genSeqSQL ,$seq));
				$start -= 1;
				$num = '0';
				$ok = $this->Execute("insert into $seq values($start)");
				if (!$ok) {
					return false;
				}
			}
			$this->Execute("update $seq set id=id+1 where id=$num");

			if ($this->affected_rows() > 0) {
				$num += 1;
				$this->genID = $num;
				return $num;
			}
		}
		if ($fn = $this->raiseErrorFn) {
			$fn($this->databaseType,'GENID',-32000,"Unable to generate unique id after $MAXLOOPS attempts",$seq,$num);
		}
		return false;
	}

	function CreateSequence($seqname='adodbseq',$start=1)
	{
		if (empty($this->_genSeqSQL)) {
			return false;
		}
		$ok = $this->Execute(sprintf($this->_genSeqSQL,$seqname));
		if (!$ok) {
			return false;
		}
		$start -= 1;
		return $this->Execute("insert into $seqname values($start)");
	}

	var $_dropSeqSQL = 'drop table %s';
	function DropSequence($seqname = 'adodbseq')
	{
		if (empty($this->_dropSeqSQL)) {
			return false;
		}
		return $this->Execute(sprintf($this->_dropSeqSQL,$seqname));
	}

	// returns true or false
	function _close()
	{
		return $this->_connectionID->close();
	}

	function MetaIndexes($table, $primary = FALSE, $owner = false)
	{
		$false = false;
		// save old fetch mode
		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		if ($this->fetchMode !== FALSE) {
			$savem = $this->SetFetchMode(FALSE);
		}
		$SQL=sprintf("SELECT name,sql FROM sqlite_master WHERE type='index' AND LOWER(tbl_name)='%s'", strtolower($table));
		$rs = $this->Execute($SQL);
		if (!is_object($rs)) {
			if (isset($savem)) {
				$this->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			return $false;
		}

		$indexes = array ();
		while ($row = $rs->FetchRow()) {
			if ($primary && preg_match("/primary/i",$row[1]) == 0) {
				continue;
			}
			if (!isset($indexes[$row[0]])) {
				$indexes[$row[0]] = array(
					'unique' => preg_match("/unique/i",$row[1]),
					'columns' => array()
				);
			}
			/**
			 * There must be a more elegant way of doing this,
			 * the index elements appear in the SQL statement
			 * in cols[1] between parentheses
			 * e.g CREATE UNIQUE INDEX ware_0 ON warehouse (org,warehouse)
			 */
			$cols = explode("(",$row[1]);
			$cols = explode(")",$cols[1]);
			array_pop($cols);
			$indexes[$row[0]]['columns'] = $cols;
		}
		if (isset($savem)) {
			$this->SetFetchMode($savem);
			$ADODB_FETCH_MODE = $save;
		}
		return $indexes;
	}

	/**
	* Returns the maximum size of a MetaType C field. Because of the
	* database design, sqlite places no limits on the size of data inserted
	*
	* @return int
	*/
	function charMax()
	{
		return ADODB_STRINGMAX_NOLIMIT;
	}

	/**
	* Returns the maximum size of a MetaType X field. Because of the
	* database design, sqlite places no limits on the size of data inserted
	*
	* @return int
	*/
	function textMax()
	{
		return ADODB_STRINGMAX_NOLIMIT;
	}

	/**
	 * Converts a date to a month only field and pads it to 2 characters
	 *
	 * This uses the more efficient strftime native function to process
	 *
	 * @param 	str		$fld	The name of the field to process
	 *
	 * @return	str				The SQL Statement
	 */
	function month($fld)
	{
		$x = "strftime('%m',$fld)";
		return $x;
	}

	/**
	 * Converts a date to a day only field and pads it to 2 characters
	 *
	 * This uses the more efficient strftime native function to process
	 *
	 * @param 	str		$fld	The name of the field to process
	 *
	 * @return	str				The SQL Statement
	 */
	function day($fld) {
		$x = "strftime('%d',$fld)";
		return $x;
	}

	/**
	 * Converts a date to a year only field
	 *
	 * This uses the more efficient strftime native function to process
	 *
	 * @param 	str		$fld	The name of the field to process
	 *
	 * @return	str				The SQL Statement
	 */
	function year($fld)
	{
		$x = "strftime('%Y',$fld)";
		return $x;
	}

}



/*
	function ErrorMsg()
 	{
		if ($this->_logsql) {
			return $this->_errorMsg;
		}
		return ($this->_errorNo) ? sqlite_error_string($this->_errorNo) : '';
	}
*/
	
