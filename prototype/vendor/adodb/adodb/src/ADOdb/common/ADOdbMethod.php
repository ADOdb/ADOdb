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
	
	protected function adodb_key_exists($key, &$arr,$force=2)
	{
		if ($force<=0) {
			// the following is the old behaviour where null or empty fields are ignored
			return (!empty($arr[$key])) || (isset($arr[$key]) && strlen($arr[$key])>0);
		}

		if (isset($arr[$key])) return true;
		## null check below
		if (ADODB_PHPVER >= 0x4010) return array_key_exists($key,$arr);
		return false;
	}
	
}