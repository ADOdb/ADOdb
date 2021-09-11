<?php
/**
* The monolg logger functionality for ADOdb
*
* Requires access to a functional Monolog setup
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\logger\plugins\monolog;

class ADOLogger extends \ADOdb\addins\logger\ADOLogger
{
	/*
	* The default tag that appears in the log file
	*/
	public string $loggingTag = 'ADOdb';

	/*
	* The default log level that appears in the log file
	*/
	public string $logLevel   = 'critical';

	/*
	* A sane default file location for the log file
	*/
	public string $textFile = __DIR__ . '/adodb.log';

	/*
	* An imported Monolog stream handler. If this is an array,
	* then the keys are the levels, and the values are the
	* streams. There are 2 levels,CRITICAL and DEBUG
	*/
	//public ?array $streamHandlers = null;

	public ?object $monologObject = null;


	final public function __construct(
			?object $loggingDefinition=null){

		/*
		* Save off to check for logging existence
		*/
		//$this->streamHandlers = $loggingDefinitions->streamHandlers;

		if ($loggingDefinition->debug)
			$this->log(ADOlogger::DEBUG,'Logging Sytem Startup');

		/*
		* Instantiate the monolog logger
		*/
		$this->monologObject = new \Monolog\Logger($loggingDefinition->loggingTag);

		foreach($loggingDefinition->streamHandlers as $level=>$s)
		{
			$this->monologObject->pushHandler($s);
			$this->logAtLevels[$level] = true;
		}
	}

	/**
	* Send a message to monolog
	*
	* @param int  $logLevel
	* @param string $message
	*
	* @return void
	*/
	public function log(int $logLevel,string $message): void
	{
		$this->monologObject->log($logLevel,$message);

	}
}
