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
	* An imported Monolog object
	*/
	public ?object $monologObject = null;

	final public function __construct(
			?object $loggingDefinition=null){
		
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

		/*
		* This can be how we push additional information into the log
		*/
		//$this->monologObject->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor());
		
	}

	/**
	* Send a message to monolog
	*
	* @param int  $logLevel
	* @param string $message
	* @param string[] $tags
	*
	* @return void
	*/
	public function log(int $logLevel,string $message,?array $tags=null): void
	{
		if (!$tags)
			$tags = array();
		
		$this->monologObject->log($logLevel,$message,$tags);

	}
}
