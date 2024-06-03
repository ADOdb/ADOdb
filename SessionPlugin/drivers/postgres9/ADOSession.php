<?php
/**
* postgresql driver configuration for the Sessions package
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\session\drivers\postgres9;

use \ADOdb\addins\session;

class ADOSession extends \ADOdb\addins\session\ADOSession {

	/*
	* Large object handling required
	*/
	protected string $largeObject = 'bytea';

	/*
	* Whether we should optimaize the table (if supported)
	*/
	protected bool $optimizeTable = true;

	/**
	* Functionality to optimize the table
	*/
	final protected function getOptimizationSql(): ?string
	{

		return sprintf('VACUUM %s',$this->tableName);

	}
}