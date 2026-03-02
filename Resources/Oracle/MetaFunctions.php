<?php

/**
 * Metafunctions loadable class
 *
 */

namespace ADOdb\Resources\Oracle;

require_once ADODB_DIR . '/Resources/MetaFunctions.php';

use ADOConnection;
use ADOfieldObject;

class MetaFunctions extends \ADOdb\Resources\MetaFunctions
{
    public string $typeX = 'VARCHAR(4000)';
    public string $typeXL = 'CLOB';

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

        switch ($t) {
            case 'VARCHAR':
            case 'VARCHAR2':
            case 'CHAR':
            case 'VARBINARY':
            case 'BINARY':
                if (isset($this) && $len <= $this->blobSize) {
                    return 'C';
                }
                return 'X';

            case 'NCHAR':
            case 'NVARCHAR2':
            case 'NVARCHAR':
                if (isset($this) && $len <= $this->blobSize) {
                    return 'C2';
                }
                return 'X2';

            case 'NCLOB':
            case 'CLOB':
                return 'XL';

            case 'LONG RAW':
            case 'LONG VARBINARY':
            case 'BLOB':
                return 'B';

            case 'TIMESTAMP':
                return 'TS';

            case 'DATE':
                return 'T';

            case 'INT':
            case 'SMALLINT':
            case 'INTEGER':
                return 'I';

            case 'BOOLEAN':
                return 'L';

            default:
                return ADODB_DEFAULT_METATYPE;
        }
    }

    public function ActualType(object $db,  string $meta): string
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
            case 'X':
                return $this->typeX;
            case 'XL':
                return $this->typeXL;

            case 'C2':
                return 'NVARCHAR2';
            case 'X2':
                return 'NVARCHAR2(4000)';

            case 'B':
                return 'BLOB';

            case 'TS':
                return 'TIMESTAMP';

            case 'D':
            case 'T':
                return 'DATE';
            case 'L':
                return 'NUMBER(1)';
            case 'I1':
                return 'NUMBER(3)';
            case 'I2':
                return 'NUMBER(5)';
            case 'I':
            case 'I4':
                return 'NUMBER(10)';

            case 'I8':
                return 'NUMBER(20)';
            case 'F':
                return 'NUMBER';
            case 'N':
                return 'NUMBER';
            case 'R':
                return 'NUMBER(20)';
            default:
                return $meta;
        }
    }
}
