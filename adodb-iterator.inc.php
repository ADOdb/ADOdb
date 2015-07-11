<?php
/** 
* This is the short description placeholder for the generic file docblock 
* 
* This is the long description placeholder for the generic file docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @category   FIXME
* @package    ADODB 
* @author     John Lim 
* @copyright  2014-      The ADODB project 
* @copyright  2000-2014 John Lim 
* @license    BSD License    (Primary) 
* @license    Lesser GPL License    (Secondary) 
* @version    5.21.0 
* 
* @adodb-filecheck-status: FIXME
* @adodb-codesniffer-status: FIXME
* @adodb-documentor-status: FIXME
* 
*/ 
/*
  V5.20dev  ??-???-2014  (c) 2000-2014 John Lim (jlim#natsoft.com). All rights reserved.
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.
  Set tabs to 4.
  Declares the ADODB Base Class for PHP5 "ADODB_BASE_RS", and supports iteration with
  the ADODB_Iterator class.
  		$rs = $db->Execute("select * from adoxyz");
		foreach($rs as $k => $v) {
			echo $k; print_r($v); echo "<br>";
		}
	Iterator code based on http://cvs.php.net/cvs.php/php-src/ext/spl/examples/cachingiterator.inc?login=2
	Moved to adodb.inc.php to improve performance.
 */
