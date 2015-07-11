<?php
/** 
* This is the short description placeholder for the generic file docblock 
* 
* This is the long description placeholder for the generic file docblock
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @author     John Lim 
* @copyright  2014-      The ADODB project 
* @copyright  2000-2014 John Lim 
* @license    BSD License    (Primary) 
* @license    Lesser GPL License    (Secondary) 
* @version    5.21.0 
* @package    ADODB 
* @category   FIXME 
* 
* @adodb-filecheck-status: FIXME
* @adodb-driver-status: FIXME;
* @adodb-codesniffer-status: FIXME
* @adodb-documentor-status: FIXME
* 
*/ 
/// $Id $
///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// ADOdb  - Database Abstraction Library for PHP                         //
//          http://adodb.sourceforge.net/                                //
//                                                                       //
// Copyright (c) 2000-2014 John Lim (jlim\@natsoft.com.my)               //
//          All rights reserved.                                         //
//          Released under both BSD license and LGPL library license.    //
//          Whenever there is any discrepancy between the two licenses,  //
//          the BSD license will take precedence                         //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.com                                            //
//                                                                       //
// Copyright (C) 2001-3001 Martin Dougiamas        http://dougiamas.com  //
//           (C) 2001-3001 Eloy Lafuente (stronk7) http://contiento.com  //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////
/**
*  MSSQL Driver with auto-prepended "N" for correct unicode storage
*  of SQL literal strings. Intended to be used with MSSQL drivers that
*  are sending UCS-2 data to MSSQL (FreeTDS and ODBTP) in order to get
*  true cross-db compatibility from the application point of view.
*/
// security - hide paths
if (!defined('ADODB_DIR')) die();
// one useful constant
if (!defined('SINGLEQUOTE')) define('SINGLEQUOTE', "'");
include_once(ADODB_DIR.'/drivers/adodb-mssql.inc.php');

/** 
* This is the short description placeholder for the class docblock 
*  
* This is the long description placeholder for the class docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @version 5.21.0 
* 
* @adodb-class-status FIXME
*/
class ADODB_mssql_n extends ADODB_mssql {
	var $databaseType = "mssql_n";

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function ADODB_mssqlpo()
	{
		ADODB_mssql::ADODB_mssql();
	}

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function _query($sql,$inputarr=false)
	{
        $sql = $this->_appendN($sql);
		return ADODB_mssql::_query($sql,$inputarr);
	}
    /**
     * This function will intercept all the literals used in the SQL, prepending the "N" char to them
     * in order to allow mssql to store properly data sent in the correct UCS-2 encoding (by freeTDS
     * and ODBTP) keeping SQL compatibility at ADOdb level (instead of hacking every project to add
     * the "N" notation when working against MSSQL.
     *
     * Note that this hack only must be used if ALL the char-based columns in your DB are of type nchar,
     * nvarchar and ntext
     */

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function _appendN($sql) {
        $result = $sql;
    /// Check we have some single quote in the query. Exit ok.
        if (strpos($sql, SINGLEQUOTE) === false) {
            return $sql;
        }
    /// Check we haven't an odd number of single quotes (this can cause problems below
    /// and should be considered one wrong SQL). Exit with debug info.
        if ((substr_count($sql, SINGLEQUOTE) & 1)) {
            if ($this->debug) {
                ADOConnection::outp("{$this->databaseType} internal transformation: not converted. Wrong number of quotes (odd)");
            }
            return $sql;
        }
    /// Check we haven't any backslash + single quote combination. It should mean wrong
    /// backslashes use (bad magic_quotes_sybase?). Exit with debug info.
        $regexp = '/(\\\\' . SINGLEQUOTE . '[^' . SINGLEQUOTE . '])/';
        if (preg_match($regexp, $sql)) {
            if ($this->debug) {
                ADOConnection::outp("{$this->databaseType} internal transformation: not converted. Found bad use of backslash + single quote");
            }
            return $sql;
        }
    /// Remove pairs of single-quotes
        $pairs = array();
        $regexp = '/(' . SINGLEQUOTE . SINGLEQUOTE . ')/';
        preg_match_all($regexp, $result, $list_of_pairs);
        if ($list_of_pairs) {
            foreach (array_unique($list_of_pairs[0]) as $key=>$value) {
                $pairs['<@#@#@PAIR-'.$key.'@#@#@>'] = $value;
            }
            if (!empty($pairs)) {
                $result = str_replace($pairs, array_keys($pairs), $result);
            }
        }
    /// Remove the rest of literals present in the query
        $literals = array();
        $regexp = '/(N?' . SINGLEQUOTE . '.*?' . SINGLEQUOTE . ')/is';
        preg_match_all($regexp, $result, $list_of_literals);
        if ($list_of_literals) {
            foreach (array_unique($list_of_literals[0]) as $key=>$value) {
                $literals['<#@#@#LITERAL-'.$key.'#@#@#>'] = $value;
            }
            if (!empty($literals)) {
                $result = str_replace($literals, array_keys($literals), $result);
            }
        }
    /// Analyse literals to prepend the N char to them if their contents aren't numeric
        if (!empty($literals)) {
            foreach ($literals as $key=>$value) {
                if (!is_numeric(trim($value, SINGLEQUOTE))) {
                /// Non numeric string, prepend our dear N
                    $literals[$key] = 'N' . trim($value, 'N'); //Trimming potentially existing previous "N"
                }
            }
        }
    /// Re-apply literals to the text
        if (!empty($literals)) {
            $result = str_replace(array_keys($literals), $literals, $result);
        }
    /// Any pairs followed by N' must be switched to N' followed by those pairs
    /// (or strings beginning with single quotes will fail)
        $result = preg_replace("/((<@#@#@PAIR-(\d+)@#@#@>)+)N'/", "N'$1", $result);
    /// Re-apply pairs of single-quotes to the text
        if (!empty($pairs)) {
            $result = str_replace(array_keys($pairs), $pairs, $result);
        }
    /// Print transformation if debug = on
        if ($result != $sql && $this->debug) {
            ADOConnection::outp("{$this->databaseType} internal transformation:<br>{$sql}<br>to<br>{$result}");
        }
        return $result;
    }
}

/** 
* This is the short description placeholder for the class docblock 
*  
* This is the long description placeholder for the class docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @version 5.21.0 
* 
* @adodb-class-status FIXME
*/
class ADORecordset_mssql_n extends ADORecordset_mssql {
	var $databaseType = "mssql_n";

    /** 
    * This is the short description placeholder for the function docblock
    *  
    * This is the long description placeholder for the function docblock
    * Please see the ADOdb website for how to maintain adodb custom tags
    * 
    * @version 5.21.0 
    * @param   FIXME 
    * @return  FIXME 
    * 
    * @adodb-visibility  FIXME
    * @adodb-function-status FIXME
    * @adodb-api FIXME 
    */
    function ADORecordset_mssql_n($id,$mode=false)
	{
		$this->ADORecordset_mssql($id,$mode);
	}
}
