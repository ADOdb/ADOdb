<?php
/**
 * Active Record implementation. Superset of Zend Framework's.
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
//
//$className = sprintf('%s\Resources\ADOHelpers');
//$cls = new $className;
//include_once(ADODB_DIR.'/adodb-lib.inc.php');


/**
 * Array of ADODB_Active_DB's, indexed by ADODB_Active_Record->_dbat.
 * @see ADODB_Active_Record
 *
 * @global ADODB_Active_DB[] $_ADODB_ACTIVE_DBS
 */
$_ADODB_ACTIVE_DBS = array();

/**
 * Set to true to enable caching of metadata such as field info.
 * @global bool $ADODB_ACTIVE_CACHESECS
 */
$ADODB_ACTIVE_CACHESECS = 0;

/**
 * Set to false to disable safety checks
 * @global bool $ACTIVE_RECORD_SAFETY
 */
$ACTIVE_RECORD_SAFETY = true;

/**
 * Use default values of table definition when creating new active record.
 * @global bool $ADODB_ACTIVE_DEFVALS
 */
$ADODB_ACTIVE_DEFVALS = false;




/**
 * @param object $db    Database connection
 * @param string|int    $index Name of index - can be associative
 *                             for an example see PHPLens Issue No: 17790
 *
 * @return int|string
 */
function ADODB_SetDatabaseAdapter($db, $index=false)
{
	global $_ADODB_ACTIVE_DBS;

	if (!$_ADODB_ACTIVE_DBS) {
		$_ADODB_ACTIVE_DBS = array();
	}

	foreach ($_ADODB_ACTIVE_DBS as $k => $d) {
		if ($d->db === $db) {
			return $k;
		}
	}

	$obj = new ADODB_Active_DB();
	$obj->db = $db;
	$obj->tables = array();

	if (!$index) {
		$index = sizeof($_ADODB_ACTIVE_DBS);
	}

	$_ADODB_ACTIVE_DBS[$index] = $obj;

	return sizeof($_ADODB_ACTIVE_DBS) - 1;
}



/**
 * @param object $db
 * @param string        $class
 * @param string        $table
 * @param string        $whereOrderBy
 * @param array         $bindarr
 * @param array         $primkeyArr
 * @param array         $extra
 *
 * @return array|false
 */
function adodb_GetActiveRecordsClass($db, $class, $table, $whereOrderBy, $bindarr, $primkeyArr, $extra)
{
	$save = $db->SetFetchMode(ADODB_FETCH_NUM);

	$qry = /** @lang text */ "select * from " . $table;

	if (!empty($whereOrderBy)) {
		$qry .= ' WHERE '.$whereOrderBy;
	}
	if(isset($extra['limit'])) {
		$rows = false;
		if(isset($extra['offset'])) {
			$rs = $db->SelectLimit($qry, $extra['limit'], $extra['offset'],$bindarr);
		} else {
			$rs = $db->SelectLimit($qry, $extra['limit'],-1,$bindarr);
		}
		if ($rs) {
			while (!$rs->EOF) {
				$rows[] = $rs->fields;
				$rs->MoveNext();
			}
		}
	} else
		$rows = $db->GetAll($qry,$bindarr);

	$db->SetFetchMode($save);

	if ($rows === false) {
		return false;
	}

	if (!class_exists($class)) {
		$db->outp_throw("Unknown class $class in GetActiveRecordsClass()",'GetActiveRecordsClass');
		return false;
	}
	$arr = array();
	// arrRef will be the structure that knows about our objects.
	// It is an associative array.
	// We will, however, return arr, preserving regular 0.. order so that
	// obj[0] can be used by app developers.
	foreach($rows as $row) {
		$obj = new $class($table,$primkeyArr,$db);
		if ($obj->ErrorNo()){
			$db->_errorMsg = $obj->ErrorMsg();
			return false;
		}
		$obj->Set($row);
		$arr[] = $obj;
	}

	return $arr;
}
