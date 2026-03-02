<?php
namespace ADOdb\Resources;

use ADOdb\Resources\ADODB_Iterator_empty;

/**
 * Lightweight recordset when there are no records to be returned
 */
class ADORecordSetEmpty implements \IteratorAggregate
{
    var $dataProvider = 'empty';
    var $databaseType = false;
    var $EOF = true;
    var $_numOfRows = 0;
    /** @var bool|array  */
    var $fields = false;
    var $connection = false;

    /**
     * The timestamp that the recordset was created
     *
     * @var integer
     */
    public int $timeCreated = 0;

    function RowCount() {
        return 0;
    }

    function RecordCount() {
        return 0;
    }

    function PO_RecordCount() {
        return 0;
    }

    function Close() {
        return true;
    }

    function FetchRow() {
        return false;
    }

    function FieldCount() {
        return 0;
    }

    function Init() {}

    #[\ReturnTypeWillChange]
    function getIterator() {
        return new ADODB_Iterator_empty($this);
    }

    function GetAssoc() {
        return array();
    }

    function GetArray() {
        return array();
    }

    function GetAll() {
        return array();
    }

    function GetArrayLimit() {
        return array();
    }

    function GetRows() {
        return array();
    }

    function GetRowAssoc() {
        return array();
    }

    function MaxRecordCount() {
        return 0;
    }

    function NumRows() {
        return 0;
    }

    function NumCols() {
        return 0;
    }
}