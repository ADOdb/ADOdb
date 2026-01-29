<?php
/**
 * Data Dictionary for Microsoft SQL Server native (mssqlnative)

 * FileDescription
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

/*
In ADOdb, named quotes for MS SQL Server use ". From the MSSQL Docs:

	Note Delimiters are for identifiers only. Delimiters cannot be used for keywords,
	whether or not they are marked as reserved in SQL Server.

	Quoted identifiers are delimited by double quotation marks ("):
	SELECT * FROM "Blanks in Table Name"

	Bracketed identifiers are delimited by brackets ([ ]):
	SELECT * FROM [Blanks In Table Name]

	Quoted identifiers are valid only when the QUOTED_IDENTIFIER option is set to ON. By default,
	the Microsoft OLE DB Provider for SQL Server and SQL Server ODBC driver set QUOTED_IDENTIFIER ON
	when they connect.

	In Transact-SQL, the option can be set at various levels using SET QUOTED_IDENTIFIER,
	the quoted identifier option of sp_dboption, or the user options option of sp_configure.

	When SET ANSI_DEFAULTS is ON, SET QUOTED_IDENTIFIER is enabled.

	Syntax

		SET QUOTED_IDENTIFIER { ON | OFF }


*/

// security - hide paths
if (!defined('ADODB_DIR')) die();

class ADODB2_mssqlnative extends ADODB_DataDict {
	var $databaseType = 'mssqlnative';
	var $dropIndex = /** @lang text */ 'DROP INDEX %1$s ON %2$s';
	var $renameTable = "EXEC sp_rename '%s','%s'";
	var $renameColumn = "EXEC sp_rename '%s.%s','%s'";

    /**
     * Type to use for X metaType. TEXT is now deprecated
     *
     * @var string
     */
	public string $typeX = 'VARCHAR(MAX)';


	/**
     * Type to use for XL MetaType. Image is now deprecated
     *
     * @var string
     */
    public string $typeXL = 'VARBINARY(MAX)';

	/**
	 * The mapped type of DATE. Older systems used DATETIME
	 *
	 * @var string
	 */
	public string $typeD = 'DATE';


	public $blobAllowsDefaultValue = true;
	public $blobAllowsNotNull      = true;

	/**
	 * Returns an ADOdb Metatype provided a SQL Server Type
	 *
	 * It is important to send the column object rather than
	 * the string type because the length is important
	 * for distinguishing type
	 *
	 * @param string|object  $t
	 * @param integer        $len
	 * @param boolean|object $fieldobj
	 *
	 * @return string
	 */
	public function metaType($t, $len = -1, $fieldobj = false)
	{
		
        if (is_object($t)) {
			$fieldobj = $t;

			$t      = $fieldobj->type;
			$length = $fieldobj->length;

		} elseif (!$fieldobj && $this->connection->debug) {

			ADOConnection::outp('metaType provides a more accurate result if passed the column object');
		
            }

		$t = strtoupper($t);
		
		if (array_key_exists($t, $this->connection->customActualTypes)) {
			return $this->connection->customActualTypes[$t];
		}

		/*
		* Since the introduction of the requirement of the ODBC driver,
		* types are now string values. The numeric value is in the 
		* xtype field. Older versions of the native client still send
		* numbers
		*/

		if (!preg_match('/^[A-Z2]+$/', $t)) {
			return $this->legacyMetaType($t);
		}

		if ($t == 'VARCHAR' && $length == -1) {
            /*
            * varchat(max)
            */
			$t = 'CLOB';
		} elseif ($t == 'NVARCHAR' && $length == -1) {
            /*
            * nvarchar(max)
            */
			$t = 'NCLOB';
		} elseif ($t == 'VARBINARY' && $length == -1) {
			/*
            * varbinary(max)
            */
            $t = 'IMAGE';
		}
		
		
		switch ($t) {
			case 'VARCHAR':
				return 'C';
			
			case 'NVARCHAR':
				return 'C2';

			case 'CLOB':
				return 'X';

			case 'NCLOB':
				return 'X2';
			
			case 'BINARY':
			case 'VARBINARY':
				return 'B';

			case 'TEXT':
			case 'IMAGE':
				return 'XL';

			case 'DATE':
				return 'D';

			case 'TIME':
			case 'DATETIME':
			case 'DATETIME2':
			case 'SMALLDATETIME':
			case 'DATETIMEOFFSET':
				return 'T';

			case 'NUMERIC':
			case 'DECIMAL':
			case 'MONEY':
			case 'SMALLMONEY':
				return 'F';

			case 'REAL':
				return 'R';

			case 'BIT':
				return 'L';
			
			case 'SMALLINT':
				return 'I2';

			case 'INT':
            case 'INTEGER':
				return 'I4';

			case 'BIGINT':
				return 'I';
		
			default:

				return ADODB_DEFAULT_METATYPE;
		}
	}

