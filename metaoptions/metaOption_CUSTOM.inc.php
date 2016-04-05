<?php
/**
* Base Class for handling Custom items
*
* Any Attribute that is not handled by an autoloaded class is processed
* by this simple class
*
*/
class metaOption_CUSTOM extends metaOption
{
	
	/*
	* This tells the metaOptionsParser to not use the legacy parser to process
	* the attribute. In this case, the options are appended as-is to the 
	* _genfields line
	*/
	protected $legacyParser = false;
	
	function __construct()
	{
	    $args         = func_get_args();
		
		$dict         = $args[0];
		$value 		  = $args[1];
		$key 		  = $args[2];
		$this->text = $key . ' ' . $value;
	}
}
?>
	