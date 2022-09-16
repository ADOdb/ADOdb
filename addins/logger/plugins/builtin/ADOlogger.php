<?php
/**
* The builtin logger functionality for ADOdb
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\logger\plugins\builtin;

class ADOlogger extends \ADOdb\addins\logger\ADOlogger
{
	
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
	
	protected array $streamHandlers = array();
	
	final public function __construct(
			?object $loggingDefinition=null){
		
		if (count($loggingDefinition->streamHandlers) == 0)
		{
			$filePointer = @fopen($loggingDefinition->textFile,'a');
			if (!$filePointer)
			{
				/*
				* Nowhere to log, write to STDOUT
				*/
				print "Logging file startup error";
			}
			else
				fclose($filePointer);
		}
		else
		{
			$this->streamHandlers = $loggingDefinition->streamHandlers;
			
			foreach($loggingDefinition->streamHandlers as $level=>$output)
			{
				$this->levelInUse[$level] = true;
				$filePointer = fopen($output,'a');
				if (!$filePointer)
				{
					printf("Logging file at level %s startup error",$this->levels[$level]);
				}
				else
					fclose($filePointer);
			}
		}
		if ($loggingDefinition->loggingTag)
			$this->loggingTag = $loggingDefinition->loggingTag;
			
		if ($loggingDefinition->debug)
			$this->log(ADOlogger::DEBUG,'Logging Sytem Startup');
		
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
	public function log(int $logLevel,string $message): void{
		
				
		/*
		* In case we pass an invalid level
		*/
		$levelDescription = 'UNKNOWN';
		
		if (array_key_exists($logLevel,$this->levels))
			$levelDescription = $this->levels[$logLevel];
		
		$line = sprintf('[%s] %s.%s: %s%s',
						date('c'),
						$this->loggingTag,
						$levelDescription,
						$message,
						PHP_EOL
						);
		
		if (count($this->levelInUse) == 0)
			$output = $this->textFile;
		else if (array_key_exists($logLevel,$this->levelInUse))
			/*
			* Write to the appropriate stream 
			*/
			$output = $this->streamHandlers[$logLevel];
		else
			return;
			
		$fp = fopen($output,'a');
		fputs($fp, $line);
		fclose($fp);
	}
	
}

	