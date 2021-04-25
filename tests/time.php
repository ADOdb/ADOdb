<?php
/**
 * ADOdb tests - Time.
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

include_once('../adodb-time.inc.php');
adodb_date_test();
?>
<?php
//require("adodb-time.inc.php");

$datestring = "2063-12-24"; // string normally from mySQL
$stringArray = explode("-", $datestring);
$date = adodb_mktime(0,0,0,$stringArray[1],$stringArray[2],$stringArray[0]);

$convertedDate = adodb_date("d-M-Y", $date); // converted string to UK style date

echo( "Original: $datestring<br>" );
echo( "Converted: $convertedDate" ); //why is string returned as one day (3 not 4) less for this example??
