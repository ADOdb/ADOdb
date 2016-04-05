<?php
/**
* Base Class for handling COMMENT
*
* This is also a an example of how this option can be
* extended on a driver-by-driver basis. In this example, the default
* behaviour is to not support the attribute, but check __DIR__/mysql
* to see how we extend the option for the mysql driver
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
	    $this->text = '';
	}
}
?>
	