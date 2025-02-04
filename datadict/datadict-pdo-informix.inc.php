<?php
/**
 * Data Dictionary for PDO IBM INFORMIX driver
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

// security - hide paths
if (!defined('ADODB_DIR')) die();

class ADODB2_pdo_informix extends ADODB_DataDict {

	public $databaseType = 'pdo_informix';
	public $seqField = false;

	var $alterCol = ' MODIFY COLUMN';
	var $alterTableAddIndex = true;
	
	public $dropTable = 'DROP TABLE IF EXISTS %s';

	var $dropIndex = 'DROP INDEX %s ON %s';
	var $renameColumn = 'ALTER TABLE %s CHANGE COLUMN %s %s %s';	// needs column-definition!

	public $blobAllowsNotNull = true;
	
	public function metaType($t,$len=-1,$fieldobj=false)
	{
		
		print "\nMT=$t";
		
		if (is_object($t)) {
			$fieldobj = $t;
			$t = $fieldobj->type;
		}
	
		$t = strtoupper($t);
		
		if (array_key_exists($t,$this->connection->customActualTypes))
			return  $this->connection->customActualTypes[$t];
	
		if (!is_integer($t))
			return ADODB_DEFAULT_METATYPE;
	
		if ($t >= 255 && $t < 512)
		{
			$t -= 256; 
		}
		
		$typeCrossRef = array(
			0 => 'C', //'CHAR',
			1 => 'I2', //'SMALLINT',
			2 => 'I', //'INTEGER',
			3 => 'F', //'FLOAT',
			4 => 'F', //'SMALLFLOAT',
			5 => 'N', //'DECIMAL',
			6 => 'F', //'SERIAL 1',
			7 => 'D', //'DATE',
			8 => 'N', //'MONEY',
			9 => 'C', //'NULL',
			10 => 'T', //'DATETIME',
			11 => 'I1', //'BYTE',
			12 => 'X', //'TEXT',
			13 => 'C', //'VARCHAR',
			14 => 'T', //'INTERVAL',
			15 => 'C2',//'NCHAR',
			16 => 'C2', //'NVARCHAR',
			17 => 'I8', // 'INT8',
			18 => 'R', //'SERIAL8',
			19 => 'C', //'SET',
			20 => 'C', //'MULTISET',
			21 => 'C', //'LIST',
			22 => 'R', //'ROW (unnamed)',
			23 => 'C', //'COLLECTION',
			40 => 'C2', //'LVARCHAR',
			41 => 'XL',// 'BLOB',
			43 => 'C2', //'LVARCHAR',
			45 => 'L', //'BOOLEAN',
			52 => 'I', //'BIGINT',
			53 => 'R', //'BIGSERIAL',
			2061 => 'C', //'IDSSECURITYLABEL',
			4118 => 'R' //'ROW'
		);
		
		
		if (array_key_exists($t,$typeCrossRef))
		{
			return $typeCrossRef[$t];
		}
		
		return ADODB_DEFAULT_METATYPE;
	}

	public function actualType($meta)
	{
		$meta = strtoupper($meta);
		
		/*
		* Add support for custom meta types. We do this
		* first, that allows us to override existing types
		*/
		if (isset($this->connection->customMetaTypes[$meta]))
			return $this->connection->customMetaTypes[$meta]['actual'];
		
		switch($meta) {
		
		case 'C': return 'VARCHAR';// 255
		case 'XL':
		case 'X': return 'TEXT';

		case 'C2': return 'NVARCHAR';
		case 'X2': return 'BYTE';

		case 'B': return 'BLOB';

		case 'D': return 'DATE';
		case 'TS':
		case 'T': return 'DATETIME';

		case 'L': return 'BOOLEAN';
		case 'I': return 'INTEGER';
		case 'I1': return 'SMALLINT';
		case 'I2': return 'SMALLINT';
		case 'I4': return 'INTEGER';
		case 'I8': return 'INT(8)';

		case 'F': return 'FLOAT';
		case 'N': return 'DECIMAL';
		
		default: 
			return $meta;
		}
	}

	
	// return string must begin with space
	public function _createSuffix($fname, &$ftype, $fnotnull,$fdefault,$fautoinc,$fconstraint,$funsigned)
	{
		if ($fautoinc) {
			$ftype = 'SERIAL';
			return '';
		}
		$suffix = '';
		if (strlen($fdefault)) $suffix .= " DEFAULT $fdefault";
		if ($fnotnull) $suffix .= ' NOT NULL';
		if ($fconstraint) $suffix .= ' '.$fconstraint;
		return $suffix;
	}

}
