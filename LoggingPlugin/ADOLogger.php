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
namespace ADOdb\LoggingPlugin;
use ADOdb\LoggingPlugin;

abstract class ADOLogger
{
	
	/*******************************************
	 * Describes the available logging outputs
	 ********************************************/
	public const LOG_OUTPUT_BUILTIN = 'builtin';
	public const LOG_OUTPUT_MONOLOG = 'monolog';
	
	/********************************************
	 * Describes the way that data is written to
	 * file
	 ********************************************/
	public const LOG_FORMAT_PLAINTEXT = 0;
	public const LOG_FORMAT_JSON      = 1;

	public int $logFormat = self::LOG_FORMAT_JSON;

	/*
	* The default tag that appears in the log file
	*/
	public string $loggingTag = 'ADODB';

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

	/*
	* Holds the logging object
	*/
	public ?object $loggingObject = null;


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

	protected string $loggingDefinitionTemplate = '\\ADOdb\\addins\\LoggingPlugin\\plugins\\%s\\ADOloggingDefinitions';
	protected string $loggingObjectTemplate     = '\\ADOdb\\addins\\LoggingPlugin\\plugins\\%s\\ADOlogger';

	/*
	* Identifies the plugin
	*/
	protected string $plugin = '';

	/*
	* JSON logging/Plain, imported from loggingDefinitions
	*/
	protected int $jsonLogging = 1;

	/*
	* Holds the default JSON format object
	*/
	public ?object $logJson = null;

	public ?object $connection = null;

	/*
	* Any tags we want to transmit
	*/
	public ?object $tagJson = null;

	/**********************************************************************
	 * Section associated with logging Core functionality
	 **********************************************************************/

	/*
	* This is picked up by outp() and used to redirect messages here
	*/
	public string $outpMethod = 'coreLogger';

	/*
	* This is picked up by adodb_backtrace and used to redirect messages
	* here
	*/
	public string $backtraceMethod = 'coreBacktrace';

	/*
	* Indicates whether we are going to throw backtraces
	* into the logging system
	*/
	public bool $logBacktrace = false;

	/*
	* Appends debugging of the logging class into the trail,
	* not the same as logging the parent module
	*/
	public bool $debug = false;

	/*
	* Should the logging function prevent the raiseErrorFn from executing
	*/
	protected bool $suppressErrorFunction = true;
	
	
	/**
	* Constructor
	*
	* Determines how messages are processed
	*
	* @param $loggingDefinitions
	*
	*/
	public function __construct(?array $streamHandlers=null, string $loggingTag='ADODB', int $logFormat=self::LOG_FORMAT_JSON,bool $debug=false){

		$this->debug 		= $debug;
		$this->loggingTag 	= $loggingTag;
		$this->logFormat 	= $logFormat;

		$this->activateLoggingObject($streamHandlers,$loggingTag);
		
		if ($this->debug)
		{
			if ($this->loggingObject){
				$this->loggingObject->log(self::DEBUG,'The logging service was successfully started');
				$this->loggingObject->log(self::DEBUG,sprintf('The logging service uses the %s plugin',$this->plugin));
			}
			else
				/*
				* Nothing to write to, throw a message to STDOUT. Because
				* the logging object is a boolean false, the logging service is disabled
				*/
				printf ('A fatal error occurred starting the %s logging service',$this->plugin);
		}

	}

	/**
	 * Instantiates the object that does the actual logging
	 * 
	 * @param array $streamHandlers
	 * @param string $loggingTag
	 * @return bool
	 */
	abstract protected function activateLoggingObject(?array $streamHandlers,string $loggingTag);

