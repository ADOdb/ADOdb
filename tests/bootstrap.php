<?php

if (!function_exists('ADONewConnection')) {
    include(__DIR__ . '/../adodb.inc.php');
}
if (!class_exists('ADODB_Active_Record')) {
    include(__DIR__ . '/../adodb-active-record.inc.php');
}
if (!class_exists('adoSchema')) {
    include(__DIR__ . '/../adodb-xmlschema03.inc.php');
}

