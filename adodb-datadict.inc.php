<?php
/**
 * ADOdb Data Dictionary base class.
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
 */

// security - hide paths
if (!defined('ADODB_DIR')) die();

/**
 * Test script for parser
 */
function lens_ParseTest()
{
$str = "`zcol ACOL` NUMBER(32,2) DEFAULT 'The \"cow\" (and Jim''s dog) jumps over the moon' PRIMARY, INTI INT AUTO DEFAULT 0, zcol2\"afs ds";
print "<p>$str</p>";
$a= lens_ParseArgs($str);
print "<pre>";
print_r($a);
print "</pre>";
}


if (!function_exists('ctype_alnum')) {
	function ctype_alnum($text) {
		return preg_match('/^[a-z0-9]*$/i', $text);
	}
}

//Lens_ParseTest();

/**
	Parse arguments, treat "text" (text) and 'text' as quotation marks.
	To escape, use "" or '' or ))

	Will read in "abc def" sans quotes, as: abc def
	Same with 'abc def'.
	However if `abc def`, then will read in as `abc def`

	@param endstmtchar    Character that indicates end of statement
	@param tokenchars     Include the following characters in tokens apart from A-Z and 0-9
	@returns 2 dimensional array containing parsed tokens.
*/
function lens_ParseArgs($args,$endstmtchar=',',$tokenchars='_.-')
{
	$pos = 0;
	$intoken = false;
	$stmtno = 0;
	$endquote = false;
	$tokens = array();
	$tokens[$stmtno] = array();
	$max = strlen($args);
	$quoted = false;
	$tokarr = array();

	while ($pos < $max) {
		$ch = substr($args,$pos,1);
		switch($ch) {
		case ' ':
		case "\t":
		case "\n":
		case "\r":
			if (!$quoted) {
				if ($intoken) {
					$intoken = false;
					$tokens[$stmtno][] = implode('',$tokarr);
				}
				break;
			}

			$tokarr[] = $ch;
			break;

		case '`':
			if ($intoken) $tokarr[] = $ch;
		case '(':
		case ')':
		case '"':
		case "'":

			if ($intoken) {
				if (empty($endquote)) {
					$tokens[$stmtno][] = implode('',$tokarr);
					if ($ch == '(') $endquote = ')';
					else $endquote = $ch;
					$quoted = true;
					$intoken = true;
					$tokarr = array();
				} else if ($endquote == $ch) {
					$ch2 = substr($args,$pos+1,1);
					if ($ch2 == $endquote) {
						$pos += 1;
						$tokarr[] = $ch2;
					} else {
						$quoted = false;
						$intoken = false;
						$tokens[$stmtno][] = implode('',$tokarr);
						$endquote = '';
					}
				} else
					$tokarr[] = $ch;

			}else {

				if ($ch == '(') $endquote = ')';
				else $endquote = $ch;
				$quoted = true;
				$intoken = true;
				$tokarr = array();
				if ($ch == '`') $tokarr[] = '`';
			}
			break;

		default:

			if (!$intoken) {
				if ($ch == $endstmtchar) {
					$stmtno += 1;
					$tokens[$stmtno] = array();
					break;
				}

				$intoken = true;
				$quoted = false;
				$endquote = false;
				$tokarr = array();

			}

			if ($quoted) $tokarr[] = $ch;
			else if (ctype_alnum($ch) || strpos($tokenchars,$ch) !== false) $tokarr[] = $ch;
			else {
				if ($ch == $endstmtchar) {
					$tokens[$stmtno][] = implode('',$tokarr);
					$stmtno += 1;
					$tokens[$stmtno] = array();
					$intoken = false;
					$tokarr = array();
					break;
				}
				$tokens[$stmtno][] = implode('',$tokarr);
				$tokens[$stmtno][] = $ch;
				$intoken = false;
			}
		}
		$pos += 1;
	}
	if ($intoken) $tokens[$stmtno][] = implode('',$tokarr);

	return $tokens;
}


class ADODB_DataDict {
	/** @var ADOConnection */
	var $connection;
	var $debug = false;
	var $dropTable = 'DROP TABLE %s';
	var $renameTable = 'RENAME TABLE %s TO %s';
	var $dropIndex = 'DROP INDEX %s';
	var $addCol = ' ADD';
	var $alterCol = ' ALTER COLUMN';
	var $dropCol = ' DROP COLUMN';
	var $renameColumn = 'ALTER TABLE %s RENAME COLUMN %s TO %s';	// table, old-column, new-column, column-definitions (not used by default)
	var $nameRegex = '\w';
	var $nameRegexBrackets = 'a-zA-Z0-9_\(\)';
	var $schema = false;
	var $serverInfo = array();
	var $autoIncrement = false;
	var $dataProvider;
	var $invalidResizeTypes4 = array('CLOB','BLOB','TEXT','DATE','TIME'); // for changeTableSQL
	var $blobSize = 100; 	/// any varchar/char field this size or greater is treated as a blob
							/// in other words, we use a text area for editing.
	/** @var string Uppercase driver name */
	var $upperName;

	/*
	* Indicates whether a BLOB/CLOB field will allow a NOT NULL setting
	* The type is whatever is matched to an X or X2 or B type. We must
	* explicitly set the value in the driver to switch the behaviour on
	*/
	public $blobAllowsNotNull;
	/*
	* Indicates whether a BLOB/CLOB field will allow a DEFAULT set
	* The type is whatever is matched to an X or X2 or B type. We must
	* explicitly set the value in the driver to switch the behaviour on
	*/
	public $blobAllowsDefaultValue;


	/**
	 * @var string String to use to quote identifiers and names
	 */
	public $quote;

	/*
	* Constants that represent the current activity
	* passed from a parent process to the lineProcessor
	* methods
	* 
	* @example dropColumnSql passes EWPROCESS_TABLE_DELETE
	*/
	const REPROCESS_TABLE_CREATE = 1;
	const REPROCESS_TABLE_CHANGE = 2;
	const REPROCESS_TABLE_DELETE = 3;



	function getCommentSQL($table,$col)
	{
		return false;
	}

	function setCommentSQL($table,$col,$cmt)
	{
		return false;
	}

	/**
	 * Returns an array of table names and/or views in the database.
	 *
	 * @param string|bool $ttype      `TABLE`, `VIEW`, or false for both.
	 * @param string|bool $showSchema Prepends the schema/user to the table name.
	 * @param string|bool $mask       Input mask
	 *
	 * @return array|false
	 * @see ADOConnection::metaTables()
	 *
	 */
	public function metaTables($ttype=false, $showSchema=false, $mask=false)
	{
		if (!$this->connection->isConnected()) {
            return false;
        }
		return $this->connection->metaTables($ttype, $showSchema, $mask);
	}

