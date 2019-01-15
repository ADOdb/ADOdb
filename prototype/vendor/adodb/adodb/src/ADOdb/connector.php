<?php
namespace ADOdb;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

	
class connector
{
	protected $driver;
	
	protected $driverPath;
	
	protected $connectionObject;
	
	protected $fetchMode = 0;
	
	public $loggingObject;
 
	public $debug;
	
	protected $dataDictionary;
	
	protected $performanceMonitor;
 
	public function __construct($driver='',$options=false)
	{
		if (is_array($options))
		{
			if (isset($options['debug']))
				$this->debug = $options['debug'];
		}
		if ($this->debug) 
			$this->openLogger();
		
		if (!$driver)
		{
			if (!$this->debug)
				$this->openLogger();
			
			$this->logMessage('Must specify a valid ADOdb driver');
			return;
		}
		
		$driver = str_replace('/','\\',$driver);
		$driverArray = explode('\\',$driver);
		
		/*
		* Lets see if we have a valid driver
		*/
		list($status,$cleanDriver) = $this->checkDriverStatus($driverArray);
		
		if ($status)
			return;
		
		$this->driverPath = "ADOdb\\drivers\\$driver\\";
		$connectorClass   = $this->driverPath . 'adoConnection';
		
		
		
		$this->connectionObject = new $connectorClass;
		
		$this->connectionObject->debug 			= $this->debug;
		$this->connectionObject->driverPath 	= $this->driverPath;
		$this->connectionObject->loggingObject 	= $this->loggingObject;
		$this->connectionObject->fetchMode 		= $this->fetchMode;
		
	}
	public function openLogger()
	{
		$this->loggingObject = new \Monolog\Logger('name');
		$this->loggingObject->pushHandler(new StreamHandler('/dev/github/logs/log.txt', Logger::WARNING));
	}
		
	public function logMessage($message,$level=-1)
	{
		switch ($level)
		{
			default:
			$this->loggingObject->log(Logger::WARNING,$message);
			break;
		}
	}
	
	/**
	 * Connect to database
	 *
	 * @param [argHostname]		Host to connect to
	 * @param [argUsername]		Userid to login
	 * @param [argPassword]		Associated password
	 * @param [argDatabaseName]	database
	 * @param [forceNew]		force new connection
	 *
	 * @return true or false
	 */
	public function connect($argHostname = "", $argUsername = "", $argPassword = "", $argDatabaseName = "", $forceNew = false)
	{
		$this->connectionObject->connect($argHostname, $argUsername, $argPassword, $argDatabaseName, $forceNew);
		
		return $this->connectionObject;
	}
	
	/**
	* using this for parameters associated with the driver e.g. PDO
	*/
	public function setDriverParameter()
	{
	}
	
	/**
	* using this for parameters associated with the databer e.g. mysl
	*/
	public function setConnectionParameter()
	{
	}
	
	
	private function checkDriverStatus($driverArray)
	{
		
		$physicalDriver = __DIR__ . '/drivers/';
		
		$driverText = '[' . implode('\\',$driverArray) . ']';
		
		$cleanDriver = '';
		if (count($driverArray) > 2)
		{
			if ($this->debug)
			{
				$this->logMessage($driverText . ' is not a valid ADOdb driver name');
				return array(1,'');
			}
		}
		if (count($driverArray) > 1)
		{
			
			$pdo = strtoupper($driverArray[0]);
			if (strcmp($pdo,'PDO') <> 0)
			{
				$this->logMessage($driverText . ' is not a valid ADOdb driver name');
				return array(1,'');
			}
			$physicalDriver .= $pdo . '/';
			
			$cleanDriver = 'PDO\\';
		}
		
		$endpoint = strtolower(array_pop($driverArray));
		
		$physicalDriver .= $endpoint;
		$cleanDriver    .= $endpoint;
		
		if (!is_dir($physicalDriver))
		{
			$this->logMessage($driverText . ' is not a valid ADOdb driver name');
			return array(1,'');
		}
		
		
		return array(0,$cleanDriver);
	}
	
	public function setFetchMode($fetchMode)
	{
		$this->fetchMode = $fetchMode;
		
		if (is_object($this->connectionObject))
			$this->connectionObject->fetchMode = $fetchMode;
		
	}
	
	public function setADOdbParameter($parameter,$value)
	{
		$this->$parameter = $value;
	}
	
	public function loadPerformanceMonitor()
	{
		$perfmon = $this->driverPath . '/perfmon/performanceMonitor.php';
		if (!file_exists($perfmon))
		{
			if ($this->debug)
				$this->logMessage('Performance Monitor not available for this driver');
			return false;
		}
		
		$this->performanceMonitor = new $perfmon;
		
		return $this->performanceMonitor;
	}
	
	public function loadDataDictionary()
	{
		$datadict = $this->driverPath . '/datadict/dataDictionary.php';
		if (!file_exists($datadict))
		{
			if ($this->debug)
				$this->logMessage('Data Dictionary feature not available for this driver');
			return false;
		}
		
		$this->dataDictionary = new $datadict;
		$this->dataDictionary->quote = $this->connection->nameQuote;
		$this->dataDictionary->serverInfo = $this->connection->ServerInfo();

		
		return $this->dataDictionary;
	}		
	
}

include 'common/time/time.php';
include 'helpers.php';