	/**
	* The core function for feature that uses this system
	*
	* @param	int		 $logLevel
	* @param	string	 $message
	* @return void
	*/
	public function log(int $logLevel,?string $message=null): void
	{
		
		if (is_array($this->tagJson))
		{
			$tags = $this->tagJson;
		} else {
			$tags = array();
		}

		/*
		* If the message is json format, encode as necessary
		*/
		if ($this->logFormat == self::LOG_FORMAT_JSON)
		{
		
			if (!is_object($this->logJson))
			{
				$this->loadLoggingJson();
			}
			if (is_string($message))
			{
				$this->logJson->shortMessage = $message;
			}
			
			$this->logJson->level = $logLevel;
			$message = json_encode($this->logJson);
		}

		/* Tranmit the message onto to whatever logging
		* system chosen we ignore any messages sent 
		* at levels not set
		*/
		if (count($this->logAtLevels) == 0 || $this->isLevelLogged($logLevel))
		{
			/*
			* Send the message to the appropriate handler
			*/
			$this->loggingObject->log($logLevel,$message,$tags);
			
		}
		
		$this->logJson = null;
	}

	/**
	 * Is a particular level logged using the predifined levels
	 * 
	 * @param int $logLevel
	 * @return bool
	 */
	final public function isLevelLogged(int $logLevel): bool{

		if (count($this->logAtLevels) == 0)
			return true;
		
		if (array_key_exists($logLevel,$this->logAtLevels))
			return true;

		return false;

	}

	/**
	 * Adds the connection into the Logging Object
	 * 
	 * @param object $connection
	 * @param int    $logLevel
	 * @return void
	 */
	final public function setConnectionObject(object $connection) : void
	{
		$this->connection = $connection;
		if (!is_object($this->logJson))
		{
			$this->loadLoggingJson();
		}
		
		$this->setLoggingParameter('driver',$connection->databaseType);
		$this->setLoggingParameter('ADOdbVersion',$connection->version());
	
	}

	/**
	 * Sets the streamHandlers into the Logging Object if we want
	 * to delay the loading of the handlers for any reason
	 * 
	 * 
	 * @param array $streamHandlers
	 * @return bool
	 */
	final public function setStreamHandlers(array $streamHandlers) : bool
	{
		if (!$this->loggingObject)
		{
			return false;
		}
						
		foreach($streamHandlers as $level=>$s)
		{
			$this->loggingObject->pushHandler($s);
			$this->logAtLevels[$level] = true;
		}
		
		return true;
	
	}


	/**
	 * Creates and stores an empty log object
	 * 
	 * @param object $connection
	 * @param int    $logLevel
	 * @return void
	 */
	private function loadLoggingJson() : void
	{
		$logJson = new \ADOdb\LoggingPlugin\ADOJsonLogFormat;
		
		if ($this->connection)
		{
			/*
			* Adds driver specific data to the message
			*/
			$logJson->driver                 = $this->connection->databaseType;
			$logJson->ADOdbVersion			 = $this->connection->version();
		}

		$this->logJson = $logJson;
	
	}

	/**
	 * Sets the connection into the tags
	 * 
	 * @param object $connection
	 * @return void
	 */
	final public function loadTagJson(object $connection) : void
	{
		$tagJson = new \ADOdb\LoggingPlugin\ADOJsonTagFormat;
			
		$tagJson->driver                 = $connection->databaseType;
		$tagJson->ADOdbVersion			 = $connection->version();

		$this->tagJson = $tagJson;
	}

	/**
	 * Appends any custom or standard value into the logging object
	 * 
	 * @param string $key
	 * @param mixed value
	 * @return void
	 */
	final public function setLoggingParameter(string $key,mixed $value) : void
	{
		if (!is_object($this->logJson))
		{
			$this->loadLoggingJson();
		}

		$this->logJson->{$key} = $value;

	}

	/**
	 * Appends a custom value into the tag object
	 * 
	 * @param string $key
	 * @param mixed value
	 * @return void
	 */
	public function setLoggingTag(?string $key,mixed $value=null) : void
	{
		if ($key === null)
		{
			$this->tagJson = null;
			return;
		}

		if (!$this->tagJson)
			$this->tagJson = new \ADOdb\LoggingPlugin\ADOjsonTagFormat;

		$this->tagJson->$key = $value;
	}

	/************************************************************************
	 * Methods associated with core logging functions
	 ************************************************************************/