	function metaColumns($tab, $upper=true, $schema=false)
	{
		if (!$this->connection->isConnected()) return array();
		return $this->connection->metaColumns($this->tableName($tab), $upper, $schema);
	}

	function metaPrimaryKeys($tab,$owner=false,$intkey=false)
	{
		if (!$this->connection->isConnected()) return array();
		return $this->connection->metaPrimaryKeys($this->tableName($tab), $owner, $intkey);
	}

	function metaIndexes($table, $primary = false, $owner = false)
	{
		if (!$this->connection->isConnected()) return array();
		return $this->connection->metaIndexes($this->tableName($table), $primary, $owner);
	}

	/**
	 * Returns the meta type for a given type and length.
	 *
	 * @param mixed  $t        The object to test.
	 * @param int    $len      The length of the field, if applicable.
	 * @param object $fieldobj The field object, if available.
	 *
	 * @return string
	 */
	function metaType($t, $len=-1, $fieldobj=false)
	{
		static $typeMap = array(
		'VARCHAR' => 'C',
		'VARCHAR2' => 'C',
		'CHAR' => 'C',
		'C' => 'C',
		'STRING' => 'C',
		'NCHAR' => 'C',
		'NVARCHAR' => 'C',
		'VARYING' => 'C',
		'BPCHAR' => 'C',
		'CHARACTER' => 'C',
		'INTERVAL' => 'C',  # Postgres
		'MACADDR' => 'C', # postgres
		'VAR_STRING' => 'C', # mysql
		##
		'LONGCHAR' => 'X',
		'TEXT' => 'X',
		'NTEXT' => 'X',
		'M' => 'X',
		'X' => 'X',
		'CLOB' => 'X',
		'NCLOB' => 'X',
		'LVARCHAR' => 'X',
		##
		'BLOB' => 'B',
		'IMAGE' => 'B',
		'BINARY' => 'B',
		'VARBINARY' => 'B',
		'LONGBINARY' => 'B',
		'B' => 'B',
		##
		'YEAR' => 'D', // mysql
		'DATE' => 'D',
		'D' => 'D',
		##
		'UNIQUEIDENTIFIER' => 'C', # MS SQL Server
		##
		'TIME' => 'T',
		'TIMESTAMP' => 'T',
		'DATETIME' => 'T',
		'TIMESTAMPTZ' => 'T',
		'SMALLDATETIME' => 'T',
		'T' => 'T',
		'TIMESTAMP WITHOUT TIME ZONE' => 'T', // postgresql
		##
		'BOOL' => 'L',
		'BOOLEAN' => 'L',
		'BIT' => 'L',
		'L' => 'L',
		##
		'COUNTER' => 'R',
		'R' => 'R',
		'SERIAL' => 'R', // ifx
		'INT IDENTITY' => 'R',
		##
		'INT' => 'I',
		'INT2' => 'I',
		'INT4' => 'I',
		'INT8' => 'I',
		'INTEGER' => 'I',
		'INTEGER UNSIGNED' => 'I',
		'SHORT' => 'I',
		'TINYINT' => 'I',
		'SMALLINT' => 'I',
		'I' => 'I',
		##
		'LONG' => 'N', // interbase is numeric, oci8 is blob
		'BIGINT' => 'N', // this is bigger than PHP 32-bit integers
		'DECIMAL' => 'N',
		'DEC' => 'N',
		'REAL' => 'N',
		'DOUBLE' => 'N',
		'DOUBLE PRECISION' => 'N',
		'SMALLFLOAT' => 'N',
		'FLOAT' => 'N',
		'NUMBER' => 'N',
		'NUM' => 'N',
		'NUMERIC' => 'N',
		'MONEY' => 'N',

		## informix 9.2
		'SQLINT' => 'I',
		'SQLSERIAL' => 'I',
		'SQLSMINT' => 'I',
		'SQLSMFLOAT' => 'N',
		'SQLFLOAT' => 'N',
		'SQLMONEY' => 'N',
		'SQLDECIMAL' => 'N',
		'SQLDATE' => 'D',
		'SQLVCHAR' => 'C',
		'SQLCHAR' => 'C',
		'SQLDTIME' => 'T',
		'SQLINTERVAL' => 'N',
		'SQLBYTES' => 'B',
		'SQLTEXT' => 'X',
		 ## informix 10
		"SQLINT8" => 'I8',
		"SQLSERIAL8" => 'I8',
		"SQLNCHAR" => 'C',
		"SQLNVCHAR" => 'C',
		"SQLLVARCHAR" => 'X',
		"SQLBOOL" => 'L'
		);

		if (!$this->connection->isConnected()) {
			$t = strtoupper($t);
			if (isset($typeMap[$t])) return $typeMap[$t];
			return ADODB_DEFAULT_METATYPE;
		}
		return $this->connection->metaType($t,$len,$fieldobj);
	}

	function nameQuote($name = NULL,$allowBrackets=false)
	{
		if (!is_string($name)) {
			return false;
		}

		$name = trim($name);

		if ( !is_object($this->connection) ) {
			return $name;
		}

		$quote = $this->connection->nameQuote;

		// if name is of the form `name`, quote it
		if ( preg_match('/^`(.+)`$/', $name, $matches) ) {
			return $quote . $matches[1] . $quote;
		}

		// if name contains special characters, quote it
		$regex = ($allowBrackets) ? $this->nameRegexBrackets : $this->nameRegex;

		if ( !preg_match('/^[' . $regex . ']+$/', $name) ) {
			return $quote . $name . $quote;
		}

		return $name;
	}

	function tableName($name)
	{
		if ( $this->schema ) {
			return $this->nameQuote($this->schema) .'.'. $this->nameQuote($name);
		}
		return $this->nameQuote($name);
	}

	// Executes the sql array returned by getTableSQL and getIndexSQL
	function executeSQLArray($sql, $continueOnError = true)
	{
		$rez = 2;
		$conn = $this->connection;
		$saved = $conn->debug;
		foreach($sql as $line) {

			if ($this->debug) $conn->debug = true;
			$ok = $conn->execute($line);
			$conn->debug = $saved;
			if (!$ok) {
				if ($this->debug) ADOConnection::outp($conn->errorMsg());
				if (!$continueOnError) return 0;
				$rez = 1;
			}
		}
		return $rez;
	}

	/**
	 * Returns the actual type for a given meta type.
	 *
	 * @param string $meta The meta type to convert:
	 * - C:  varchar
	 * - X:  CLOB (character large object) or
	 *       largest varchar size if CLOB is not supported
	 * - C2: Multibyte varchar
	 * - X2: Multibyte CLOB
	 * - B:  BLOB (binary large object)
	 * - D:  Date
	 * - T:  Date-time
	 * - L:  Integer field suitable for storing booleans (0 or 1)
	 * - I:  Integer
	 * - F:  Floating point number
	 * - N:  Numeric or decimal number
	 *
	 * @return string The actual type corresponding to the meta type.
	 */
	function actualType($meta)
	{
		$meta = strtoupper($meta);

		// Add support for custom meta types. We do this
		// first, that allows us to override existing types
		if (isset($this->connection->customMetaTypes[$meta])) {
			return $this->connection->customMetaTypes[$meta]['actual'];
		}

		return $meta;
	}

