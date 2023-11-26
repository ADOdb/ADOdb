<?php
/**
* Logging class for the Logging package
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\logger;

class ADOLogger
{
	/*********************************************
	* For extension functionality, these logging
	* levels are from Monolog
	**********************************************/

	/**
     * Detailed debug information
     */
    public const DEBUG = 100;

    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    public const INFO = 200;

    /**
     * Uncommon events
     */
    public const NOTICE = 250;

    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    public const WARNING = 300;

    /**
     * Runtime errors
     */
    public const ERROR = 400;

    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     */
    public const CRITICAL = 500;

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    public const ALERT = 550;

    /**
     * Urgent alert.
     */
    public const EMERGENCY = 600;

	public $loggingObject;
	/*
	* The debug level
	*/
	protected int $debugLevel = 0;
	
	/*
	* Indicates what message levels we log. Any
	* others are discarded. If empty then try
	* to log everything
	*/
	protected array $logAtLevels = array();

	/**
	* Constructor
	*
	* Determines how messages are processed
	*
	* @param $loggingDefinitions
	*
	*/
	public function __construct(
			?object $loggingDefinition=null){

		if (!is_object($loggingDefinition))
		{
			/*
			* Uses the default builtin class
			*/
			$loggingDefinition = new \ADOdb\addins\logger\plugins\builtin\ADOloggingDefinitions;
		}

		$plugin = $loggingDefinition->loggingMechanism;

		$loggingClass = sprintf('\\ADOdb\\addins\\logger\\plugins\\%s\\ADOlogger',$plugin);

		$this->loggingObject = new $loggingClass($loggingDefinition);

		if ($loggingDefinition->debug)
		{
			if ($this->loggingObject){
				$this->loggingObject->log(self::DEBUG,'The logging service was successfully started');
				$this->loggingObject->log(self::DEBUG,sprintf('The logging service uses the %s plugin',$plugin));
			}
			else
				/*
				* Nothing to write to, throw a message to STDOUT. Because
				* the logging object is a boolean false, the logging service is disabled
				*/
				printf ('A fatal error occurred starting the %s logging service',$plugin);
		}

	}

	/**
	* The core function for feature that uses this system
	*
	* @param	int		 $logLevel
	* @param	string	 $message
	* @param    string[] $tags
	* @return void
	*/
	public function log(int $logLevel,string $message, ?array $tags=null): void{

		if (!$tags)
			$tags = array();
		/*
		* Tranmit the message onto to whatever logging
		* system chosen we ignore any messages sent 
		* at levels not set
		*/
		if (count($this->logAtLevels) == 0 || $this->isLevelLogged($logLevel))
		{
			$this->loggingObject->log($logLevel,$message,$tags);
			
		}
	}

	/**
	 * Is a particular level logged
	 * 
	 * @param int $logLevel
	 * @return bool
	 */
	public function isLevelLogged(int $logLevel): bool{

		if (count($this->logAtLevels) == 0)
			return true;
		if (array_key_exists($logLevel,$this->logAtLevels))
			return true;

		return false;

	}

}

class ADOjsonLogFormat
{
	public string $version = '1.0';

	public string $ADOdbVersion = '';

	public string $level = '0';
	
	public string $message = '';

	public array $sqlStatement = array('sql'=>'','params'=>'');

	public int $errorCode = 0;
	
	public string $errorMessage = '';

	public string $host = '';

	public string $source = '';

	public string $driver = '';

	public string $php = '';

	public string $os  = '';

	public function __construct()
	{
		$this->php    = PHP_VERSION;
		$this->os     = PHP_OS;
		$this->source = isset($_SERVER['HTTP_USER_AGENT']) ? 'cgi' : 'cli';
	}
}