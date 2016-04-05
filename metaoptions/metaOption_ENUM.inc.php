<?php
/**
* Base Class for handling ENUM
*/
class metaOption_ENUM extends metaOption
{
	/*
	* We use this to get ANSI SQL ordering when used with
	* NOT NULL
	*/
	protected $priority = 5;
	
	function __construct()
	{
		$args         = func_get_args();
		$dict         = $args[0];
		$defaultValue = $args[1];
		
		/*
		* The _genFields method needs the ENUM and the values
		* jammed together without a space, so check that now
		*/
		$this->text = 'ENUM' . trim($defaultValue);
	}
}
?>
	