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
namespace ADOdb\SessionPlugin\drivers\mysqli;

use \ADOdb\SessionPlugin;
use \ADOdb\SessionPlugin\drivers;

final class ADOSession extends \ADOdb\SessionPlugin\ADOSession {


	/*
	* No large object handling required
	*/
	protected string $largeObject = '';

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

	/**
	 * Returns a required preprocessed session key value for the given value.
	 *
	 * @param string $value
	 * @return string
	 */
	final protected function processSessionKey(string $value): string
	{
		return sprintf('CAST(%s AS BINARY)',$value);
	}
}