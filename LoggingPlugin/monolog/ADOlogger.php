<?php
/**
* The monolog logger functionality for ADOdb
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
namespace ADOdb\LoggingPlugin\monolog;

class ADOLogger extends \ADOdb\LoggingPlugin\ADOLogger
{
	
	/*
	* Identifies the plugin
	*/
	protected string $plugin = 'monolog';

		
	protected function activateLoggingObject(?array $streamHandlers,string $loggingTag)
	{
		/*
		* Instantiate the monolog logger
		*/
		$this->loggingObject = new \Monolog\Logger($loggingTag);

		if (is_array($streamHandlers))
		{
			return $this->setStreamHandlers($streamHandlers);
		}

	}

	/**
	 * Push additional information into the log
	 * 
	 * @param string  $processorName
	 * @return void
	 */
	
	public function pushProcessor(string $processorName): void
	{

		$newProcessor = sprintf('\\Monolog\\Processor\\%s',$processorName);
		$this->loggingObject->pushProcessor(new $newProcessor);

	}

}
