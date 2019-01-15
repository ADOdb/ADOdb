<?php
namespace ADOdb

//==============================================================================================
// CLASS ADORecordSet
//==============================================================================================

class ADODB_Iterator implements Iterator {

	private $rs;

	function __construct($rs) {
		$this->rs = $rs;
	}

	function rewind() {
		$this->rs->MoveFirst();
	}

	function valid() {
		return !$this->rs->EOF;
	}

	function key() {
		return $this->rs->_currentRow;
	}

	function current() {
		return $this->rs->fields;
	}

	function next() {
		$this->rs->MoveNext();
	}

	function __call($func, $params) {
		return call_user_func_array(array($this->rs, $func), $params);
	}

	function hasMore() {
		return !$this->rs->EOF;
	}

}