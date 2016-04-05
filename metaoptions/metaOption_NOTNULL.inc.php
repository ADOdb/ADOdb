<?php
/**
* Base Class for handling NOTNULL
*/
class metaOption_NOTNULL extends metaOption
{
	/*
	* We force this to be at the front of the list for ANSI SQL purposes
	*/
	protected $priority = 0;
	
	function __construct()
	{
	    $this->text = 'NOT NULL';
	}
}
?>
	