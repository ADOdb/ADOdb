<?php
/**
* Logging class for the Logging package
*
* This file is part of the ADOdb package.
*
* @copyright 2021-2024 Mark Newnham
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
	* The JSON tag format file
	*/
	public string $jsonTagObject = "\\ADOdb\\LoggingPlugin\\ADOJsonTagFormat";
	
	/*
	* The JSON log format file
	*/
	public string $jsonLogObject = "\\ADOdb\\LoggingPlugin\\ADOJsonLogFormat";

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

	/********************************************
	 * Describes the way that data is written to
	 * file
	 ********************************************/
	public const LOG_FORMAT_PLAINTEXT = 0;
	public const LOG_FORMAT_JSON      = 1;

	
	protected int $logFormat = self::LOG_FORMAT_JSON;

	/*
	* The default tag that appears in the log file
	*/
	protected string $loggingIdentifier = 'ADODB';

	/*
	* Holds the final logging object, e.g. the monolog instance
	*/
	protected ?object $loggingObject = null;


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
	protected ?object $logJson = null;

	/*
	* A copy of the DB connection
	*/
	protected ?object $connection = null;

	/*
	* Any tags we want to transmit
	*/
	protected ?object $tagJson = null;

	/*
	* Activate the tagging feature
	*/
	protected bool $switchOnTags = false;

	/*
	* Use a pre-filled set of tags if required
	*/
	protected bool $addSystemTags = false;

	/**********************************************************************
	 * Section associated with logging Core functionality
	 **********************************************************************/

	
	/*
	* Indicates whether we are going to throw backtraces
	* into the logging system
	*/
	protected bool $logBacktrace = false;

	/*
	* Appends debugging of the logging class into the trail,
	* not the same as logging the parent module
	*/
	protected bool $debug = false;

	/*
	* Should the logging function prevent the raiseErrorFn from executing
	*/
	protected bool $suppressErrorFunction = false;

	
	/**
	* Constructor
	*
	* Determines how messages are processed
	*
	* @param $loggingDefinitions
	*
	*/
	public function __construct(?array $streamHandlers=null, string $loggingIdentifier='ADODB', int $logFormat=self::LOG_FORMAT_JSON,bool $debug=false){

		$this->debug 				= $debug;
		$this->loggingIdentifier 	= $loggingIdentifier;
		$this->logFormat 			= $logFormat;

		$this->activateLoggingObject($streamHandlers,$loggingIdentifier);
		
		if ($this->debug)
		{
			if ($this->loggingObject) {
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
	 * @param string $loggingIdentifier
	 * @return bool
	 */
	abstract protected function activateLoggingObject(?array $streamHandlers,string $loggingIdentifier);

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
	 * Is a particular level logged using the predifined levels
	 * 
	 * @param int $logLevel
	 * @return bool
	 */
	final public function getLoggedLevels(): array
	{

		return $this->logAtLevels;
		
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
		$jsonLogObject = $this->jsonLogObject;
		$logJson = new $jsonLogObject($this->connection);
		
		$this->logJson = $logJson;
	
	}

	/**
	 * Sets the connection into the tags
	 * 
	 * @return void
	 */
	final protected function loadTagJson() : void
	{

		if (!$this->tagJson)
		{
			if ($this->addSystemTags)
			{
				$jsonTagObject = $this->jsonTagObject;
				$tagJson = new $jsonTagObject($this->connection);
			} else {
				$tagJson = new \stdClass;
			}
		}

		$this->tagJson = $tagJson;
		
	}

	/** 
	 * Push tags into the log using the monolog TagProcessor feature
	 * 
	 * @param object $connection
	 * @return void
	 */
	abstract protected function pushTagJson(object $connection) : void;

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
	 * Appends a custom value into the message tag object
	 * 
	 * @param string $key
	 * @param mixed value
	 * @return void
	 */
	public function setMessageTag(?string $key,mixed $value=null) : void
	{
		if ($key === null)
		{
			$this->tagJson = null;
			return;
		}

		if (!$this->tagJson)
			$this->loadTagJson();
			
		$this->tagJson->$key = $value;
	}

	/**
	 * Activates the message tagging system
	 * 
	 * @param bool $switchOnTags
	 * @param bool $addSystemTags
	 * @return void
	 */
	final public function setMessageTags(?bool $switchOnTags=true,?bool $addSystemTags=false): void{
		$this->switchOnTags  = $switchOnTags;
		$this->addSystemTags = $addSystemTags;
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
	final public function setCoreLogging(bool $logBacktrace=false, bool $suppressErrorFunction=false) :void
	{
		/*
		* This global is seen by the core ADOdb system
		*/
		global $ADODB_LOGGING_OBJECT;
		
		$ADODB_LOGGING_OBJECT = $this;

		/*
		* Save off behavioral indicators
		*/
		$this->logBacktrace          = $logBacktrace;
		$this->suppressErrorFunction = $suppressErrorFunction;
	}

	/**
	* The root function takes an inbound ADODb log message
	* and converts it into a syslog format message. Note that
	* SQL execution errors don't pass through here
	*
	* Whilst it is a public function, its sole purpose is to post-process
	* data from the outp() method.
	*
	* The error level comes from a customized function in outp()
	*
	* @param string $messsge
	* @param bool $newline   Discarded by the function
	* @param int  $errorLevel The error level sent by the call
	*
	* @return void
	*/
	final public function coreLogger($message,$newline,$errorLevel=self::DEBUG) : void
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
	* Logs an invalid SQL execution
	*
	* @param object $connection
	* @param string $sql
	* @param array  input params
	*
	* @return void
	*/
	final public function logInvalidSql(object $connection, string $sql,mixed $inputarr) : void
	{
		if (!$this->isLevelLogged(self::CRITICAL))
			return;

		if (!is_object($this->connection))
			$this->setConnectionObject($connection);
		
		if ($this->logFormat == self::LOG_FORMAT_JSON)
		{
			$sqlStatement = array(
				'sql' => $sql,
				'params' => $inputarr
			);
			$this->setLoggingParameter('sqlStatement',$sqlStatement);
			/*
			* Obtain the database specific error notification
			*/
			$this->setLoggingParameter('errorCode',$connection->errorNo());
			$this->setLoggingParameter('errorMessage',$connection->errorMsg());
			/*
			* Adds the ADOdb Meta Error in the appropriate language set
			*/
			$this->setLoggingParameter('metaErrorCode',$connection->metaError($connection->errorNo()));
			$this->setLoggingParameter('metaErrorMessage',$connection->metaErrorMsg($connection->metaError($connection->errorNo())));

			if ($this->logBacktrace)
			{
				$backtraceData = $this->coreBacktrace();

				$this->setLoggingParameter('callStack',$backtraceData);
			}
			
			
			$this->pushTagJson($connection);
			$this->log(self::CRITICAL,'QUERY EXECUTION FAILURE');
		}
		else
		{
			$params = '';
			if (is_array($inputarr))
				$params = implode(',',$inputarr);
			
			$message = sprintf('Execution of statement failed: %s , %s / Error: %s %s',$sql,$params,$connection->ErrorNo(),$connection->ErrorMsg());
			
			$this->pushTagJson($connection);
			$this->log(self::CRITICAL,$message);
		}
		
		if (!$this->getErrorHandlingStatus())
		{
			/*
			* Process the error through both LOGGING_OBJECT and raiseErrorFn
			*/
			$fn = $connection->raiseErrorFn;
			if ($fn) {
				$fn($connection->databaseType,'EXECUTE',$connection->ErrorNo(),$connection->ErrorMsg(),$sql,$inputarr,$connection);
			}
		}
	}

	/**
	* Logs an valid SQL execution
	*
	* @param object $connection
	* @param string $sql
	* @param array  input params
	*
	* @return void
	*/
	final public function logValidSql(object $connection, string $sql,mixed $inputarr) : void
	{
		if (!$this->isLevelLogged(self::INFO))
			return;
		
		if (!is_object($this->connection))
			$this->setConnectionObject($connection);
				
		if ($this->logFormat == self::LOG_FORMAT_JSON)
		{

			$sqlStatement = array(
				'sql' => $sql,
				'params' => $inputarr
			);
			$this->setLoggingParameter('sqlStatement',$sqlStatement);
			$this->pushTagJson($connection);
			
			if ($this->logBacktrace)
			{
				$backtraceData = $this->coreBacktrace();

				$this->setLoggingParameter('callStack',$backtraceData);
			}
			$this->log(self::INFO,'SUCCESSFUL QUERY EXECUTION');
		}
		else
		{
			$params = '';
			if (is_array($inputarr))
				$params = implode(',',$inputarr);
			
			$message = sprintf('Successful Execution of sql: %s , params: %s',$sql,$params);

			$this->pushTagJson($connection);
			$this->log(self::INFO,$message);
		}
	}

	/**
	* Produces a backtrace object for JSON logging
	*
	* @return array
	*/
	private function coreBacktrace() : array
	{
		
		$elementArray    = array();
		
		// Get 2 extra elements if max depth is specified
		$elementsToIgnore = 0;
		$traceArr = debug_backtrace(0, 0);
		
		// Remove elements to ignore, plus the first 2 elements that just show
		// calls to adodb_backtrace
		for ($elementsToIgnore += 2; $elementsToIgnore > 0; $elementsToIgnore--) {
			array_shift($traceArr);
		}
		$elements = sizeof($traceArr);

		foreach ($traceArr as $element) {
			
			$baseClass    = '';
			$baseFunction = $element['function'];
			if (isset($element['class'])) {
				$baseClass = $element['class'];
			}

			// Function arguments
			$args = array();
			if (isset($element['args'])) 
			{
				foreach ($element['args'] as $v) {
					if (is_null($v)) {
						$args[] = 'null';
					} elseif (is_array($v)) {
						$args[] = 'Array[' . sizeof($v) . ']';
					} elseif (is_object($v)) {
						$args[] = 'Object:' . get_class($v);
					} elseif (is_bool($v)) {
						$args[] = $v ? 'true' : 'false';
					} else {
						// Remove newlines and tabs, compress repeating spaces
						$v = preg_replace('/\s+/', ' ', $v);
						$args[] = $v;
					}
				}
			}

			$file = str_replace('\\','/',$element['file']) ?? 'unknown file';
						
			$elements--;

			$c = new \stdClass;
			$c->class = $baseClass;
			$c->function = array(
				'name'=>$baseFunction,
				'args'=>$args);
			$c->file = $file;
			$c->line = $element['line'] ?? 'unknown';
			$elementArray[] = $c;
				
			
		}

		return $elementArray;

	}

	/**
	 * Sets the log format
	 * 
	 * @param int $logFormat
	 * @return bool
	 */
	final public function setLogFormat(int $logFormat) : bool
	{
		if ($logFormat < 0 || $logFormat > 1)
			return false;
		$this->logFormat = $logFormat;
		return true;
	}

	/**
	 * Sets the logging identifier
	 * 
	 * @param string $loggingIdentifier
	 * @return bool
	 */
	final public function setLoggingIdentifier(string $loggingIdentifier) : void
	{
	
		$this->loggingIdentifier = strtoupper($loggingIdentifier);
		
	}

	/**
	 * Returns the status of backtrace addition
	 * 
	 * @return bool
	 */
	final public function getBacktraceStatus() : bool
	{
		return $this->logBacktrace;
	}

	/**
	 * Returns the status of error function suppression
	 * 
	 * @return bool
	 */
	final public function getErrorHandlingStatus() : bool
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