	/**
	* Inserts this logging system as the default for ADOdb
	*
	* @param bool $logBacktrace Adds backtrace information to log
	* @param bool $suppressErrorFunction Stops the $raiseErrorFn from running
	* @return void
	*/
	final public function redirectCoreLogging(bool $logBacktrace=false, bool $suppressErrorFunction=true) :void
	{
		global $ADODB_LOGGING_OBJECT;
		/*
		* This global is seen by the core ADOdb system
		*/
		$ADODB_LOGGING_OBJECT = $this;

		$this->logBacktrace = $logBacktrace;
		$this->suppressErrorFunction = $suppressErrorFunction;
	}

	/**
	* The root function takes an inbound ADODb log message
	* and converts it into a syslog format message. Note that
	* SQL execution errors don't pass through here
	*
	* The error level comes from a customized function in outp()
	*
	* @param string $messsge
	* @param bool $newline   Discarded by the function
	* @param int  $errorLevel The error level sent by the call
	*
	* @return void
	*/
	final public function coreLogger($message,$newline,$errorLevel=self::DEBUG)
	{
		
		/*
		* We do the best we can here to turn the inbound message
		* into something that is suitable for logging. Order of
		* processing is important here, the last process should
		* always be the multi-space removal
		*/
		$message = str_replace("\n",' ',$message);
		$message = strip_tags($message);
		$message = str_replace('&nbsp;',' ',$message);
		$message = htmlspecialchars_decode($message);
		$message = preg_replace('!\s+!', ' ', $message);

		/*
		* Now pass the message to the appropriate plugin
		*/
		$this->log($errorLevel,$message);
	}

	/**
	* Linked to the adodb_backtrace
	*
	* @param bool $printArr
	* @param int  $levels
	* @param int  $skippy
	* @param string $ishtml
	*
	* @return void
	*/
	public function coreBacktrace($printOrArr=true,$levels=9999,$skippy=0,$ishtml=null)
	{
		if (!function_exists('debug_backtrace'))
		{
			$this->log(self::DEBUG,'function debug_backtrace unavailable');
			return '';
		}

		if (!$this->logBacktrace)
			/*
			* If we switched off backtrace logging
			*/
			return;


		$fmt =  "%% line %4d, file: %s";

		$MAXSTRLEN = 128;

		$traceArr = debug_backtrace();
		
		$tabs = sizeof($traceArr)-1;

		$s = '';

		foreach ($traceArr as $arr) {
			if ($skippy) {
				$skippy -= 1;
				continue;
			}

			$levels -= 1;
			if ($levels < 0)
				break;

			$args = array();
			$s = sprintf('[STACK %s] ',$tabs);
			$tabs -= 1;

			if (isset($arr['class']))
				$s .= $arr['class'].'.';

			if (isset($arr['args']))
			{
				foreach($arr['args'] as $v)
				{
					if (is_null($v))
						$args[] = 'null';
					else if (is_array($v))
						$args[] = 'Array['.sizeof($v).']';
					else if (is_object($v))
						$args[] = 'Object:'.get_class($v);
					else if (is_bool($v))
						$args[] = $v ? 'true' : 'false';
					else {
						$v = (string) @$v;
						$str = str_replace(array("\r","\n"),' ',substr($v,0,$MAXSTRLEN));
						if (strlen($v) > $MAXSTRLEN)
							$str .= '...';

						$args[] = $str;
					}
				}
			}
			$s .= $arr['function'].'('.implode(', ',$args).')';

			$s .= @sprintf($fmt, $arr['line'],$arr['file'],basename($arr['file']));
		}
		return $s;
	}

	/**
	 * Returns the status of error function suppression
	 * 
	 * @return bool
	 */
	final public function ignoreErrorHandling() : bool
	{
		return $this->suppressErrorFunction;
	}

	/** 
	 * Push additional information into the log using the 
	 * monolog Processor feature
	 * 
	 * @param string  $processorName
	 * @return void
	 */
	 abstract public function pushProcessor(string $processorName): void;
}

