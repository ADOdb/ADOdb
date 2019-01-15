<?php
namespace ADOdb;

/	/==============================================================================================
	// CLASS ADORecordSet_empty
	//==============================================================================================

	class ADODB_Iterator_empty implements Iterator {

		private $rs;

		function __construct($rs) {
			$this->rs = $rs;
		}

		function rewind() {}

		function valid() {
			return !$this->rs->EOF;
		}

		function key() {
			return false;
		}

		function current() {
			return false;
		}

		function next() {}

		function __call($func, $params) {
			return call_user_func_array(array($this->rs, $func), $params);
		}
	

		function hasMore() {
			return false;
		}

	}
