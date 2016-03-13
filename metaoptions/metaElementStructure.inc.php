<?php
/**
* Class that represents the structure of a table element
*
* The element can represent anything, a column,index,column attribute etc
*/
class metaElementStructure
{
	public $type;
	public $name;
	public $value;
	public $platform;
	public $action=0;
	public $attributes = array();
}
?>