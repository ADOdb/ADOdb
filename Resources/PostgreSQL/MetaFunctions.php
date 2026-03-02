<?php

/**
 * Metafunctions loadable class
 *
 */

namespace ADOdb\Resources\PostgreSQL;

require_once ADODB_DIR . '/Resources/MetaFunctions.php';

use ADOConnection;
use ADOfieldObject;

class MetaFunctions extends \ADOdb\Resources\MetaFunctions
{
    public int $blobSize = 2000;
    /**
     * Get the ADOdb metatype.
     *
     * Many databases use different names for the same type, so we transform
     * the native type to our standardised one, which uses 1 character codes.
     * @see https://adodb.org/dokuwiki/doku.php?id=v5:dictionary:dictionary_index#portable_data_types
     *
     * @param ADOConnection  $db       database connection
     * @param ADOfieldObject $fieldObj Field object returned by the database driver
     *
     * @return string The ADOdb Standard type
     */
    public function metaType(
        object $db, 
        ADOfieldObject $fieldObj
    ): string {

        $t = strtoupper($fieldObj->type);
        $len = $fieldObj->max_length;

        if (array_key_exists($t, $db->customActualTypes)) {
            return  $db->customActualTypes[$t];
        }

        $is_serial = is_object($fieldObj)
                  && !empty($fieldObj->primary_key)
                  && !empty($fieldObj->unique)
                  && !empty($fieldObj->has_default)
                  && substr($fieldObj->default_value, 0, 8) == 'nextval(';

        switch ($t) {
            case 'INTERVAL':
            case 'CHAR':
            case 'CHARACTER':
            case 'VARCHAR':
            case 'NAME':
            case 'BPCHAR':
                if ($len <= $this->blobSize) {
                    return 'C';
                }

            case 'TEXT':
                return 'X';

            case 'IMAGE': // user defined type
            case 'BLOB': // user defined type
            case 'BIT': // This is a bit string, not a single bit, so don't return 'L'
            case 'VARBIT':
            case 'BYTEA':
                return 'B';

            case 'BOOL':
            case 'BOOLEAN':
                return 'L';

            case 'DATE':
                return 'D';

            case 'TIME':
            case 'DATETIME':
            case 'TIMESTAMP':
            case 'TIMESTAMPTZ':
                return 'T';

            case 'INTEGER':
                return !$is_serial ? 'I' : 'R';
            case 'SMALLINT':
            case 'INT2':
                return !$is_serial ? 'I2' : 'R';
            case 'INT4':
                return !$is_serial ? 'I4' : 'R';
            case 'BIGINT':
            case 'INT8':
                return !$is_serial ? 'I8' : 'R';

            case 'OID':
            case 'SERIAL':
                return 'R';

            case 'FLOAT4':
            case 'FLOAT8':
            case 'DOUBLE PRECISION':
            case 'REAL':
                return 'F';

            default:
                return ADODB_DEFAULT_METATYPE;
        }
    }

    /**
     * Returns the actual type for a given meta type.
     *
     * @param ADOConnection $db   The database connection
     * @param string        $meta The meta type to convert:
     *
     * @return string The actual type corresponding to the meta type.
     */
    public function actualType(object $db,  string $meta): string
    {
        $meta = strtoupper($meta);

        /*
        * Add support for custom meta types. We do this
        * first, that allows us to override existing types
        */
        if (isset($db->customMetaTypes[$meta])) {
            return $db->customMetaTypes[$meta]['actual'];
        }

        switch ($meta) {
            case 'C':
                return 'VARCHAR';
            case 'XL':
            case 'X':
                return 'TEXT';

            case 'C2':
                return 'VARCHAR';
            case 'X2':
                return 'TEXT';

            case 'B':
                return 'BYTEA';

            case 'D':
                return 'DATE';
            case 'TS':
            case 'T':
                return 'TIMESTAMP';

            case 'L':
                return 'BOOLEAN';
            case 'I':
                return 'INTEGER';
            case 'I1':
                return 'SMALLINT';
            case 'I2':
                return 'INT2';
            case 'I4':
                return 'INT4';
            case 'I8':
                return 'INT8';

            case 'F':
                return 'FLOAT8';
            case 'N':
                return 'NUMERIC';
            default:
                return $meta;
        }
    }
}
