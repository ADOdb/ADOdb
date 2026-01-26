<?php

/**
 * Data Dictionary for SQLite.
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @package ADOdb
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v2.1 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-2.1-or-later
 *
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
 */

// security - hide paths
if (!defined('ADODB_DIR')) {
    die();
}

class ADODB2_sqlite extends ADODB_DataDict
{
    /**
     * The database type
     *
     * @var string
     */
    public $databaseType = 'sqlite';

    /**
     * The string prefix to add a column
     *
     * @var string
     */
    public $addCol = ' ADD COLUMN';

    /**
     * The string to drop a table if allowed
     *
     * @var string
     */
    public $dropTable = 'DROP TABLE IF EXISTS %s';

    /**
     * The string to drop an index if allowed
     *
     * @var string
     */
    public $dropIndex = 'DROP INDEX IF EXISTS %s';

    /**
     * The string to rename a table if allowed
     *
     * @var string
     */
    public $renameTable = 'ALTER TABLE %s RENAME TO %s';

    /**
     * Can a blob be set with a default value
     *
     * @var boolean
     */
    public $blobAllowsDefaultValue = true;

    /**
     * Can a blob have a NOT NULL setting
     *
     * @var boolean
     */
    public $blobAllowsNotNull      = true;

    /**
     * Returns the actual type for a given meta type.
     *
     * @param string $meta The meta type to convert:
     *
     * @return string The actual type corresponding to the meta type.
     */
    public function actualType($meta)
    {
        $meta = strtoupper($meta);

        // Add support for custom meta types.
        // We do this first, that allows us to override existing types
        if (isset($this->connection->customMetaTypes[$meta])) {
            return $this->connection->customMetaTypes[$meta]['actual'];
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

    /**
     * Construct a database specific SQL string of constraints for column.
     *
     * @param string $fname         Column name.
     * @param string & $ftype       Column type.
     * @param bool   $fnotnull      Whether the column is NOT NULL.
     * @param string|bool $fdefault The column's default value.
     * @param bool   $fautoinc      Whether the column is auto-incrementing.
     * @param string $fconstraint   Any additional constraints for the column.
     * @param bool   $funsigned     Whether the column is unsigned.
     * @param string|bool $fprimary Whether the column is a primary key.
     * @param array  & $pkey        The primary key definition (list of column names), if applicable.
     *
     * @return string Combined constraint string, must start with a space.
     */
    public function _createSuffix($fname, &$ftype, $fnotnull, $fdefault, $fautoinc, $fconstraint, $funsigned, $fprimary, &$pkey)
    {
        $suffix = '';
        if ($funsigned && !($fprimary && $fautoinc)) {
            $suffix .= ' UNSIGNED';
        }
        if ($fnotnull) {
            $suffix .= ' NOT NULL';
        }
        if (strlen($fdefault)) {
            $suffix .= " DEFAULT $fdefault";
        }
        if ($fprimary && $fautoinc) {
            $suffix .= ' PRIMARY KEY AUTOINCREMENT';
            array_pop($pkey);
        }
        if ($fconstraint) {
            $suffix .= ' ' . $fconstraint;
        }
        return $suffix;
    }

    /**
     * Change the definition of one column
     *
     * As some DBMs can't do that on their own, you need to supply the complete definition of the new table,
     * to allow recreating the table and copying the content over to the new table
     *
     * @param string       $tabname table-name
     * @param array|string $flds column-name and type for the changed column
     * @param string       $tableflds='' complete definition of the new table, eg. for postgres, default ''
     * @param array|string $tableoptions='' options for the new table see createTableSQL, default ''
     *
     * @return array with SQL strings
     */
    public function alterColumnSQL($tabname, $flds, $tableflds = '', $tableoptions = '')
    {
        if ($this->debug) {
            ADOConnection::outp("AlterColumnSQL not supported natively by SQLite");
        }
        return array();
    }

    /**
     * Drop one column.
     *
     * @param string       $tabname      Table name.
     * @param string       $flds         Column name and type for the changed column.
     * @param string       $tableflds    Complete definition of the new table. Defaults to ''.
     * @param array|string $tableoptions Options for the new table {@see createTableSQL()},
     *                                   defaults to ''.
     *
     * @return array SQL statements.
     */
    public function dropColumnSQL($tabname, $flds, $tableflds = '', $tableoptions = '')
    {
        if (SQLite3::version()['versionNumber'] < 3035000) {
            if ($this->debug) {
                ADOConnection::outp("DropColumnSQL is only supported since SQLite 3.35.0");
            }
            return array();
        }
        return parent::dropColumnSQL($tabname, $flds, $tableflds, $tableoptions);
    }

    /**
     * Rename one column.
     *
     * @param string $tabname   Table name.
     * @param string $oldcolumn Column to be renamed.
     * @param string $newcolumn New column name.
     * @param string $flds      unused
     *
     * @return array SQL statements.
     */
    public function renameColumnSQL($tabname, $oldcolumn, $newcolumn, $flds = '')
    {
        if (SQLite3::version()['versionNumber'] < 3025000) {
            if ($this->debug) {
                ADOConnection::outp("renameColumnSQL is only supported since SQLite 3.25.0");
            }
            return array();
        }
        return parent::renameColumnSQL($tabname, $oldcolumn, $newcolumn, $flds);
    }
}
