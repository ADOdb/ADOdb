<?php
namespace ADOdb\common;

use ADOdb;

/**
* Method template for functions turned into classes
*
*/
class ADOdbMethod
{

	/*
	* The result of the function that was executed
	*/
	protected $methodResult;

	
	/**
	* Returns the result of the method
	*
	* @return mixed
	*/
	final public function getResult()
	{
		return $this->methodResult;
	}
	
}