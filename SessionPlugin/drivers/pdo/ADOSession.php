<?php
/**
* base PDO driver session management functionality for the Sessions package
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\SessionPlugin\drivers\pdo;

use \ADOdb\SessionPlugin;

class ADOSession extends \ADOdb\SessionPlugin\ADOSession {
	
	/*
	* No large object handling required
	*/
	protected string $largeObject = '';
	
	protected string $lobValue = 'null';
	
	final protected function getLobValue(?string $param1=null) : string {
		return 'null';
	}
}