	function createDatabase($dbname,$options=false)
	{
		$options = $this->_options($options);
		$sql = array();

		$s = 'CREATE DATABASE ' . $this->nameQuote($dbname);
		if (isset($options[$this->upperName]))
			$s .= ' '.$options[$this->upperName];

		$sql[] = $s;
		return $sql;
	}

	/*
	 Generates the SQL to create index. Returns an array of sql strings.
	*/
	function createIndexSQL($idxname, $tabname, $flds, $idxoptions = false)
	{
		if (!is_array($flds)) {
			$flds = explode(',',$flds);
		}

		foreach($flds as $key => $fld) {
			# some indexes can use partial fields, eg. index first 32 chars of "name" with NAME(32)
			$flds[$key] = $this->nameQuote($fld,$allowBrackets=true);
		}

		return $this->_indexSQL($this->nameQuote($idxname), $this->tableName($tabname), $flds, $this->_options($idxoptions));
	}

	function dropIndexSQL ($idxname, $tabname = NULL)
	{
		return array(sprintf($this->dropIndex, $this->nameQuote($idxname), $this->tableName($tabname)));
	}

	function setSchema($schema)
	{
		$this->schema = $schema;
	}

	function addColumnSQL($tabname, $flds)
	{
		$tabname = $this->tableName($tabname);
		$sql = array();
		list($lines,$pkey,$idxs) = $this->_genFields($flds);
		// genfields can return FALSE at times
		if ($lines  == null) $lines = array();
		$alter = 'ALTER TABLE ' . $tabname . $this->addCol . ' ';
		foreach($lines as $v) {
			$sql[] = $alter . $v;
		}
		if (is_array($idxs)) {
			foreach($idxs as $idx => $idxdef) {
				$sql_idxs = $this->createIndexSql($idx, $tabname, $idxdef['cols'], $idxdef['opts']);
				$sql = array_merge($sql, $sql_idxs);
			}
		}
		return $sql;
	}

	/**
	 * Change the definition of one column
	 *
	 * As some DBMs can't do that on their own, you need to supply the complete definition of the new table,
	 * to allow recreating the table and copying the content over to the new table
	 *
	 * @param string       $tabname table-name
	 * @param array|string $flds column-name and type for the changed column
	 * @param string       $tableflds='' complete definition of the new table, eg. for postgres, default ''
	 * @param array|string $tableoptions='' options for the new table see createTableSQL, default ''
	 *
	 * @return array with SQL strings
	 */
	function alterColumnSQL($tabname, $flds, $tableflds='',$tableoptions='')
	{
		$tabname = $this->tableName($tabname);
		$sql = array();
		$preProcessLines  = [];
		$postProcessLines = [];

		list($lines,$pkey,$idxs) = $this->_genFields($flds);
		
		// genfields can return FALSE at times
		if ($lines == null) { 
			$lines = array();
		}

		/*
		* Execute any driver-specific line changes
		*/
		list ($preProcessLines,$lines, $postProcessLines) = 
			$this->reprocessColumns(
				$tabname,
				$lines,
				self::REPROCESS_TABLE_CHANGE
			);
		
		/*
		* Preprocess lines are executed before the table process,
		* Postprocess lines are executed after
		*/
		if (count($preProcessLines) > 0) {
			$sql = array_merge($sql, $preProcessLines);
		}

		$alter = 'ALTER TABLE ' . $tabname . $this->alterCol . ' ';
		foreach($lines as $v) {
			$sql[] = $alter . $v;
		}
		if (is_array($idxs)) {
			foreach($idxs as $idx => $idxdef) {
				$sql_idxs = $this->createIndexSql($idx, $tabname, $idxdef['cols'], $idxdef['opts']);
				$sql = array_merge($sql, $sql_idxs);
			}

		}

		/*
		* Merge any postprocess lines available
		*/
		if (count($postProcessLines) > 0) {
			$sql = array_merge($sql, $postProcessLines);
		}
		return $sql;
	}

	/**
	 * Rename one column.
	 *
	 * Some DBs can only do this together with changing the type of the column
	 * (even if that stays the same, eg. MySQL < 8.0).
	 *
	 * @param string $tabname   Table name.
	 * @param string $oldcolumn Column to be renamed.
	 * @param string $newcolumn New column name.
	 * @param string $flds      Complete column definition string like for {@see addColumnSQL};
	 *                          This is currently only used by MySQL < 8.0. Defaults to ''.
	 *
	 * @return array SQL statements.
	 */
	function renameColumnSQL($tabname, $oldcolumn, $newcolumn, $flds='')
	{
		$tabname = $this->tableName($tabname);
		$column_def = '';
		if ($flds) {
			list($lines,$pkey,$idxs) = $this->_genFields($flds);
			// genfields can return FALSE at times
			if ($lines == null) {
				$lines = array();
			}
			$first  = current($lines);
			list(,$column_def) = preg_split("/[\t ]+/",$first,2);
		}
		return array(sprintf($this->renameColumn,$tabname,$this->nameQuote($oldcolumn),$this->nameQuote($newcolumn),$column_def));
	}

	/**
	 * Drop one column.
	 *
	 * Some DBs can't do that on their own (e.g. PostgreSQL), so you need
	 * to supply the complete definition of the new table, to allow recreating
	 * it and copying the content over to the new table.
	 *
	 * @param string       $tabname      Table name.
	 * @param string       $flds         Column name and type for the changed column.
	 * @param string       $tableflds    Complete definition of the new table. Defaults to ''.
	 * @param array|string $tableoptions Options for the new table {@see createTableSQL()},
	 *                                   defaults to ''.
	 *
	 * @return array SQL statements.
	 */
	function dropColumnSQL($tabname, $flds, $tableflds='', $tableoptions='')
	{
		$tabname = $this->tableName($tabname);
		if (!is_array($flds)) $flds = explode(',',$flds);
		$sql = array();
		$alter = 'ALTER TABLE ' . $tabname . $this->dropCol . ' ';
		foreach($flds as $v) {
			$sql[] = $alter . $this->nameQuote($v);
		}
		return $sql;
	}

	function dropTableSQL($tabname)
	{
		return array (sprintf($this->dropTable, $this->tableName($tabname)));
	}

	function renameTableSQL($tabname,$newname)
	{
		return array (sprintf($this->renameTable, $this->tableName($tabname),$this->tableName($newname)));
	}

