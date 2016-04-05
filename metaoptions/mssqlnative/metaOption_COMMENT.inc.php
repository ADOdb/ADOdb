<?php
/**
*  Class for handling COMMENT in mssqlnative driver
*
*/
class metaOption_COMMENT extends metaOption
{
	/*
	* We are doing all the parsing in the metaObjectParser, not the legacy
	* system
	*/
	protected $legacyParser = false;

	function __construct()
	{
	    $args         = func_get_args();
		$dict         = $args[0];
		$defaultValue = $args[1];
		
		/*
		* Remove any leading or trailing quotes, and set the appropriate
		* quoting for the string
		*/
		if (preg_match_all('/^[\'"`](.*?)[\'"`]$/',$defaultValue,$deQuoted))
		{
			$defaultValue = $deQuoted[1][0];
		}
		$defaultValue = $dict->connection->qstr($defaultValue); 
		
		$this->text = 'COMMENT ' . $defaultValue;

	}
}
?>