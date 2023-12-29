<?php
/**
* Base logging definitions functionality for the Logging package
*
* This file is part of the ADOdb package.
*
* @copyright 2021-2023 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\LoggingPlugin;

class ADOLoggingDefinitions
{

	/*
	* What logging mechanism are we using. set by the plugin
	*/
	public string $loggingMechanism = '';
	
	/*
	* Appends debugging of the logging class into the trail,
	* not the same as logging the parent module
	*/
	public bool $debug = false;
	
	/*
	* The default tag that appears in the log file
	*/
	public string $loggingTag = 'ADODB';

	/*
	* Whether we use JSON or Plain Text. JSON = 1, Plain = 0;
	*/
	public int $jsonLogging = 1;

	/*
	* Determines the output for the levels
	* If unused, everything is logged to the same file
	*/
	public ?array $streamHandlers = null;
		
		
}