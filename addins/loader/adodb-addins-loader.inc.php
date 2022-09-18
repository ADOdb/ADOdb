<?php
/*
* Install PSR-0-compatible class autoloader that reads from the addins directory
* Simple replacement if you are not usong composer
*/	
spl_autoload_register(function($class)
{
    $file = __DIR__ . "/../../../" . preg_replace('{\\\\|_(?!.*\\\\)}', DIRECTORY_SEPARATOR, ltrim($class, '\\')).'.php';
	
    if (file_exists($file))
        include $file;
});

