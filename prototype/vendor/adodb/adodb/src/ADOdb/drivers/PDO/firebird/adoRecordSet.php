<?php
/**
*
* PDO driver extended for Firebird
*
* @version   v6.0.0-dev  ??-???-2019
* @copyright (c) 2019 Mark Newnham,Damien Regad and the ADOdb community
*
* Released under both BSD license and Lesser GPL library license. 
* You can choose which license you prefer.
*/

namespace ADOdb\drivers\PDO\firebird;

use ADOdb;

/**
* Class placeholder for autoloading
*
*/
class adoRecordSet extends ADOdb\drivers\PDO\adoRecordSet
{
	
	/**
	* getColumnMeta is not supported in the PDO\Firebird driver
	*
	* @param int	$fieldOffset
	*
	* @return obj
	*/
	final public function FetchField($fieldOffset = -1)	{
		
		$o = new ADOdb\common\ADOFieldObject();
		$o->name = 'bad getColumnMeta()';
		$o->max_length = -1;
		$o->type = 'VARCHAR';
		$o->precision = 0;
		return $o;
	}
}