	/**
	 * Called from createTableSQL, The processes all of the statements to
	 * change any Database specific SQL statements that require multiple replacments
	 * This method returns lines that are executed before the table, and those executed
	 * after
	 *
	 * @param string  $tableName
	 * @param array   $lines
	 * @param integer $processType
	 * 
	 * @example a driver that requires an index drop before changing the attributes of
	 *          a column
	 * 
	 * @return array
	 */
	protected function reprocessColumns(string $tableName, array $lines, int $processType) :array
	{
		
		$preProcessLines = [];
		$postProcessLines = [];
		/*
		* See if any individual lines of  SQL needs reprocessing,
		*/
		$metaColumns = $this->metaColumns($tableName);
		if ($metaColumns == false) {
			$metaColumns = [];
		}

		/*
		* Send each line individually so that any pre or post processing
		* can be captured and added to the necessary arrays
		*/
		foreach ($lines as $lineKey => $line) {
			
			list(
				$returnedPreProcessLines,
				$returnedLines,
				$returnedPostProcessLines
				) = $this->lineProcessor($tableName, $lineKey, $line, $metaColumns, $processType);
			
			if (count($returnedLines) == 1) {
				/*
				* Overwrite the existing line with the returned
				* value
				*/
				$lines[$lineKey] = $returnedLines[0];
			} else {
				/*
				* Inject multiple lines as a replacement for
				* the outbound single value
				*/
				$injectedLines = [];
				foreach ($returnedLines as $key => $value) {
					$injectedLines[$lineKey . '-' . $key] = $value;
				}

				/*
				* Remove the original line and replace
				*/
				$lines = array_splice($lines,$lineKey,1,$returnedLines);

			}
			
			$preProcessLines  = array_merge(
				$preProcessLines,
				$returnedPreProcessLines
			);
			
			$postProcessLines  = array_merge(
				$postProcessLines,
				$returnedPostProcessLines
			);
		}

		return array($preProcessLines, $lines, $postProcessLines);
	}

	/**
	 * Rewrite any driver specific SQL and actions to be executed before
	 * and after the main SQL statement is executed based on the
	 * type of calling action
	 *
	 * @param string $tableName   The table name 
	 * @param string $lineKey     The line key
	 * @param string $inboundLine The line to process
	 * @param array  $metaColumns Existing table columns, if any
	 * @param int	 $processMode 1=ADD/2=CHANGE/3=DELETE
	 * 
	 * @return array
	 */
	protected function lineProcessor(
		string $tableName, 
		string $lineKey, 
		string $inboundLine, 
		array $metaColumns, 
		int $processMode = 1
	) : array {
		
		/*
		* The outbound lines can be returned 
		* with any count of replacements
		* @example The DB2 driver
		*/
		$outboundLines = [ 
			$inboundLine 
		];
		
		$preProcessLines  = [];
		$postProcessLines = [];

		return array($preProcessLines, $outboundLines, $postProcessLines);

	}
	
	
	/**
	* Generate the SQL to create a new table. Returns an array of sql strings.
	*
	* @param string $tabname The table name
	* @param string $flds
	* @param mixed  $tableOptions
	*
	* @return array   
	*/
	function createTableSQL($tabname, $flds, $tableoptions=array())
	{
		
		list($lines,$pkey,$idxs) = $this->_genFields($flds, true);
		// genfields can return FALSE at times
		if ($lines == null) { 
			$lines = array();
		}

		$sql = [];
		$preProcessLines  = [];
		$postProcessLines = [];

		/*
		* Execute any driver-specific line changes
		*/
		list ($preProcessLines,$lines, $postProcessLines) = 
			$this->reprocessColumns(
				$tabname,
				$lines,
				self::REPROCESS_TABLE_CREATE
			);
		
		/*
		* Preprocess lines are executed before the table process,
		* Postprocess lines are executed after
		*/
		if (count($preProcessLines) > 0) {
			$sql = array_merge($sql, $preProcessLines);
		}

		$taboptions = $this->_options($tableoptions);
		$tabname = $this->tableName($tabname);
		$sql = array_merge($sql, $this->_tableSQL($tabname,$lines,$pkey,$taboptions));

		// ggiunta - 2006/10/12 - KLUDGE:
        // if we are on autoincrement, and table options includes REPLACE, the
        // autoincrement sequence has already been dropped on table creation sql, so
        // we avoid passing REPLACE to trigger creation code. This prevents
        // creating sql that double-drops the sequence
        if ($this->autoIncrement && isset($taboptions['REPLACE']))
        	unset($taboptions['REPLACE']);
		$tsql = $this->_triggers($tabname,$taboptions);
		foreach($tsql as $s) $sql[] = $s;

		if (is_array($idxs)) {
			foreach($idxs as $idx => $idxdef) {
				$sql_idxs = $this->createIndexSql($idx, $tabname,  $idxdef['cols'], $idxdef['opts']);
				$sql = array_merge($sql, $sql_idxs);
			}
		}

		if (count($postProcessLines) > 0) {
			$sql = array_merge($sql, $postProcessLines);
		}

		return $sql;
	}