    /**
     * Access to Server 2012 and lower actual types
     *
     * @param string  $actualType
     * 
     * @return string
     */
	protected function legacyMetatype(string $actualType): string
	{
		
		$typeConversion = array(
			-155 => 'D',
			  93 => 'D',
			-154 => 'D',
			  -2 => 'D',
			  91 => 'D',

			  12 => 'C',
			   1 => 'C',
			  -9 => 'C',
			  -8 => 'C',

			  -7 => 'L',
			  -6 => 'I2',
			  -5 => 'I8',
			 -11 => 'I',
			   4 => 'I',
			   5 => 'I4',

			  -1 => 'X',
			 -10 => 'X',

			   2 => 'N',
			   3 => 'N',
			   6 => 'N',
			   7 => 'N',

			-152 => 'X',
			-151 => 'X',
			  -4 => 'X',
			  -3 => 'X'
			);

		if (isset($typeConversion[$actualType])) {
			return $typeConversion[$actualType];
		}

		return ADODB_DEFAULT_METATYPE;
	}

    /**
     * Returns a SQL Server type based on a MetaType
     *
     * @param string $meta The metatype
     * 
     * @return string The actual type
     */
	public function ActualType($meta)
	{
		
		$meta = strtoupper($meta);
		
		/*
		* Add support for custom meta types. We do this
		* first, that allows us to override existing types
		*/
		if (isset($this->connection->customMetaTypes[$meta])) {
			return $this->connection->customMetaTypes[$meta]['actual'];
        }

		switch (strtoupper($meta)) {

		    case 'C':
                return 'VARCHAR';
		
            case 'XL':
                return  $this->typeXL;

		    case 'X':
                return $this->typeX;

		    case 'C2':
                return 'NVARCHAR';
        
            case 'X2': 
                return 'NVARCHAR(MAX)';

            case 'B':
                return 'VARBINARY(MAX)';

            case 'D':
                return $this->typeD;

		    case 'T':
            case 'TS':
                return 'DATETIME';
		    
            case 'L':
                return 'BOOLEAN';

		    case 'R':
            case 'F':
                return 'REAL';

            case 'I1':
                return 'TINYINT';
            case 'I2':
                return 'SMALLINT';

            case 'I4':
                return 'INT';

            case 'I':
            case 'I8':
                return 'BIGINT';

		    case 'N':
                return 'NUMERIC';
		    
            default:
			   return $meta;
		}
	}


	function AddColumnSQL($tabname, $flds)
	{
		$tabname = $this->TableName ($tabname);
		$f = array();
		list($lines,) = $this->_GenFields($flds);
		$s = "ALTER TABLE $tabname $this->addCol";
		foreach($lines as $v) {
			$f[] = "\n $v";
		}
		$s .= implode(', ',$f);
		$sql[] = $s;
		return $sql;
	}

	/**
	 * Get a column's default constraint.
	 *
	 * @param string $tabname
	 * @param string $colname
	 * @return string|null The Constraint's name, or null if there is none.
	 */
	function defaultConstraintName($tabname, $colname)
	{
		$sql = "SELECT name FROM sys.default_constraints
			WHERE object_name(parent_object_id) = ?
			AND col_name(parent_object_id, parent_column_id) = ?";
		return $this->connection->getOne($sql, [$tabname, $colname]);
	}

