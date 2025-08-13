<?php
/**
 * Data Dictionary for MySQL.
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
if (!defined('ADODB_DIR')) die();

class ADODB2_mysql extends ADODB_DataDict {
    var $databaseType = 'mysql';
    var $alterCol = ' MODIFY COLUMN';
    var $alterTableAddIndex = true;
    var $dropTable = 'DROP TABLE IF EXISTS %s'; // requires mysql 3.22 or later

    var $dropIndex = 'DROP INDEX %s ON %s';

    /**
     * MySQL 8.0+ supports RENAME COLUMN, but earlier versions do not.
     *
     * @var string
     */
    public $renameColumn = 'ALTER TABLE %s RENAME COLUMN %s TO %s';

    public $blobAllowsNotNull = true;
    
    /**
     * Returns the meta type for a given type and length.
     *
     * @param mixed   $t        Theobject to test
     * @param integer $len      The length of the field, if applicable.
     * @param object  $fieldobj The field object, if available.
     * 
     * @return void
     */
    function metaType($t,$len=-1,$fieldobj=false)
    {
        
        if (is_object($t)) {
            $fieldobj = $t;
            $t = $fieldobj->type;
            $len = $fieldobj->max_length;
        }
        $is_serial = is_object($fieldobj) && $fieldobj->primary_key && $fieldobj->auto_increment;

        $len = -1; // mysql max_length is not accurate
            
        $t = strtoupper($t);
        
        if (array_key_exists($t, $this->connection->customActualTypes))
            return  $this->connection->customActualTypes[$t];
        
        switch ($t) {
            
        case 'STRING':
        case 'CHAR':
        case 'VARCHAR':
        case 'TINYBLOB':
        case 'TINYTEXT':
        case 'ENUM':
        case 'SET':
            if ($len <= $this->blobSize) return 'C';

        case 'TEXT':
        case 'LONGTEXT':
        case 'MEDIUMTEXT':
            return 'X';

        // php_mysql extension always returns 'blob' even if 'text'
        // so we have to check whether binary...
        case 'IMAGE':
        case 'LONGBLOB':
        case 'BLOB':
        case 'MEDIUMBLOB':
            return !empty($fieldobj->binary) ? 'B' : 'X';

        case 'YEAR':
        case 'DATE': 
            return 'D';

        case 'TIME':
        case 'DATETIME':
        case 'TIMESTAMP': 
            return 'T';

        case 'FLOAT':
        case 'DOUBLE':
            return 'F';

        case 'INT':
        case 'INTEGER': 
            return $is_serial ? 'R' : 'I';
        case 'TINYINT': 
            return $is_serial ? 'R' : 'I1';
        case 'SMALLINT': 
            return $is_serial ? 'R' : 'I2';
        case 'MEDIUMINT': 
            return $is_serial ? 'R' : 'I4';
        case 'BIGINT':  
            return $is_serial ? 'R' : 'I8';
        default: 
            
            return ADODB_DEFAULT_METATYPE;
        }
    }

    /**
     * Returns the actual type for a given meta type.
     *
     * @param string $meta The meta type to convert.
     *
     * @return string The actual type corresponding to the meta type.
    */
    function actualType($meta)
    {
        
        $meta = strtoupper($meta);
        
        /*
        * Add support for custom meta types. We do this
        * first, that allows us to override existing types
        */
        if (isset($this->connection->customMetaTypes[$meta]))
            return $this->connection->customMetaTypes[$meta]['actual'];
                
        switch($meta) 
        {
        
        case 'C': 
            return 'VARCHAR';
        case 'XL':
            return 'LONGTEXT';
        case 'X': 
            return 'TEXT';

        case 'C2': 
            return 'VARCHAR';
        case 'X2': 
            return 'LONGTEXT';

        case 'B': 
            return 'LONGBLOB';

        case 'D': 
            return 'DATE';
        case 'TS':
        case 'T': 
            return 'DATETIME';
        case 'L': 
            return 'TINYINT';

        case 'R':
        case 'I4':
        case 'I':
            return 'INTEGER';
        case 'I1': 
            return 'TINYINT';
        case 'I2': 
            return 'SMALLINT';
        case 'I8': 
            return 'BIGINT';

        case 'F': 
            return 'DOUBLE';
        case 'N': 
            return 'NUMERIC';
            
        default:
            
            return $meta;
        }
    }

    /**
     * Creates the suffix for a column definition.
     *
     * @param string $fname       The field name.
     * @param string $ftype       The field type.
     * @param bool   $fnotnull    Whether the field is NOT NULL.
     * @param string $fdefault    The default value for the field.
     * @param bool   $fautoinc    Whether the field is auto-incrementing.
     * @param string $fconstraint Any additional constraints for the field.
     * @param bool   $funsigned   Whether the field is unsigned.
     * @param bool   $fprimary    Whether the field is a primary key.
     * @param string $pkey        The primary key definition, if applicable.
     *
     * @return string The suffix for the column definition.
     */
    function _createSuffix($fname, &$ftype, $fnotnull, $fdefault, $fautoinc, $fconstraint, $funsigned, $fprimary, &$pkey)
    {
        $suffix = '';
        if ($funsigned) $suffix .= ' UNSIGNED';
        if ($fnotnull) $suffix .= ' NOT NULL';
        if (strlen($fdefault)) $suffix .= " DEFAULT $fdefault";
        if ($fautoinc) $suffix .= ' AUTO_INCREMENT';
        if ($fconstraint) $suffix .= ' '.$fconstraint;
        return $suffix;
    }

    /*
    CREATE [TEMPORARY] TABLE [IF NOT EXISTS] tbl_name [(create_definition,...)]
        [table_options] [select_statement]
        create_definition:
        col_name type [NOT NULL | NULL] [DEFAULT default_value] [AUTO_INCREMENT]
        [PRIMARY KEY] [reference_definition]
        or PRIMARY KEY (index_col_name,...)
        or KEY [index_name] (index_col_name,...)
        or INDEX [index_name] (index_col_name,...)
        or UNIQUE [INDEX] [index_name] (index_col_name,...)
        or FULLTEXT [INDEX] [index_name] (index_col_name,...)
        or [CONSTRAINT symbol] FOREIGN KEY [index_name] (index_col_name,...)
        [reference_definition]
        or CHECK (expr)
    */

    /*
    CREATE [UNIQUE|FULLTEXT] INDEX index_name
        ON tbl_name (col_name[(length)],... )
    */

    /**
     * Creates the SQL statements to create or replace an index.
     *
     * @param string $idxname    The name of the index.
     * @param string $tabname    The name of the table.
     * @param mixed  $flds       The fields for the index, as a string or array.
     * @param array  $idxoptions Options for the index, such as UNIQUE, FULLTEXT, etc.
     *
     * @return array SQL statements to create or replace the index.
     */
    function _indexSQL($idxname, $tabname, $flds, $idxoptions)
    {
        $sql = array();

        if ( isset($idxoptions['REPLACE']) || isset($idxoptions['DROP']) ) {
            if ($this->alterTableAddIndex) $sql[] = "ALTER TABLE $tabname DROP INDEX $idxname";
            else $sql[] = sprintf($this->dropIndex, $idxname, $tabname);

            if ( isset($idxoptions['DROP']) )
                return $sql;
        }

        if (empty($flds) ) {
            return $sql;
        }

        if (isset($idxoptions['FULLTEXT'])) {
            $unique = ' FULLTEXT';
        } elseif (isset($idxoptions['UNIQUE'])) {
            $unique = ' UNIQUE';
        } else {
            $unique = '';
        }

        if ( is_array($flds) ) $flds = implode(', ',$flds);

        if ($this->alterTableAddIndex) $s = "ALTER TABLE $tabname ADD $unique INDEX $idxname ";
        else $s = 'CREATE' . $unique . ' INDEX ' . $idxname . ' ON ' . $tabname;

        $s .= ' (' . $flds . ')';

        if (isset($idxoptions[$this->upperName]) )
            $s .= $idxoptions[$this->upperName];

        $sql[] = $s;

        return $sql;
    }

    /**
     * Rename one column.
     *
     * Some DBs can only do this together with changing the type of the column
     * (even if that stays the same, eg. MySQL).
     *
     * @param string $tabname   Table name.
     * @param string $oldcolumn Column to be renamed.
     * @param string $newcolumn New column name.
     * @param string $flds      Complete column definition string like for {@see addColumnSQL};
     *                          This is currently only used by MySQL. Defaults to ''.
     *
     * @return array SQL statements.
     */
    function renameColumnSQL($tabname, $oldcolumn, $newcolumn, $flds='')
    {

        $version = $this->connection->ServerInfo();
        $vSplit = explode('.', $version['version']);
    
        if ($vSplit[0] < 8) {
            $this->renameColumn = 'ALTER TABLE %s CHANGE COLUMN %s %s %s';
        } else {
            $flds = '';
        }

        return ADODB_DataDict::renameColumnSQL($tabname, $oldcolumn, $newcolumn, $flds);
    }
}
