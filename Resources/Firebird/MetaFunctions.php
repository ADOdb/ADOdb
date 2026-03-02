<?php

/**
 * Metafunctions loadable class
 *
 */

namespace ADOdb\Resources\Firebird;

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

		switch ($t) {
			case 'CHAR':
				return 'C';

			case 'TEXT':
			case 'VARCHAR':
			case 'VARYING':
				if ($len <= $this->blobSize) {
					return 'C';
				}
				return 'X';
			case 'BLOB':
				return 'B';

			case 'TIMESTAMP':
			case 'DATE':
				return 'D';
			case 'TIME':
				return 'T';
			//case 'T': return 'T';

			//case 'L': return 'L';
			case 'INT':
			case 'SHORT':
			case 'INTEGER':
				return 'I';
			default:
				return ADODB_DEFAULT_METATYPE;
		}
    }

    public function actualType(object $db,  string $meta): string
    {
        $meta = strtoupper($meta);

        if (isset($db->customMetaTypes[$meta])) {
            return $db->customMetaTypes[$meta]['actual'];
        }

		switch($meta) {
			case 'C':
				return 'VARCHAR';
			case 'XL':
				return 'BLOB SUB_TYPE BINARY';
			case 'X':
				return 'BLOB SUB_TYPE TEXT';

			case 'C2':
				return 'VARCHAR(32765)'; // up to 32K
			case 'X2':
				return 'VARCHAR(4096)';

			case 'V':
				return 'CHAR';
			case 'C1':
				return 'CHAR(1)';

			case 'B':
				return 'BLOB';

			case 'D':
				return 'DATE';
			case 'TS':
			case 'T':
				return 'TIMESTAMP';

			case 'L':
			case 'I1':
			case 'I2':
				return 'SMALLINT';
			case 'I':
			case 'I4':
				return 'INTEGER';
			case 'I8':
				return 'BIGINT';

			case 'F':
				return 'DOUBLE PRECISION';
			case 'N':
				return 'DECIMAL';
			default:
				return $meta;
		}
	}

        
}
