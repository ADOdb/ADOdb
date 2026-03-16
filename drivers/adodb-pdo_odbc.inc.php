<?php
/**
 * lightweight PDO ODBC driver
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

class ADODB_pdo_odbc extends ADODB_pdo {

    /*
    * Because we don't know what the end point database is, 
    * we can't support any of the database specific functions
    */    
	public $hasTransactions = false;
	public $hasInsertID     = false;

    public $metaColumnsSQL;

	function _init($parentDriver){}

	/**
	 * Calculate the offset of a date for a particular database
	 * and generate appropriate SQL.
	 *
	 * Useful for calculating future/past dates and storing in a database.
	 *
	 * @param double       $dayFraction 1.5 means 1.5 days from now, 1.0/24 for 1 hour
	 * @param string|false $date        Reference date, false for system time
	 *
	 * @return string
	 */
	function OffsetDate($dayFraction, $date=false)
	{
		if (!$date) {
			$date = $this->sysDate;
		}

		$fraction = $dayFraction * 24 * 3600;
		return $date . ' + INTERVAL ' .	$fraction . ' SECOND';
//		return "from_unixtime(unix_timestamp($date)+$fraction)";
	}
	
	/**
	 * Get information about the current server.
	 *
	 * @return array
	 */
	public function serverInfo()
	{
		$arr = array();
		$arr['description'] = ADOConnection::GetOne('select version()');
		$arr['version'] 	= ADOConnection::_findvers($arr['description']);
		return $arr;
	}

    /**
	  * Gets the database name from the DSN
	  *
	  * @param	string	$dsnString
	  *
	  * @return string
	  */
	  protected function getDatabasenameFromDsn($dsnString){

		return $dsnString;
	}

}
