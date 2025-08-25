<?php
/**
 * PHPUnit allows for a "bootstrap" file to set up autoloading.
 * ADOdb doesn't really use autoloading so it's just a set of
 * conditional includes
 */

if (!function_exists('ADONewConnection')) {
    include(__DIR__ . '/../adodb.inc.php');
}
if (!class_exists('ADODB_Active_Record')) {
    include(__DIR__ . '/../adodb-active-record.inc.php');
}
if (!class_exists('adoSchema')) {
    include(__DIR__ . '/../adodb-xmlschema03.inc.php');
}

