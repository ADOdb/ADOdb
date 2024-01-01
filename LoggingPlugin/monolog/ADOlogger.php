<?php
/**
* The monolog logger functionality for ADOdb
*
* Requires access to a functional Monolog setup
*
* This file is part of the ADOdb package.
*
* @copyright 2021-2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\LoggingPlugin\monolog;

final class ADOLogger extends \ADOdb\LoggingPlugin\ADOLogger
{
	
	/*
	* Identifies the plugin
	*/
	protected string $plugin = 'monolog';

	/**
	 * Instantiates the object that does the actual logging
	 * 
	 * @param array $streamHandlers
	 * @param string $loggingTag
	 * @return bool
	 */
	final protected function activateLoggingObject(?array $streamHandlers,string $loggingTag) :bool
	{
		/*
		* Instantiate the monolog logger
		*/
		$this->loggingObject = new \Monolog\Logger($loggingTag);

		if (is_array($streamHandlers))
		{
			return $this->setStreamHandlers($streamHandlers);
		}

		return false;
	}

	/** 
	 * Push additional information into the log using the 
	 * monolog Processor feature
	 * 
	 * @param string  $processorName
	 * @return void
	 */
	final public function pushProcessor(string $processorName): void
	{

		$newProcessor = sprintf('\\Monolog\\Processor\\%s',$processorName);
		$this->loggingObject->pushProcessor(new $newProcessor);

	}
}
