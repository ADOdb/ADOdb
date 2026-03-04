<?php

namespace ADOdb\Resources\ActiveRecord;

class ADODBActiveDB {
	/** @var ADOConnection */
	var $db;

	/**
	 * assoc array of ADODB_Active_Table objects, indexed by tablename
	 * @var ADODB_Active_Table[]
	 */
	var $tables;
}