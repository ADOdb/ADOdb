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
namespace ADOdb\addins\LoggingPlugin\plugins\monolog;

class ADOLogger extends \ADOdb\addins\LoggingPlugin\ADOLogger
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
	public ?object $targetObject = null;

	final public function __construct(
			?object $loggingDefinition=null){
		
		if ($loggingDefinition->debug)
			$this->log(ADOlogger::DEBUG,'Logging Sytem Startup');

		/*
		* Instantiate the monolog logger
		*/
		$this->targetObject = new \Monolog\Logger($loggingDefinition->loggingTag);

		if (is_array($loggingDefinition->streamHandlers))
		{
			foreach($loggingDefinition->streamHandlers as $level=>$s)
			{
				$this->targetObject->pushHandler($s);
				$this->logAtLevels[$level] = true;
			}
		}

		/*
		* This can be how we push additional information into the log
		*/
		//$this->targetObject->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor());
		
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
	public function log(int $logLevel,string $message=null): void
	{
		if ($this->tagJson)
			$tagArray = (array) $this->tagJson;
		else
			$tagArray = array();
		
		$this->targetObject->log($logLevel,$message,$tagArray);

	}
}
