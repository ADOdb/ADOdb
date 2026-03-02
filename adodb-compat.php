<?php


require 'vendor/autoload.php';
$loader = new Riimu\Kit\ClassLoader\ClassLoader();

$loader->addBasePath('c:/dev/github');
$loader->addPrefixPath([
    'ADOdb\Resources\MySQL',
    'ADOdb\Resources\SQLite',
    'ADOdb\Resources\SqlServer',
    'ADOdb\Resources\Oracle',
    'ADOdb\Resources\IBMDB2',
    'ADOdb\Resources'
]);
$loader->register();
