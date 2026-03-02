<?php

/**
 * Metafunctions loadable class
 *
 */

namespace ADOdb\Resources\SqlServer;

require_once ADODB_DIR . '/Resources/MetaFunctions.php';

use ADOConnection;
use ADOfieldObject;

class MetaFunctions extends \ADOdb\Resources\MetaFunctions
{
    public string $typeX = 'TEXT';  ## Alternatively, set it to VARCHAR(4000)
    public string $typeXL = 'TEXT';
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

        $t      = strtoupper($fieldObj->type);
        $length = $fieldObj->max_length;

        if (array_key_exists($t, $db->customActualTypes)) {
            return $db->customActualTypes[$t];
        }

        /*
        * Since the introduction of the requirement of the ODBC driver,
        * types are now string values. The numeric value is in the
        * xtype field. Older versions of the native client still send
        * numbers
        */

        if ($t == 'VARCHAR' && $length == -1) {
            /*
            * varchar(max)
            */
            $t = 'CLOB';
        } elseif ($t == 'NVARCHAR' && $length == -1) {
            /*
            * nvarchar(max)
            */
            $t = 'NCLOB';
        } elseif ($t == 'VARBINARY' && $length == -1) {
            /*
            * varbinary(max)
            */
            $t = 'IMAGE';
        }

        switch ($t) {
            case 'VARCHAR':
                return 'C';

            case 'NVARCHAR':
                return 'C2';

            case 'CLOB':
                return 'X';

            case 'NCLOB':
                return 'X2';

            case 'BINARY':
            case 'VARBINARY':
                return 'B';

            case 'TEXT':
            case 'IMAGE':
                return 'XL';

            case 'DATE':
                return 'D';

            case 'TIME':
            case 'DATETIME':
            case 'DATETIME2':
            case 'SMALLDATETIME':
            case 'DATETIMEOFFSET':
                return 'T';

            case 'NUMERIC':
            case 'DECIMAL':
            case 'MONEY':
            case 'SMALLMONEY':
                return 'N';

            case 'REAL':
                return 'R';

            case 'BIT':
                return 'L';

            case 'SMALLINT':
                return 'I2';

            case 'INT':
            case 'INTEGER':
                return 'I4';

            case 'BIGINT':
                return 'I';

            default:
                print "FAIL\n";
                return ADODB_DEFAULT_METATYPE;
        }
    }

    public function ActualType(object $db,  string $meta): string
    {
        $DATE_TYPE = 'DATE';
        $meta = strtoupper($meta);

        /*
        * Add support for custom meta types. We do this
        * first, that allows us to override existing types
        */
        if (isset($db->customMetaTypes[$meta])) {
            return $db->customMetaTypes[$meta]['actual'];
        }

        switch (strtoupper($meta)) {
            case 'C':
                return 'VARCHAR';
            case 'XL':
                return (isset($this)) ? $this->typeXL : 'TEXT';
            case 'X':
                return (isset($this)) ? $this->typeX : 'TEXT'; ## could be varchar(8000), but we want compat with oracle
            case 'C2':
                return 'NVARCHAR';
            case 'X2':
                return 'NTEXT';

            case 'B':
                return 'IMAGE';

            case 'D':
                return $DATE_TYPE;
            case 'T':
                return 'TIME';
            case 'L':
                return 'BIT';

            case 'R':
            case 'I':
                return 'INT';
            case 'I1':
                return 'TINYINT';
            case 'I2':
                return 'SMALLINT';
            case 'I4':
                return 'INT';
            case 'I8':
                return 'BIGINT';

            case 'F':
                return 'REAL';
            case 'N':
                return 'NUMERIC';
            default:
                return $meta;
        }
    }
}