	function _genFields($flds,$widespacing=false)
	{
		if (is_string($flds)) {
			$padding = '     ';
			$txt = $flds.$padding;
			$flds = array();
			$flds0 = lens_ParseArgs($txt,',');
			$flds0 = array_filter($flds0);

			$hasparam = false;
			foreach($flds0 as $f0) {
				$f1 = array();
				foreach($f0 as $token) {
					switch (strtoupper($token)) {
					case 'INDEX':
						$f1['INDEX'] = '';
						// fall through intentionally
					case 'CONSTRAINT':
					case 'DEFAULT':
						$hasparam = $token;
						break;
					default:
						if ($hasparam) $f1[$hasparam] = $token;
						else $f1[] = $token;
						$hasparam = false;
						break;
					}
				}
				// 'index' token without a name means single column index: name it after column
				if (array_key_exists('INDEX', $f1) && $f1['INDEX'] == '') {
					$f1['INDEX'] = isset($f0['NAME']) ? $f0['NAME'] : $f0[0];
					// check if column name used to create an index name was quoted
					if (($f1['INDEX'][0] == '"' || $f1['INDEX'][0] == "'" || $f1['INDEX'][0] == "`") &&
						($f1['INDEX'][0] == substr($f1['INDEX'], -1))) {
						$f1['INDEX'] = $f1['INDEX'][0].'idx_'.substr($f1['INDEX'], 1, -1).$f1['INDEX'][0];
					}
					else
						$f1['INDEX'] = 'idx_'.$f1['INDEX'];
				}
				// reset it, so we don't get next field 1st token as INDEX...
				$hasparam = false;

				$flds[] = $f1;

			}
		}
		$this->autoIncrement = false;
		$lines = array();
		$pkey = array();
		$idxs = array();
		foreach($flds as $fld) {
			if (is_array($fld))
				$fld = array_change_key_case($fld,CASE_UPPER);
			$fname = false;
			$fdefault = false;
			$fautoinc = false;
			$ftype = false;
			$fsize = false;
			$fprec = false;
			$fprimary = false;
			$fnoquote = false;
			$fdefts = false;
			$fdefdate = false;
			$fconstraint = false;
			$fnotnull = false;
			$funsigned = false;
			$findex = '';
			$funiqueindex = false;
			$fOptions	  = array();

			//-----------------
			// Parse attributes
			foreach($fld as $attr => $v) {
				if ($attr == 2 && is_numeric($v))
					$attr = 'SIZE';
				elseif ($attr == 2 && strtoupper($ftype) == 'ENUM')
					$attr = 'ENUM';
				else if (is_numeric($attr) && $attr > 1 && !is_numeric($v))
					$attr = strtoupper($v);

				switch($attr) {
				case '0':
				case 'NAME': 	$fname = $v; break;
				case '1':
				case 'TYPE':

					$ty = $v;
					$ftype = $this->actualType(strtoupper($v));
					break;

				case 'SIZE':
					$dotat = strpos($v,'.');
					if ($dotat === false)
						$dotat = strpos($v,',');
					if ($dotat === false)
						$fsize = $v;
					else {

						$fsize = substr($v,0,$dotat);
						$fprec = substr($v,$dotat+1);

					}
					break;
				case 'UNSIGNED': $funsigned = true; break;
				case 'AUTOINCREMENT':
				case 'AUTO':	$fautoinc = true; $fnotnull = true; break;
				case 'KEY':
                // a primary key col can be non unique in itself (if key spans many cols...)
				case 'PRIMARY':	$fprimary = $v; $fnotnull = true; /*$funiqueindex = true;*/ break;
				case 'DEF':
				case 'DEFAULT': $fdefault = $v; break;
				case 'NOTNULL': $fnotnull = $v; break;
				case 'NOQUOTE': $fnoquote = $v; break;
				case 'DEFDATE': $fdefdate = $v; break;
				case 'DEFTIMESTAMP': $fdefts = $v; break;
				case 'CONSTRAINT': $fconstraint = $v; break;
				// let INDEX keyword create a 'very standard' index on column
				case 'INDEX': $findex = $v; break;
				case 'UNIQUE': $funiqueindex = true; break;
				case 'ENUM':
					$fOptions['ENUM'] = $v; break;
				} //switch
			} // foreach $fld

			//--------------------
			// VALIDATE FIELD INFO
			if (!strlen($fname)) {
				if ($this->debug) ADOConnection::outp("Undefined NAME");
				return false;
			}

			$fid = strtoupper(preg_replace('/^`(.+)`$/', '$1', $fname));
			$fname = $this->nameQuote($fname);

			if (!strlen($ftype)) {
				if ($this->debug) ADOConnection::outp("Undefined TYPE for field '$fname'");
				return false;
			} else {
				$ftype = strtoupper($ftype);
			}

			$ftype = $this->_getSize($ftype, $ty, $fsize, $fprec, $fOptions);

			if (($ty == 'X' || $ty == 'X2' || $ty == 'XL' || $ty == 'B') && !$this->blobAllowsNotNull)
				/*
				* some blob types do not accept nulls, so we override the
				* previously defined value
				*/
				$fnotnull = false;

			if ($fprimary)
				$pkey[] = $fname;

			if (($ty == 'X' || $ty == 'X2' || $ty == 'XL' || $ty == 'B') && !$this->blobAllowsDefaultValue)
				/*
				* some databases do not allow blobs to have defaults, so we
				* override the previously defined value
				*/
				$fdefault = false;

			// build list of indexes
			if ($findex != '') {
				if (array_key_exists($findex, $idxs)) {
					$idxs[$findex]['cols'][] = ($fname);
					if (in_array('UNIQUE', $idxs[$findex]['opts']) != $funiqueindex) {
						if ($this->debug) ADOConnection::outp("Index $findex defined once UNIQUE and once not");
					}
					if ($funiqueindex && !in_array('UNIQUE', $idxs[$findex]['opts']))
						$idxs[$findex]['opts'][] = 'UNIQUE';
				}
				else
				{
					$idxs[$findex] = array();
					$idxs[$findex]['cols'] = array($fname);
					if ($funiqueindex)
						$idxs[$findex]['opts'] = array('UNIQUE');
					else
						$idxs[$findex]['opts'] = array();
				}
			}

			//--------------------
			// CONSTRUCT FIELD SQL
			if ($fdefts) {
				$fdefault = $this->connection->sysTimeStamp;
			} else if ($fdefdate) {
				$fdefault = $this->connection->sysDate;
			} else if ($fdefault !== false && !$fnoquote) {
				if ($ty == 'C' or $ty == 'X' or
					( substr($fdefault,0,1) != "'" && !is_numeric($fdefault))) {

					if (($ty == 'D' || $ty == 'T') && strtolower($fdefault) != 'null') {
						// convert default date into database-aware code
						if ($ty == 'T')
						{
							$fdefault = $this->connection->dbTimeStamp($fdefault);
						}
						else
						{
							$fdefault = $this->connection->dbDate($fdefault);
						}
					}
					else
					if (strlen($fdefault) != 1 && substr($fdefault,0,1) == ' ' && substr($fdefault,strlen($fdefault)-1) == ' ')
						$fdefault = trim($fdefault);
					else if (strtolower($fdefault) != 'null')
						$fdefault = $this->connection->qstr($fdefault);
				}
			}
			$suffix = $this->_createSuffix($fname, $ftype, $fnotnull, $fdefault, $fautoinc, $fconstraint, $funsigned, $fprimary, $pkey);

			// add index creation
			if ($widespacing) $fname = str_pad($fname,24);

			 // check for field names appearing twice
            if (array_key_exists($fid, $lines)) {
            	 ADOConnection::outp("Field '$fname' defined twice");
            }

			$lines[$fid] = $fname.' '.$ftype.$suffix;

			if ($fautoinc) $this->autoIncrement = true;
		} // foreach $flds

		return array($lines,$pkey,$idxs);
	}

	/**
		 GENERATE THE SIZE PART OF THE DATATYPE
			$ftype is the actual type
			$ty is the type defined originally in the DDL
	*/
	function _getSize($ftype, $ty, $fsize, $fprec, $options=false)
	{
		if (strlen($fsize) && $ty != 'X' && $ty != 'B' && strpos($ftype,'(') === false) {
			$ftype .= "(".$fsize;
			if (strlen($fprec)) $ftype .= ",".$fprec;
			$ftype .= ')';
		}

		/*
		* Handle additional options
		*/
		if (is_array($options))
		{
			foreach($options as $type=>$value)
			{
				switch ($type)
				{
					case 'ENUM':
					$ftype .= '(' . $value . ')';
					break;

					default:
				}
			}
		}

		return $ftype;
	}


