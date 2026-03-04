<?php

namespace ADOdb\Resources;


/**
 * Class ADODB_Iterator_empty
 */
class ADODBIteratorEmpty implements \Iterator {

    private $rs;

    function __construct($rs) {
        $this->rs = $rs;
    }

    #[\ReturnTypeWillChange]
    function rewind() {}

    #[\ReturnTypeWillChange]
    function valid() {
        return !$this->rs->EOF;
    }

    #[\ReturnTypeWillChange]
    function key() {
        return false;
    }

    #[\ReturnTypeWillChange]
    function current() {
        return false;
    }

    #[\ReturnTypeWillChange]
    function next() {}

    function __call($func, $params) {
        return call_user_func_array(array($this->rs, $func), $params);
    }

    #[\ReturnTypeWillChange]
    function hasMore() {
        return false;
    }

}
