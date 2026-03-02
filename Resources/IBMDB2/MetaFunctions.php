<?php

/**
 * Metafunctions loadable class
 *
 */

namespace ADOdb\Resources\IBMDB2;

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

        return parent::metaType($db, $fieldObj);
    }

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
                return 'CLOB';
            case 'X':
                return 'VARCHAR(3600)';

            case 'C2':
                return 'VARCHAR'; // up to 32K
            case 'X2':
                return 'VARCHAR(3600)'; // up to 32000, but default page size too small

            case 'B':
                return 'BLOB';

            case 'D':
                return 'DATE';
            case 'TS':
            case 'T':
                return 'TIMESTAMP';

            case 'L':
                return 'SMALLINT';
            case 'I':
                return 'INTEGER';
            case 'I1':
                return 'SMALLINT';
            case 'I2':
                return 'SMALLINT';
            case 'I4':
                return 'INTEGER';
            case 'I8':
                return 'BIGINT';

            case 'F':
                return 'DOUBLE';
            case 'N':
                return 'DECIMAL';
            default:
                return $meta;
        }
    }
}
