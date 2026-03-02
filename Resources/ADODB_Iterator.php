<?php

namespace ADOdb\Resources;

/**
 * Class ADODB_Iterator
 */
class ADODB_Iterator implements \Iterator {

    private $rs;

    function __construct($rs) {
        $this->rs = $rs;
    }

    #[\ReturnTypeWillChange]
    function rewind() {
        $this->rs->MoveFirst();
    }

    #[\ReturnTypeWillChange]
    function valid() {
        return !$this->rs->EOF;
    }

    #[\ReturnTypeWillChange]
    function key() {
        return $this->rs->_currentRow;
    }

    #[\ReturnTypeWillChange]
    function current() {
        return $this->rs->fields;
    }

    #[\ReturnTypeWillChange]
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