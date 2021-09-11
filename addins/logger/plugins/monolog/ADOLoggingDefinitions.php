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
namespace ADOdb\addins\logger\plugins\monolog;

class ADOLoggingDefinitions extends \ADOdb\addins\logger\ADOLoggingDefinitions
{

	/*
	* What logging mechanism are we using. Do not change
	*/
	public string $loggingMechanism = 'monolog';
	
	/*
	* An imported Monolog stream handler. If this is an array,
	* then the keys are the levels, and the values are the
	* streams. 
	*/
	public ?array $streamHandlers = null;
}