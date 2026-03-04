<?php
    /**
 * Base class for Database-specific PDO drivers.
 */

namespace ADOdb\Resources\PDO;

class ADOBaseConnection extends \ADOdb\Resources\PDO\ADOConnection
{

	/**
	 * Initialize parent driver properties with driver-specific values.
	 *
	 * Called by {@see ADODB_pdo::_UpdatePDO()}.
	 *
	 * @param ADODB_pdo $parentDriver
	 * @return void
	 * @internal
	 */
	public function _init($parentDriver)
	{
		$parentDriver->_bindInputArray = true;
	}

	function ServerInfo()
	{
		return $this->metaObject->serverInfo();
	}

	/**
	  * Gets the database name from the DSN
	  *
	  * @param	string	$dsnString
	  *
	  * @return string
	  */
	protected function xgetDatabasenameFromDsn($dsnString){

		$dsnArray = preg_split('/[;=]+/',$dsnString);
		$dbIndex  = array_search('database',$dsnArray);

		return $dsnArray[$dbIndex + 1];
	}


}
