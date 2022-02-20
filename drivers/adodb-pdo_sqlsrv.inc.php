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

	function _init(ADODB_pdo $parentDriver)
	{
		$parentDriver->hasTransactions = true;
		$parentDriver->_bindInputArray = true;
		$parentDriver->hasInsertID = true;
		$parentDriver->fmtTimeStamp = "'Y-m-d H:i:s'";
		$parentDriver->fmtDate = "'Y-m-d'";
	}

	function BeginTrans()
	{
		$returnval = parent::BeginTrans();
		return $returnval;
	}

	function MetaColumns($table, $normalize = true)
	{
		return false;
	}

	function MetaTables($ttype = false, $showSchema = false, $mask = false)
	{
		return false;
	}

	function SelectLimit($sql, $nrows = -1, $offset = -1, $inputarr = false, $secs2cache = 0)
	{
		$ret = ADOConnection::SelectLimit($sql, $nrows, $offset, $inputarr, $secs2cache);
		return $ret;
	}

	function ServerInfo()
	{
		return ADOConnection::ServerInfo();
	}
}

class ADORecordSet_pdo_sqlsrv extends ADORecordSet_pdo
{
	public $databaseType = "pdo_sqlsrv";


	/**
	 * Decodes the special PDO sqlsrv types
	 *
	 * @param string[] $arr 	The raw column data
	 * @return string	The column type
	 */
	protected function decodePdoType($arr)
	{
		if (isset($arr['sqlsrv:decl_type']) && $arr['sqlsrv:decl_type'] <> "null") {
			/*
			* Use the SQL Server driver specific value
			*/
			$type = $arr['sqlsrv:decl_type'];
		}

		else
			$type = parent::decodePdoType($arr);

		return $type;
	}

}

class ADORecordSet_array_pdo_sqlsrv extends ADORecordSet_array_pdo
{
	function SetTransactionMode( $transaction_mode )
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
