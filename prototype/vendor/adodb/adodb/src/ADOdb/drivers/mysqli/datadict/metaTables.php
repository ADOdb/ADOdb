<?php
namespace ADOdb\drivers\mysqli\datadict;
use ADOdb;

class metaTables extends \ADOdb\common\datadict\metaTables
{
	
	var $metaTablesSQL = "SELECT
			TABLE_NAME,
			CASE WHEN TABLE_TYPE = 'VIEW' THEN 'V' ELSE 'T' END
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_SCHEMA=";
	
	/**
	 * Retrieves a list of tables based on given criteria
	 *
	 * @param string $ttype Table type = 'TABLE', 'VIEW' or false=both (default)
	 * @param string $showSchema schema name, false = current schema (default)
	 * @param string $mask filters the table by name
	 *
	 * @return array list of tables
	 */
	function __construct($connection,$ttype=false,$showSchema=false,$mask=false)
	{
		$save = $this->metaTablesSQL;
		
		if ($showSchema && is_string($showSchema)) {
			$this->metaTablesSQL .= $connection->qstr($showSchema);
		} else {
			$this->metaTablesSQL .= "schema()";
		}

		if ($mask) {
			$mask = $connection->qstr($mask);
			$this->metaTablesSQL .= " AND table_name LIKE $mask";
		}
		
		$this->retrieveMetaTables($connection,$ttype=false,$showSchema=false,$mask=false);

		//$connection->metaTablesSQL = $save;
		//$this->function = $ret;
	}
}