<?php

/**
 * ADOdb tests.
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

include_once('../adodb.inc.php');
$rs = new ADORecordSet_array();

$array = array(
array ('Name', 'Age'),
array ('John', '12'),
array ('Jill', '8'),
array ('Bill', '49')
);

$typearr = array('C','I');


$rs->InitArray($array, $typearr);

while (!$rs->EOF) {
    print_r($rs->fields);
    echo "<br>";
    $rs->MoveNext();
}

echo "<hr /> 1 Seek<br>";
$rs->Move(1);
while (!$rs->EOF) {
    print_r($rs->fields);
    echo "<br>";
    $rs->MoveNext();
}

echo "<hr /> 2 Seek<br>";
$rs->Move(2);
while (!$rs->EOF) {
    print_r($rs->fields);
    echo "<br>";
    $rs->MoveNext();
}

echo "<hr /> 3 Seek<br>";
$rs->Move(3);
while (!$rs->EOF) {
    print_r($rs->fields);
    echo "<br>";
    $rs->MoveNext();
}



die();
