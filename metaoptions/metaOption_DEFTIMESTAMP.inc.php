<?php
/**
* Base Class for handling DEFTIMESTAMP
*/
class metaOption_DEFTIMESTAMP extends metaOption
{
	
	function __construct()
	{
		$args         = func_get_args();
		$dict         = $args[0];
		$defaultValue = $args[1];
		
		$this->text = 'DEFAULT ' . $dict->connection->sysTimeStamp;
		
	}
}
?>
	