	function AlterColumnSQL($tabname, $flds, $tableflds='',$tableoptions='')
	{
		$tabname = $this->TableName ($tabname);
		$sql = array();

		list($lines,,$idxs) = $this->_GenFields($flds);
		$alter = 'ALTER TABLE ' . $tabname . $this->alterCol . ' ';
		foreach($lines as $v) {
			if ($not_null = preg_match('/NOT NULL/i',$v)) {
				$v = preg_replace('/NOT NULL/i','',$v);
			}
			if (preg_match('/^([^ ]+) .*DEFAULT (\'[^\']+\'|\"[^\"]+\"|[^ ]+)/',$v,$matches)) {
				list(,$colname,$default) = $matches;
				$v = preg_replace('/^' . preg_quote($colname) . '\s/', '', $v);
				$t = trim(str_replace('DEFAULT '.$default,'',$v));
				if ( $constraintname = $this->defaultConstraintName($tabname,$colname) ) {
					$sql[] = 'ALTER TABLE '.$tabname.' DROP CONSTRAINT '. $constraintname;
				}
				if ($not_null) {
					$sql[] = $alter . $colname . ' ' . $t  . ' NOT NULL';
				} else {
					$sql[] = $alter . $colname . ' ' . $t ;
				}
				$sql[] = 'ALTER TABLE ' . $tabname
					. ' ADD CONSTRAINT DF__' . $tabname . '__' .  $colname .  '__' . dechex(rand())
					. ' DEFAULT ' . $default . ' FOR ' . $colname;
			} else {
				$colname = strtok($v," ");
				if ( $constraintname = $this->defaultConstraintName($tabname,$colname) ) {
					$sql[] = 'ALTER TABLE '.$tabname.' DROP CONSTRAINT '. $constraintname;
				}
				if ($not_null) {
					$sql[] = $alter . $v  . ' NOT NULL';
				} else {
					$sql[] = $alter . $v;
				}
			}
		}
		if (is_array($idxs)) {
			foreach($idxs as $idx => $idxdef) {
				$sql_idxs = $this->CreateIndexSql($idx, $tabname, $idxdef['cols'], $idxdef['opts']);
				$sql = array_merge($sql, $sql_idxs);
			}
		}
		return $sql;
	}


	/**
	 * Drop a column, syntax is ALTER TABLE table DROP COLUMN column,column
	 *
	 * @param string   $tabname      Table Name
	 * @param string[] $flds         One, or an array of Fields To Drop
	 * @param string   $tableflds    Throwaway value to make the function match the parent
	 * @param string   $tableoptions Throway value to make the function match the parent
	 *
	 * @return string[]  The SQL necessary to drop the column
	 */
	function DropColumnSQL($tabname, $flds, $tableflds='',$tableoptions='')
	{
		$tabname = $this->TableName ($tabname);
		if (!is_array($flds)) {
			/** @noinspection PhpParamsInspection */
			$flds = explode(',', $flds);
		}
		$f = array();
		$s = 'ALTER TABLE ' . $tabname;
		foreach($flds as $v) {
			if ( $constraintname = $this->defaultConstraintName($tabname,$v) ) {
				$sql[] = 'ALTER TABLE ' . $tabname . ' DROP CONSTRAINT ' . $constraintname;
			}
			$f[] = ' DROP COLUMN ' . $this->NameQuote($v);
		}
		$s .= implode(', ',$f);
		$sql[] = $s;
		return $sql;
	}

	// return string must begin with space

	/** @noinspection DuplicatedCode */
	function _createSuffix($fname, &$ftype, $fnotnull, $fdefault, $fautoinc, $fconstraint, $funsigned, $fprimary, &$pkey)
	{
		$suffix = '';
		if (strlen($fdefault)) $suffix .= " DEFAULT $fdefault";
		if ($fautoinc) $suffix .= ' IDENTITY(1,1)';
		if ($fnotnull) $suffix .= ' NOT NULL';
		else if ($suffix == '') $suffix .= ' NULL';
		if ($fconstraint) $suffix .= ' '.$fconstraint;
		return $suffix;
	}

	/** @noinspection DuplicatedCode */
	function _IndexSQL($idxname, $tabname, $flds, $idxoptions)
	{
		$sql = array();

		if ( isset($idxoptions['REPLACE']) || isset($idxoptions['DROP']) ) {
			$sql[] = sprintf ($this->dropIndex, $idxname, $tabname);
			if ( isset($idxoptions['DROP']) )
				return $sql;
		}

		if ( empty ($flds) ) {
			return $sql;
		}

		$unique = isset($idxoptions['UNIQUE']) ? ' UNIQUE' : '';
		$clustered = isset($idxoptions['CLUSTERED']) ? ' CLUSTERED' : '';

		if ( is_array($flds) )
			$flds = implode(', ',$flds);
		$s = 'CREATE' . $unique . $clustered . ' INDEX ' . $idxname . ' ON ' . $tabname . ' (' . $flds . ')';

		if ( isset($idxoptions[$this->upperName]) )
			$s .= $idxoptions[$this->upperName];


		$sql[] = $s;

		return $sql;
	}


	function _GetSize($ftype, $ty, $fsize, $fprec, $options=false)
	{
		switch ($ftype) {
			case 'INT':
			case 'SMALLINT':
			case 'TINYINT':
			case 'BIGINT':
				return $ftype;
		}
		if ($ty == 'T') {
			return $ftype;
		}
		return parent::_GetSize($ftype, $ty, $fsize, $fprec, $options);
	}
}
