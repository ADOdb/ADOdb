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

class ADOlogger extends \ADOdb\LoggingPlugin\ADOlogger
{
	
	/*
	* Identifies the plugin
	*/
	protected string $plugin = 'builtin';

	/**
    * 
    *
    * @var string[] $levels Logging levels with the levels as key
    */
    protected array $levels = [
        self::DEBUG     => 'DEBUG',
        self::INFO      => 'INFO',
        self::NOTICE    => 'NOTICE',
        self::WARNING   => 'WARNING',
        self::ERROR     => 'ERROR',
        self::CRITICAL  => 'CRITICAL',
        self::ALERT     => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
    ];
	
	/*
	* A sane default file location for the log file. This
	* has to be somewhere writable by the web server (usually)
	*/
	public string $textFile = '/tmp/adodb.log';
	
	protected int $useTextHandler = 0;

	protected function activateLoggingObject(?array $streamHandlers,string $loggingTag)
	{
		
		/*
		* Instantiate the builtin logger
		*/
		$this->loggingObject = new ADObuiltinObject($loggingTag);

		if (is_array($streamHandlers))
		{
			return $this->setStreamHandlers($streamHandlers);
		}

	}

	
}

	