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
namespace ADOdb\addins\LoggingPlugin\plugins\builtin;

class ADOlogger extends \ADOdb\addins\loggingPlugin\ADOlogger
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

	protected int $useTextHandler = 0;
	
	final public function __construct(
			?object $loggingDefinition=null){
		
		$targetObjectIdentifier = '\\ADOdb\\addins\\LoggingPlugin\\plugins\\builtin\\ADOloggingDefinitions';

		$this->targetObject = new $targetObjectIdentifier($loggingDefinition);

		if ($loggingDefinition->streamHandlers)
		{
			$this->streamHandlers = $loggingDefinition->streamHandlers;
			
			foreach($loggingDefinition->streamHandlers as $level=>$output)
			{
				$this->logAtLevels[$level] = true;
				$filePointer = fopen($output->url,'a');
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
	* Opens the default output if no streamHandlers defined
	*
	* @return bool success
	*/
	protected function openTextHandler(): bool
	{
		
		$filePointer = @fopen($loggingDefinition->textFile,'a');
		if (!$filePointer)
		{
			/*
			* Nowhere to log, write to STDOUT
			*/
			print "Logging file startup error";
			return false;
		}
		else
		{
			fclose($filePointer);
			$this->useTextHandler = 1;
			return true;
		}
		
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
	public function log(int $logLevel,string $message=null): void{
		
		if ($this->tagJson)
			$tags = json_encode($this->tagJson);
		else
			$tags = null;

		/*
		* In case we pass an invalid level
		*/
		$levelDescription = 'UNKNOWN';
		
		if (array_key_exists($logLevel,$this->levels))
			$levelDescription = $this->levels[$logLevel];

				
		$line = sprintf('[%s] %s.%s: %s %s%s',
						date('c'),
						$this->loggingTag,
						$levelDescription,
						$message,
						$tags,
						PHP_EOL
						);
		
		if (!$this->streamHandlers)
			$output = $this->targetObject->textFile;
		else if (array_key_exists($logLevel,$this->logAtLevels))
			/*
			* Write to the appropriate stream 
			*/
			$output = $this->streamHandlers[$logLevel]->url;
		else
			/*
			* Not a valid stream level
			*/
			return;
		
		$fp = @fopen($output,'a+');
		if (is_resource($fp))
		{
			fputs($fp, $line);
			fclose($fp);
		}
	}
}

	