<?php
/**
* Core logging functionality extension for the Logging package
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\logger;

class ADOCoreLogger extends \ADOdb\addins\logger\ADOLogger
{

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

	/**
	* Constructor
	*
	* Determines how messages are processed
	*
	* @param object $loggingDefinitions
	*
	*/
	public function __construct(
			?object $loggingDefinition=null){

		parent::__construct($loggingDefinition);

		$this->redirectCoreLogging();

	}

	/**
	* Inserts this logging system as the default for ADOdb
	*
	* @return void
	*/
	public function redirectCoreLogging() :void
	{
		//global $ADODB_OUTP;
		global $ADODB_LOGGING_OBJECT;
		/*
		* This global is seen by the core ADOdb system
		*/
		//$ADODB_OUTP = $this;
		$ADODB_LOGGING_OBJECT = $this;
	}

	/**
	* The root function takes an inbound ADODb log message
	* and converts it into a syslog format message.
	*
	* The error level comes from a customized function in outp()
	*
	* @param string $messsge
	* @param bool $newline   Discarded by the function
	* @param int  $errorLevel The error level sent by the call
	*
	* @return void
	*/
	public function coreLogger($message,$newline,$errorLevel=self::DEBUG)
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

		if (is_array($printOrArr))
		{
			$traceArr = $printOrArr;
			$logLevel = $this::DEBUG;
			$this->log($logLevel,'----------- DEBUG STARTS ----------');
		}
		else
		{
			$traceArr = debug_backtrace();
			$logLevel = $this::CRITICAL;
			$this->log($logLevel,'----------- ERROR STACK STARTS ----------');

			//print_r($traceArr); exit;

			array_shift($traceArr);
			array_shift($traceArr);

			$traceObject = $traceArr[0]['object'];

			//print_r($traceObject); exit;

		}

		array_shift($traceArr);
		array_shift($traceArr);

		//print_r($traceArr); //exit;
		$tabs = sizeof($traceArr)-1;

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

			$this->log($logLevel,$s);
			$s = '';

		}
		if (is_array($printOrArr))
		{
			$this->log($logLevel,'----------- DEBUG ENDS ----------');
		}
		else
		{
			$this->log($logLevel,'----------- ERROR STACK ENDS ----------');
		}
		return;
		if ($printOrArr)
			print $s;

		return $s;
	}
}
