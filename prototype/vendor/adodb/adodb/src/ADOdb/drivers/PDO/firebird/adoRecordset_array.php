<?php
namespace ADOdb\drivers\PDO\firebird;

use ADOdb;
class adoRecordSet_array extends ADOdb\adoRecordSet_array
{

	public $databaseType = "pdo_firebird";
	public $canSeek = true;


	/**
	 * returns the field object
	 *
	 * @param  int $fieldOffset Optional field offset
	 *
	 * @return object The ADOFieldObject describing the field
	 */
	public function fetchField($fieldOffset = 0)
	{
		
		$fld = new ADOFieldObject;
		$fld->name = $fieldOffset;
		$fld->type = 'C';
		$fld->max_length = 0;

		/*       This needs to be populated from the metadata */
		$fld->not_null = false;
		$fld->has_default = false;
		$fld->default_value = 'null';
		return $fld;
	}
}