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

class ADObuiltinObject
{
	
	/*********************************************
	* For extension functionality, these logging
	* levels are from Monolog
	**********************************************/

    public const DEBUG     = 100;
    public const INFO      = 200;
    public const NOTICE    = 250;
    public const WARNING   = 300;
    public const ERROR     = 400;
    public const CRITICAL  = 500;
    public const ALERT     = 550;
    public const EMERGENCY = 600;

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
		
	/*
	* Array of StreamHandler objects that define the output
	*/
	protected array $streamHandlers = array();

	/*
	* The tag injected into all logging messages
	*/
    protected ?string $loggingIdentifier;

    public function __construct(string $loggingIdentifier)
    {
        $this->loggingIdentifier = $loggingIdentifier;
    }

	/**
	 * Defines the availablility of the output for the logging level
	 * 
	 * @param object  $handler
	 * @return void
	 */
	 final public function pushHandler(object $handler): void
	 {
 
        $this->streamHandlers[$handler->level] = $handler;

		$filePointer = fopen($handler->url,'a+');
		if (!$filePointer)
		{
			printf("Logging file at level %s startup error",$handler->level);
		}
		else
			fclose($filePointer);
 
	 }

		
	/**
	* An extremely basic log-to-file mechanism. If you
	* want something more exotic, use monolog
	*
	* @param int 	$logLevel
	* @param string $message
	*
	* @return void
	*/
	final public function log(int $logLevel,string $message=null,$tagJson=null): void{
		
        
		if (is_array($tagJson))
		{
			$tags = json_encode($tagJson);
		} else {
			$tags = '';
		}
		/*
		* In case we pass an invalid level
		*/
		$levelDescription = 'UNKNOWN';
		
		if (array_key_exists($logLevel,$this->levels))
			$levelDescription = $this->levels[$logLevel];
				
		$line = sprintf('[%s] %s.%s: %s %s%s',
						date('c'),
						$this->loggingIdentifier,
						$levelDescription,
						$message,
						$tags,
						PHP_EOL
						);
		
		if (!$this->streamHandlers)
			/*
			* No handlers defined, use textfile option
			*/
			$output = $this->textFile;
		else
			/*
			* Write to the appropriate stream 
			*/
			$output = $this->streamHandlers[$logLevel]->url;

		$fp = @fopen($output,'a+');
		if (is_resource($fp))
		{
			fputs($fp, $line);
			fclose($fp);
		}
	}
}

	