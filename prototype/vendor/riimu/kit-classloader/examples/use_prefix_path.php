<?php

require_once __DIR__ . '/../vendor/autoload.php';

$loader = new Riimu\Kit\ClassLoader\ClassLoader();
$loader->addPrefixPath(__DIR__ . '/class/', 'Vendor\MyLib');
$loader->register();

var_dump(new Vendor\MyLib\OtherClass());
