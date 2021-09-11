<?php
/**
* Base logging definitions functionality for the Logging package
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\logger;

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
		
		
}