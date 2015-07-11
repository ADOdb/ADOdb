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
/*
V5.20dev  ??-???-2014  (c) 2000-2014 John Lim (jlim#natsoft.com). All rights reserved.
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.
  Portable version of sqlite driver, to make it more similar to other database drivers.
  The main differences are
   1. When selecting (joining) multiple tables, in assoc mode the table
   	  names are included in the assoc keys in the "sqlite" driver.
	  In "sqlitepo" driver, the table names are stripped from the returned column names.
	  When this results in a conflict,  the first field get preference.
	Contributed by Herman Kuiper  herman#ozuzo.net
*/
if (!defined('ADODB_DIR')) die();
include_once(ADODB_DIR.'/drivers/adodb-sqlite.inc.php');

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
class ADODB_sqlitepo extends ADODB_sqlite {
   var $databaseType = 'sqlitepo';
   function ADODB_sqlitepo()
   {
      $this->ADODB_sqlite();
   }
}
/*--------------------------------------------------------------------------------------
       Class Name: Recordset
--------------------------------------------------------------------------------------*/

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
class ADORecordset_sqlitepo extends ADORecordset_sqlite {
   var $databaseType = 'sqlitepo';
   function ADORecordset_sqlitepo($queryID,$mode=false)
   {
      $this->ADORecordset_sqlite($queryID,$mode);
   }
   // Modified to strip table names from returned fields
   function _fetch($ignore_fields=false)
   {
      $this->fields = array();
      $fields = @sqlite_fetch_array($this->_queryID,$this->fetchMode);
      if(is_array($fields))
         foreach($fields as $n => $v)
         {
            if(($p = strpos($n, ".")) !== false)
               $n = substr($n, $p+1);
            $this->fields[$n] = $v;
         }
      return !empty($this->fields);
   }
}
