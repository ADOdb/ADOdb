<?php
/**
* MD5 Crypt session management plugin for the Sessions package
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\SessionPlugin\plugins;
use \ADOdb\SessionPlugin;

class MD5Crypt extends \ADOdb\SessionPlugin\plugins\ADOCrypt{

	/**
	* Fetches the encryption key for the scheme
	*
	* return string
	*/
	final protected function fetchEncryptionKey() : string {
	
		return md5(rand(0,32000));
	
	}
}
