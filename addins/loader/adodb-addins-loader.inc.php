<?php
/*
* Install PSR-0-compatible class autoloader that reads from the addins directory
*/	
spl_autoload_register(function($class)
{
    $file = "../" . preg_replace('{\\\\|_(?!.*\\\\)}', DIRECTORY_SEPARATOR, ltrim($class, '\\')).'.php';
    if (file_exists($file))
        include $file;
});