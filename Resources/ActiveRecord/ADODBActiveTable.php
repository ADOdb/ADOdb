<?php

namespace ADOdb\Resources\ActiveRecord;



class ADODBActiveTable
{
	var $name; // table name
	var $flds; // assoc array of adofieldobjs, indexed by fieldname
	var $keys; // assoc array of primary keys, indexed by fieldname
	var $_created; // only used when stored as a cached file
	var $_belongsTo = array();
	var $_hasMany = array();
}