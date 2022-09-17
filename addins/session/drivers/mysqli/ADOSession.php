<?php
/**
* mysqli driver session co for the Sessions packfigurationage
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\session\drivers\mysqli;

use \ADOdb\addins\session;
use \ADOdb\addins\session\drivers;

final class ADOSession extends \ADOdb\addins\session\ADOSession {


	/*
	* No large object handling required
	*/
	protected string $largeObject = '';

	/*
	* Carried forward from previous version without explanation
	*/
	protected string $binaryOption = '/*! BINARY */';

	/*
	* Whether we should optimaize the table (if supported)
	*/
	protected bool $optimizeTable = false;

	/**
	* MySQL optimize table
	*
	* This is no longer recommended, so is disabled by default
	*
	* @return string		the SQL statement
	*/
	final protected function getOptimizationSql(): ?string {

		return sprintf('OPTIMIZE TABLE %s',$this->tableName);

	}
}