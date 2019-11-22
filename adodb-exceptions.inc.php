<?php

/**
 * @version   v5.21.0-dev  ??-???-2016
 * @copyright (c) 2000-2013 John Lim (jlim#natsoft.com). All rights reserved.
 * @copyright (c) 2014      Damien Regad, Mark Newnham and the ADOdb community
 * Released under both BSD license and Lesser GPL library license.
 * Whenever there is any discrepancy between the two licenses,
 * the BSD license will take precedence.
 *
 * Set tabs to 4 for best viewing.
 *
 * Latest version is available at http://adodb.org/
 *
 * Exception-handling code using PHP5 exceptions (try-catch-throw).
 */

require_once('vendor/autoload.php');

use ADODB\Exception;

if (!defined('ADODB_ERROR_HANDLER_TYPE')) define('ADODB_ERROR_HANDLER_TYPE',E_USER_ERROR);
define('ADODB_ERROR_HANDLER','adodb_throw');

class ADODB_Exception extends Exception
{
}

/**
* Default Error Handler. This will be called with the following params
*
* @param $dbms				the RDBMS you are connecting to
* @param $fn				the name of the calling function (in uppercase)
* @param $errno				the native error number from the database
* @param $errmsg			the native error msg from the database
* @param $p1				$fn specific parameter - see below
* @param $p2				$fn specific parameter - see below
* @param $thisConnection	the current connection
*/
function adodb_throw($dbms, $fn, $errno, $errmsg, $p1, $p2, $thisConnection)
{
    global $ADODB_EXCEPTION;

	if (error_reporting() == 0) return; // obey @ protocol
	if (is_string($ADODB_EXCEPTION)) $errfn = $ADODB_EXCEPTION;
	else $errfn = 'ADODB_EXCEPTION';
	throw new $errfn($dbms, $fn, $errno, $errmsg, $p1, $p2, $thisConnection);
}
