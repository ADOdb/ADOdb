<?php
/**
* The builtin logger functionality for ADOdb
*
* This file is part of the ADOdb package.
*
* @copyright 2021-2023 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\LoggingPlugin\builtin;
use ADOdb\LoggingPlugin\builtin\ADObuiltinObject;

final class ADOlogger extends \ADOdb\LoggingPlugin\ADOlogger
{
	
	/*
	* Identifies the plugin
	*/
	protected string $plugin = 'builtin';
	
	/**
	 * Instantiates the object that does the actual logging
	 * 
	 * @param array $streamHandlers
	 * @param string $loggingIdentifier
	 * @return bool
	 */
	final protected function activateLoggingObject(?array $streamHandlers,string $loggingIdentifier) : bool
	{
		
		/*
		* Instantiate the builtin logger
		*/
		$this->loggingObject = new ADObuiltinObject($loggingIdentifier);

		if (is_array($streamHandlers))
		{
			return $this->setStreamHandlers($streamHandlers);
		}
		return true;
	}

	/** 
	 * Push additional information into the log using the 
	 * Processor feature
	 * 
	 * @param string  $processorName
	 * @return void
	 */
	final public function pushProcessor(string $processorName): void {}

	/** 
	 * Push tags into the log using the TagProcessor feature
	 * 
	 * @param object $connection
	 * @return void
	 */
	final protected function pushTagJson(object $connection) : void{}
	
}

	