	/**
	 * Construct a database specific SQL string of constraints for column.
	 *
	 * @param string $fname         Column name.
	 * @param string & $ftype       Column type.
	 * @param bool   $fnotnull      Whether the column is NOT NULL.
	 * @param string|bool $fdefault The column's default value.
	 * @param bool   $fautoinc      Whether the column is auto-incrementing.
	 * @param string $fconstraint   Any additional constraints for the column.
	 * @param bool   $funsigned     Whether the column is unsigned.
	 * @param string|bool $fprimary Whether the column is a primary key.
	 * @param array  & $pkey        The primary key definition (list of column names), if applicable.
	 *
	 * @return string Combined constraint string, must start with a space.
	 */
	function _createSuffix($fname, &$ftype, $fnotnull, $fdefault, $fautoinc, $fconstraint, $funsigned, $fprimary, &$pkey)
	{
		$suffix = '';
		if (strlen($fdefault)) $suffix .= " DEFAULT $fdefault";
		if ($fnotnull) $suffix .= ' NOT NULL';
		if ($fconstraint) $suffix .= ' '.$fconstraint;
		return $suffix;
	}

	/**
	 * Creates the SQL statements to create or replace an index.
	 *
	 * @param string $idxname    The name of the index.
	 * @param string $tabname    The name of the table.
	 * @param mixed  $flds       The fields for the index, as a string or array.
	 * @param array  $idxoptions Options for the index, such as UNIQUE, FULLTEXT, etc.
	 *
	 * @return array SQL statements to create or replace the index.
	 */
	function _indexSQL($idxname, $tabname, $flds, $idxoptions)
	{
		$sql = array();

		if ( isset($idxoptions['REPLACE']) || isset($idxoptions['DROP']) ) {
			$sql[] = sprintf ($this->dropIndex, $idxname);
			if ( isset($idxoptions['DROP']) )
				return $sql;
		}

		if ( empty ($flds) ) {
			return $sql;
		}

		$unique = isset($idxoptions['UNIQUE']) ? ' UNIQUE' : '';

		$s = 'CREATE' . $unique . ' INDEX ' . $idxname . ' ON ' . $tabname . ' ';

		if ( isset($idxoptions[$this->upperName]) )
			$s .= $idxoptions[$this->upperName];

		if ( is_array($flds) )
			$flds = implode(', ',$flds);
		$s .= '(' . $flds . ')';
		$sql[] = $s;

		return $sql;
	}

	function _dropAutoIncrement($tabname)
	{
		return false;
	}

	

	function _tableSQL($tabname,$lines,$pkey,$tableoptions)
	{
		$sql = array();

		if (isset($tableoptions['REPLACE']) || isset ($tableoptions['DROP'])) {
			$sql[] = sprintf($this->dropTable,$tabname);
			if ($this->autoIncrement) {
				$sInc = $this->_dropAutoIncrement($tabname);
				if ($sInc) $sql[] = $sInc;
			}
			if ( isset ($tableoptions['DROP']) ) {
				return $sql;
			}
		}

		$s = "CREATE TABLE $tabname (\n";
		$s .= implode(",\n", $lines);
		if (is_array($pkey) && sizeof($pkey)>0) {
			$s .= ",\n                 PRIMARY KEY (";
			$s .= implode(", ",$pkey).")";
		}
		if (isset($tableoptions['CONSTRAINTS']))
			$s .= "\n".$tableoptions['CONSTRAINTS'];

		if (isset($tableoptions[$this->upperName.'_CONSTRAINTS']))
			$s .= "\n".$tableoptions[$this->upperName.'_CONSTRAINTS'];

		$s .= "\n)";
		if (isset($tableoptions[$this->upperName])) $s .= $tableoptions[$this->upperName];
		$sql[] = $s;

		return $sql;
	}

	/**
		GENERATE TRIGGERS IF NEEDED
		used when table has auto-incrementing field that is emulated using triggers
	*/
	function _triggers($tabname,$taboptions)
	{
		return array();
	}

	/**
		Sanitize options, so that array elements with no keys are promoted to keys
	*/
	function _options($opts)
	{
		if (!is_array($opts)) return array();
		$newopts = array();
		foreach($opts as $k => $v) {
			if (is_numeric($k)) $newopts[strtoupper($v)] = $v;
			else $newopts[strtoupper($k)] = $v;
		}
		return $newopts;
	}


	function _getSizePrec($size)
	{
		$fsize = false;
		$fprec = false;
		$dotat = strpos($size,'.');
		if ($dotat === false) $dotat = strpos($size,',');
		if ($dotat === false) $fsize = $size;
		else {
			$fsize = substr($size,0,$dotat);
			$fprec = substr($size,$dotat+1);
		}
		return array($fsize, $fprec);
	}

	/**
	 * This function changes/adds new fields to your table.
	 *
	 * You don't have to know if the col is new or not. It will check on its own.
	 * If the $dropOldFlds parameter is set to true, it will drop the old fields, if
	 * not set then any column not specified in the $flds parameter will be left as is,
	 * which means you can use this as a bulk update function. Wjen used as a public
	 * method then the $flds value is a string, or as an array from XMLSchema
	 * 
	 * @param string       $tablename    Table to modify
	 * @param string|array $flds         Fields to change/add, either as a string or an array
	 * @param string[]     $tableoptions Table options, see createTableSQL()
	 *                                   for more information, default false
	 * @param bool         $dropOldFlds  If set to true, it will drop any old fields not specified in $flds,
	 *
	 * @return string[] Array of SQL Commands
	 */
	function changeTableSQL($tablename, $flds, $tableoptions = false, $dropOldFlds=false)
	{
		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
		if ($this->connection->fetchMode !== false) { 
			$savem = $this->connection->setFetchMode(false);
		}
		// check table exists
		$save_handler = $this->connection->raiseErrorFn;
		$this->connection->raiseErrorFn = '';


		$existingTables = $this->connection->MetaTables('TABLE', '',$tablename);
		if (!$existingTables) {
			$existingTables = array();
		}
				
		if (!in_array($tablename, $existingTables)) {
			/*
			* Use the createTableSQL function to create the table
			*/
			$ADODB_FETCH_MODE = $save;
			return $this->createTableSQL($tablename, $flds, $tableoptions);
		}

		$metaColumns = $this->metaColumns($tablename);
		$this->connection->raiseErrorFn = $save_handler;

		if (isset($savem)) {
			$this->connection->setFetchMode($savem);
		}
		
		$ADODB_FETCH_MODE = $save;

		if (is_array($flds)) {
			/*
			* $flds as array comes from the XML schema functions
			*/
			return $this->changeTableFromArray($tablename, $flds, $metaColumns, $dropOldFlds);
		} else {
			/*
			* $flds as a string comes from changeTableSql
			*/
			return $this->changeTableFromString($tablename, $flds, $metaColumns, $dropOldFlds);
		}

	}

