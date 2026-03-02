<?php

/**
 * Metafunctions loadable class
 *
 */

namespace ADOdb\Resources\SQLite;

require_once ADODB_DIR . '/Resources/MetaFunctions.php';

use ADOConnection;
use ADOfieldObject;

class MetaFunctions extends \ADOdb\Resources\MetaFunctions
{
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

        if (array_key_exists($t, $db->customActualTypes)) {
            return  $db->customActualTypes[$t];
        }

        /*
        * We are using the Sqlite affinity method here
        * @link https://www.sqlite.org/datatype3.html
        */
        $affinity = array(
        'INT' => 'INTEGER',
        'INTEGER' => 'INTEGER',
        'TINYINT' => 'INTEGER',
        'SMALLINT' => 'INTEGER',
        'MEDIUMINT' => 'INTEGER',
        'BIGINT' => 'INTEGER',
        'UNSIGNED BIG INT' => 'INTEGER',
        'INT2' => 'INTEGER',
        'INT8' => 'INTEGER',

        'CHARACTER' => 'TEXT',
        'VARCHAR' => 'TEXT',
        'VARYING CHARACTER' => 'TEXT',
        'NCHAR' => 'TEXT',
        'NATIVE CHARACTER' => 'TEXT',
        'NVARCHAR' => 'TEXT',
        'TEXT' => 'TEXT',
        'CLOB' => 'TEXT',

        'BLOB' => 'BLOB',

        'REAL' => 'REAL',
        'DOUBLE' => 'REAL',
        'DOUBLE PRECISION' => 'REAL',
        'FLOAT' => 'REAL',

        'NUMERIC' => 'NUMERIC',
        'DECIMAL' => 'NUMERIC',
        'BOOLEAN' => 'NUMERIC',
        'DATE' => 'NUMERIC',
        'DATETIME' => 'NUMERIC'
        );

        if (!isset($affinity[$t])) {
            return ADODB_DEFAULT_METATYPE;
        }

        $subt = $affinity[$t];
        /*
        * Now that we have subclassed the provided data down
        * the sqlite 'affinity', we convert to ADOdb metatype
        */

        $subclass = array('INTEGER' => 'I',
                          'TEXT' => 'X',
                          'BLOB' => 'B',
                          'REAL' => 'N',
                          'NUMERIC' => 'N');

        return $subclass[$subt];
    }

    public function actualType(object $db,  string $meta): string
    {
        $meta = strtoupper($meta);

        // Add support for custom meta types.
        // We do this first, that allows us to override existing types
        if (isset($db->customMetaTypes[$meta])) {
            return $db->customMetaTypes[$meta]['actual'];
        }

        switch (strtoupper($meta)) {
            case 'C':
            case 'C2':
                return 'VARCHAR'; //  TEXT , TEXT affinity
            case 'XL':
            case 'X2':
                return 'LONGTEXT'; //  TEXT , TEXT affinity
            case 'X':
                return 'TEXT'; //  TEXT , TEXT affinity

            case 'B':
                return 'LONGBLOB'; //  TEXT , NONE affinity , BLOB

            case 'D':
                return 'DATE'; // NUMERIC , NUMERIC affinity
            case 'T':
                return 'DATETIME'; // NUMERIC , NUMERIC affinity

            case 'I':
            case 'R':
            case 'I4':
                return 'INTEGER'; // NUMERIC , INTEGER affinity
            case 'L':
            case 'I1':
                return 'TINYINT'; // NUMERIC , INTEGER affinity
            case 'I2':
                return 'SMALLINT'; // NUMERIC , INTEGER affinity
            case 'I8':
                return 'BIGINT'; // NUMERIC , INTEGER affinity

            case 'F':
                return 'DOUBLE'; // NUMERIC , REAL affinity
            case 'N':
                return 'NUMERIC'; // NUMERIC , NUMERIC affinity

            default:
                return $meta;
        }
    }
}
