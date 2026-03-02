<?php
/**
 * ADOdb PostgreSQL 9+ driver
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

include_once(ADODB_DIR."/drivers/adodb-postgres8.inc.php");

class ADODB_postgres9 extends ADODB_postgres8
{
	var $databaseType = 'postgres9';

	/**
	 * The class name that provides meta functions
	 * to the the driver. PDO drivers use the same
	 * providers as the native connection for the
	 * same database
	 *
	 * @var string
	 */
	protected string $metaFunctionProvider = 'PostgreSQL';

	/**
	 * The class name that provides dictionary functions
	 * to the the driver. PDO drivers use the same
	 * providers as the native connection for the
	 * same database
	 *
	 * @var string
	 */
	protected string $dataDictionaryProvider = 'PostgreSQL';
}

class ADORecordSet_postgres9 extends ADORecordSet_postgres8
{
	var $databaseType = "postgres9";
}

class ADORecordSet_assoc_postgres9 extends ADORecordSet_assoc_postgres8
{
	var $databaseType = "postgres9";
}
