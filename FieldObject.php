<?php

namespace ADOdb;

//==============================================================================================
// CLASS ADOdb\FieldObject
//==============================================================================================
/**
 * Helper class for FetchFields -- holds info on a column
 */
class FieldObject {
	var $name = '';
	var $max_length=0;
	var $type="";
/*
	// additional fields by dannym... (danny_milo@yahoo.com)
	var $not_null = false;
	// actually, this has already been built-in in the postgres, fbsql AND mysql module? ^-^
	// so we can as well make not_null standard (leaving it at "false" does not harm anyways)

	var $has_default = false; // this one I have done only in mysql and postgres for now ...
		// others to come (dannym)
	var $default_value; // default, if any, and supported. Check has_default first.
*/
}