	/**
	 * This function changes/adds new fields to your table when passed as an array
	 * such as when executed by XMLSchema
	 *
	 * @param string $tablename   The table name to process
	 * @param array  $sourceArray A set of required field changes as an array
	 * @param array  $metaColumns The metaColumns array for the current table
	 * @param bool   $dropOldFlds Whether to drop any columns that are not
	 *                            included in the field definitions
	 *
	 * @return string[] Array of SQL Commands
	 */
	protected function changeTableFromArray(
		string $tablename, 
		array $sourceArray, 
		array $metaColumns, 
		bool $dropOldFlds
	) :array {

		$processedColumns       = [];
		$columnsToAdd   		= [];
		$columnsToAlter 		= [];
		$notNullDisables    	= [];
		$autoIncrementDisables 	= [];

		foreach($sourceArray as $sourceKey=>$sourceValue) {
	
			$newColumnName = $sourceKey;
			$newMetaType   = $sourceValue['TYPE'];
			$newMaxLength    = -1;
			$newScale        = -1;
			$newDefaultValue = false;

			$requiresAutoIncrement = false;
			$requiresDefaultValue  = false;

			if ( isset($metaColumns[$newColumnName]) && is_object($metaColumns[$newColumnName]) ) {
				
				/*
				* If the column already exists, we need to check if it needs to be altered.
				* We check the meta type, max length, scale, auto_increment and default value
				* to determine if an alteration is required.
				*/
				
				// If already not allowing nulls, then don't change
				$obj = $metaColumns[$newColumnName];
				
				if (isset($obj->not_null) && $obj->not_null){
					if (in_array('NOT NULL', $sourceValue)) {
						$notNullDisables[$newColumnName] = true;
					}
					
				}
				
				$requiresAutoIncrement = array_key_exists('AUTOINCREMENT', $sourceValue);
				if (isset($obj->auto_increment) && $obj->auto_increment) {
					// If already auto_increment, then don't reapply
					
					if ($requiresAutoIncrement !== false) {
						// Set flag to remove AUTOINCREMENT from the source field definition
						$requiresAutoIncrement = false;
						$autoIncrementDisables[$newColumnName] = true;
					}
					
				}

				/*
				[NAME] => id
				[TYPE] => I
				[SIZE] => 
				[DESCR] => A unique ID assigned to each record.
				[KEY] => KEY
				[AUTOINCREMENT] => AUTOINCREMENT
				*/

				$c = $metaColumns[$newColumnName];
				
				$currentMaxLength = $c->max_length;
				
				$currentMetaType = $this->metaType($c->type,$currentMaxLength);
		
				/*
				* Validates if a default value is set and if it is now
				* or changed from the previous value.
				*/
				$defaultsIndex = array_search('DEFAULT', $sourceValue);
				if ($defaultsIndex !== false) {
					$newDefaultValue = $sourceValue[$defaultsIndex + 1];

					if (!$c->has_default || ($c->has_default && $c->default_value != $newDefaultValue)) {
						// If the default value is different, we need to alter it
						$requiresDefaultValue = true;
					}
				}
				if (isset($c->scale)) {
					$currentScale = $c->scale;
				} else {
					$currentScale = 99; // always force change if scale not known.
				}

				if ($currentScale == -1) { 
					$currentScale = false;
				}

				if ($currentMetaType == 'N') {
					list($newMaxLength, $newScale) = $this->_getSizePrec($sourceValue['SIZE']);
				}
				if ($currentMaxLength == -1) { 
					$currentMaxLength = '';
				}

				if (in_array($currentMetaType,array('C','X','X2','XL','B'))) {

					if (isset($sourceValue['SIZE']) && $sourceValue['SIZE'] != null && is_numeric($sourceValue['SIZE'])) {
						$newMaxLength = $sourceValue['SIZE'];
					} else {
						$newMaxLength = $currentMaxLength;
					} 
				}

				/*
				* Any of the following conditions will trigger an attemp
				* to alter the column
				*/
				if ($currentMetaType != $newMetaType
				|| ($currentMaxLength != $newMaxLength && $newMaxLength != -1) 
				|| ($currentScale != $newScale && $newScale != -1)
				|| $requiresAutoIncrement
				|| $requiresDefaultValue
				) {
					$columnsToAlter[$newColumnName] = $sourceValue;
				}
			} else {
				/*
				* cannot find in the existing metaColumns
				*/
				$columnsToAdd[$newColumnName] = $sourceValue;
			}
			
			/*
			* Lists all of the processes columns, to be compared 
			* against the existing columns in the table.
			* This is used to determine which columns to drop.
			*/
			$processedColumns[$newColumnName] = $sourceValue;
		}


		$addColumnsArray = array();
		$modColumnsArray = array();

		foreach($sourceArray as $columnIndex=>$vData) {

			if (array_key_exists('DEFAULT',$vData)) {
				/*
				* Wrap it in quotes so that it does not upset genfields()
				*/
				$vData['DEFAULT'] = sprintf("DEFAULT '%s'",$vData['DEFAULT']);
			}

			$columnData    = implode(' ',$vData);
				
			$newColumnName = trim($vData['NAME']);

			/*
			* we must check to see if we need to adjust the notnull or autoincrement
			* settings for the column
			*/
			if (isset($notNullDisables[$columnIndex])) {
				$columnData = str_replace('NOT NULL', '', $columnData);
			}
			
			if (isset($autoIncrementDisables[$columnIndex
			])) {
				$columnData = str_replace('AUTOINCREMENT', '', $columnData);
			}

			if (isset($columnsToAlter[$columnIndex])) {
				$modColumnsArray[] = $columnData;
			} else if (isset($columnsToAdd[$columnIndex])) {
				// if the column is not in the table, then add it
				$addColumnsArray[] = $columnData;
			}
		}

		return $this->generateChangeTableSqlArray(
			$tablename,
			$addColumnsArray,
			$modColumnsArray,
			$metaColumns,
			$processedColumns,
			$dropOldFlds
		);

	}

	

