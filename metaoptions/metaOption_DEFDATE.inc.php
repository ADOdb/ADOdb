<?php
/**
* Base Class for handling DEFDATE
*/
class metaOption_DEFDATE extends metaOption
{
	
	function __construct()
	{
		$args         = func_get_args();
		$dict         = $args[0];
		$defaultValue = $args[1];
		
		$this->text = 'DEFAULT ' . $dict->connection->sysDate;
		
	}
}
?>
	