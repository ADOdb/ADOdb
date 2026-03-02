<?php
/**
 * RecordSet fields data as object.
 *
 * @see ADORecordSet::fetchObj(), ADORecordSet::fetchObject(),
 * @see ADORecordSet::fetchNextObj(), ADORecordSet::fetchNextObject()
 */
class ADOFetchObj {
    /** @var array The RecordSet's fields */
    protected $data;

    /**
     * Constructor.
     *
     * @param array $fields Associative array with RecordSet's fields (name => value)
     */
    public function __construct(array $fields = [])
    {
        $this->data = $fields;
    }

    public function __set(string $name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __get(string $name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
        ADOConnection::outp("Unknown field: $name");
        return null;
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    public function __debugInfo()
    {
        return $this->data;
    }

    public static function __set_state(array $data)
    {
        return new self($data['data']);
    }
}
