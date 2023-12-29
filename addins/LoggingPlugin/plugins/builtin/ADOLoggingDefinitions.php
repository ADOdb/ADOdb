<?php
/**
* logging definitions functionality for the Sessions package
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\LoggingPlugin\plugins\builtin;

class ADOLoggingDefinitions extends \ADOdb\addins\LoggingPlugin\ADOLoggingDefinitions
{

	/*
	* What logging mechanism are we using. Do not change
	*/
	public string $loggingMechanism = 'builtin';
	
	/*
	* A sane default file location for the log file. This
	* has to be somewhere writable by the web server (usually)
	*/
	public string $textFile = '/tmp/adodb.log';
	
	
}