	/**
	 * This function changes/adds new fields to your table when passed as a string
	 * Used from the public method changeTableSQL()
	 * 
	 * @param string $tablename    Table to modify
	 * @param string $sourceString The source string containing the new fields
	 * @param array  $metaColumns  metaColumns array
	 * @param bool   $dropOldFlds  bool indicating whether to drop old fields or not
	 *
	 * @return string[] Array of SQL Commands
	 */
	protected function changeTableFromString(
		string $tablename, 
		string $sourceString, 
		array $metaColumns, 
		bool $dropOldFlds
		) : array {

		
		/*
		* Converts the source into an ADOdb standard array
		*/
		$sourceArray = lens_ParseArgs($sourceString,',');
		
		$sourceArray = array_filter($sourceArray);

		$processedColumns 		= [];
		$columnsToAdd 			= [];
		$columnsToAlter 		= [];
		$notNullDisables    	= [];
		$autoIncrementDisables 	= [];

		/*
		* The iteration checks for various conditions and splits the
		* sourceArray into fields to add/change/delete.
		*/

		foreach($sourceArray as $sourceKey=>$sourceValue) {

			$newColumnName   = $sourceValue[0];
			$newMetaType     = $sourceValue[1];
			$newMaxLength    = -1;
			$newScale        = -1;
			$newDefaultValue = false;

			$requiresAutoIncrement = false;
			$requiresDefaultValue  = false;

			if ( isset($metaColumns[$newColumnName]) && is_object($metaColumns[$newColumnName]) ) {
				
				/*
				* If the column already exists, we need to check if it needs to be altered.
				* We check the meta type, max length, scale, auto_increment and default value
				* to determine if an alteration is required.
				*/
				
				// If already not allowing nulls, then don't change
				$obj = $metaColumns[$newColumnName];
				
				if (isset($obj->not_null) && $obj->not_null){
					if (in_array('NOT NULL', $sourceValue)) {
						/*
						* Remove NOT NULL from the field definition
						*/
						$notNullDisables[$newColumnName] = true;
					}
					
				}
				
				$requiresAutoIncrement = array_search('AUTOINCREMENT', $sourceValue);
				if (isset($obj->auto_increment) && $obj->auto_increment) {
					// If already auto_increment, then don't reapply
					
					if ($requiresAutoIncrement !== false) {
						// Set flag to remove AUTOINCREMENT from the source field definition
						$requiresAutoIncrement = false;
						$autoIncrementDisables[$newColumnName] = true;
					}
					
				}

				$c = $metaColumns[$newColumnName];
				
				$currentMaxLength = $c->max_length;
				
				$currentMetaType = $this->metaType($c->type,$currentMaxLength);

				/*
				* Validates if a default value is set and if it is now
				* or changed from the previous value.
				*/
				$defaultsIndex = array_search('DEFAULT', $sourceValue);
				if ($defaultsIndex !== false) {
					$newDefaultValue = $sourceValue[$defaultsIndex + 1];

					if (!$c->has_default || ($c->has_default && $c->default_value != $newDefaultValue)) {
						// If the default value is different, we need to alter it
						$requiresDefaultValue = true;
					}
				}
				if (isset($c->scale)) {
					$currentScale = $c->scale;
				} else {
					$currentScale = 99; // always force change if scale not known.
				}

				if ($currentScale == -1) { 
					$currentScale = false;
				}

				if ($currentMetaType == 'N') {
					list($newMaxLength, $newScale) = $this->_getSizePrec($sourceValue[2]);
				}
				if ($currentMaxLength == -1) { 
					$currentMaxLength = '';
				}

				if (in_array($currentMetaType,array('C','X','X2','XL'))) {

					if (isset($sourceValue[2]) && is_numeric($sourceValue[2])) {
						$newMaxLength = $sourceValue[2];
					} 
				}

				/*
				* Any of the following conditions will trigger an attempt
				* to alter the column
				*/
				if ($currentMetaType != $newMetaType
				|| ($currentMaxLength != $newMaxLength && $newMaxLength != -1) 
				|| ($currentScale != $newScale && $newScale != -1)
				|| $requiresAutoIncrement
				|| $requiresDefaultValue
				) {
					$columnsToAlter[$newColumnName] = $sourceValue;
				}
			} else {
				/*
				* cannot find in the existing metaColumns
				*/
				$columnsToAdd[$newColumnName] = $sourceValue;
			}
			
			/*
			* Lists all of the processed columns, to be compared 
			* against the existing columns in the table.
			* This is used to determine which columns to drop if
			* the dropFlds flag is set, else they will be ignored
			*/
			$processedColumns[$newColumnName] = $sourceValue;
		}
		

		/*
		* now take the source data, clean it and split it into add/change/delete 
		*/
		$targetArray = preg_split('/[\r\n]/',$sourceString);
		$targetArray = array_map('trim', $targetArray);
		$targetArray = array_filter($targetArray);
		$targetArray = array_values($targetArray);
		
		$addColumnsArray = array();
		$modColumnsArray = array();
		

		foreach($targetArray as $columnIndex=>$columnData) {

			$vData = explode(' ',$columnData);
			$newColumnName = trim($vData[0]);

			/*
			* we must check to see if we need to adjust the notnull or autoincrement
			* settings for the column
			*/
			if (isset($notNullDisables[$newColumnName])) {
				$columnData = str_replace('NOT NULL', '', $columnData);
			}
			
			if (isset($autoIncrementDisables[$newColumnName])) {
				$columnData = str_replace('AUTOINCREMENT', '', $columnData);
			}

			if (isset($columnsToAlter[$newColumnName])) {
				$modColumnsArray[] = $columnData;
			} else if (isset($columnsToAdd[$newColumnName])) {
				// if the column is not in the table, then add it
				$addColumnsArray[] = $columnData;
			}
		}

		return $this->generateChangeTableSqlArray(
			$tablename,
			$addColumnsArray,
			$modColumnsArray,
			$metaColumns,
			$processedColumns,
			$dropOldFlds
		);

	}

	/**
	 * Combine the Add/Change/Delete of columns into a single array
	 *
	 * @param string  $tablename        The table to process
	 * @param array   $addColumnsArray  An array of columns to add
	 * @param array   $modColumnsArray  An array of columns to change
	 * @param array   $metaColumns      The existing columns in the tables
	 * @param array   $processedColumns A combined list of all the columns handled
	 * @param boolean $dropOldFlds      Flag indicating whether to drop old fields
	 * 
	 * @return array
	 */
	protected function generateChangeTableSqlArray(
		string $tablename,
		array $addColumnsArray,
		array $modColumnsArray,
		array $metaColumns,
		array $processedColumns,
		bool $dropOldFlds
	) : array {

		$sqlArray = array();

		foreach($modColumnsArray as $mca) {
			$sqlArray = array_merge(
				$sqlArray,
				$this->alterColumnSql($tablename, $mca)
			);
		}

		foreach($addColumnsArray as $aca) {
			$sqlArray = array_merge(
				$sqlArray,
				$this->addColumnSql($tablename, $aca)
			);
		}

		
		/*
		* XML will never drop fields here because it handles it
		* separately
		*/
		if ($dropOldFlds) {

			$currentCols = array_keys($metaColumns);
			$newCols	 = array_keys($processedColumns);
			/*
			* unpick the remaining columns that don't appear in the source data
			* and drop them
			*/
			$dropFlds    = array_diff($currentCols, $newCols);
			if (count($dropFlds) > 0) {
				$dropSql  = $this->dropColumnSQL($tablename, $dropFlds);
				$sqlArray = array_merge($sqlArray, $dropSql);
			}
		}

		return $sqlArray;

	}

} // class
