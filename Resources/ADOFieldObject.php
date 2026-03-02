<?php
//==============================================================================================
// CLASS ADOFieldObject
//==============================================================================================

namespace ADOdb\Resources;

/**
 * Helper class for FetchFields -- holds info on a column.
 *
 * Note: Dynamic properties are required here, as some drivers may require
 * the object to hold database-specific field metadata.
 */
#[\AllowDynamicProperties]
class ADOFieldObject {
    /**
     * @var string Field name
     */
    public $name = '';

    /**
     * @var string Field type.
     */
    public $type = '';

    /**
     * @var int Field size
     */
    public $max_length = 0;

    /**
     * @var int|null Numeric field scale.
     */
    public $scale;

    /**
     * @var bool True if field can be NULL
     */
    public $not_null = false;

    /**
     * @var bool True if field is a primary key
     */
    public $primary_key = false;

    /**
     * @var bool True if field is unique key
     */
    public $unique = false;

    /**
     * @var bool True if field is automatically incremented
     */
    public $auto_increment = false;

    /**
     * @var bool True if field has a default value
     */
    public $has_default = false;

    /**
     * @var mixed Default value, if any and supported; check {@see $has_default} first.
     */
    public $default_value;
}
