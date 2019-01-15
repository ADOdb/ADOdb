<?php
namespace ADOdb\common\database;

use ADOdb;


class debugExecute extends execute
{
	
	function __construct($connection, $sql, $inputarr)
	{
		
		$ss = '';
		if ($inputarr) {
			foreach($inputarr as $kk=>$vv) {
				if (is_string($vv) && strlen($vv)>64) $vv = substr($vv,0,64).'...';
				if (is_null($vv)) $ss .= "($kk=>null) ";
				else $ss .= "($kk=>'$vv') ";
			}
			$ss = "[ $ss ]";
		}
		$sqlTxt = is_array($sql) ? $sql[0] : $sql;
		/*str_replace(', ','##1#__^LF',is_array($sql) ? $sql[0] : $sql);
		$sqlTxt = str_replace(',',', ',$sqlTxt);
		$sqlTxt = str_replace('##1#__^LF', ', ' ,$sqlTxt);
		*/
		// check if running from browser or command-line
		$inBrowser = isset($_SERVER['HTTP_USER_AGENT']);

		$connection->logMessage($ss);
		$connection->logMessage($sqlTxt);
		
		$qID = $connection->_query($sql,$inputarr);

		/*
			Alexios Fakios notes that ErrorMsg() must be called before ErrorNo() for mssql
			because ErrorNo() calls Execute('SELECT @ERROR'), causing recursion
		*/
		if ($connection->databaseType == 'mssql') {
		// ErrorNo is a slow function call in mssql, and not reliable in PHP 4.0.6

			if($emsg = $connection->ErrorMsg()) {
				if ($err = $connection->ErrorNo()) {
					if ($connection->debug === -99)
						print_r( "<hr>\n($dbt): ".htmlspecialchars($sqlTxt)." &nbsp; $ss\n<hr>\n",false);
	print_r($err.': '.$emsg);
				}
			}
		} else if (!$qID) {

			if ($connection->debug === -99)
					if ($inBrowser) print_r( "<hr>\n($dbt): ".htmlspecialchars($sqlTxt)." &nbsp; $ss\n<hr>\n",false);
					else print_r("-----<hr>\n($dbt): ".$sqlTxt."$ss\n-----<hr>\n",false);

			print "\n-------------------->";
			print_r($connection->ErrorNo() .': '. $connection->ErrorMsg());
		}
		
		$connection->logMessage(print_r($connection->ErrorNo() .': '. $connection->ErrorMsg(),true));
		//if ($connection->debug === 99) 
			
		$connection->logMessage($this->_adodb_backtrace(true,9999,2));
		$this->result = $qID;
	}

	private function _adodb_backtrace($printOrArr=true,$levels=9999,$skippy=0,$ishtml=null)
	{
		if (!function_exists('debug_backtrace')) return '';

		if ($ishtml === null) $html =  (isset($_SERVER['HTTP_USER_AGENT']));
		else $html = $ishtml;

		$fmt =  ($html) ? "</font><font color=#808080 size=-1> %% line %4d, file: <a href=\"file:/%s\">%s</a></font>" : "%% line %4d, file: %s";

		$MAXSTRLEN = 128;

		$s = ($html) ? '<pre align=left>' : '';

		if (is_array($printOrArr)) $traceArr = $printOrArr;
		else $traceArr = debug_backtrace();
		array_shift($traceArr);
		array_shift($traceArr);
		$tabs = sizeof($traceArr)-2;

		foreach ($traceArr as $arr) {
			if ($skippy) {$skippy -= 1; continue;}
			$levels -= 1;
			if ($levels < 0) break;

			$args = array();
			for ($i=0; $i < $tabs; $i++) $s .=  ($html) ? ' &nbsp; ' : "\t";
			$tabs -= 1;
			if ($html) $s .= '<font face="Courier New,Courier">';
			if (isset($arr['class'])) $s .= $arr['class'].'.';
			if (isset($arr['args']))
			 foreach($arr['args'] as $v) {
				if (is_null($v)) $args[] = 'null';
				else if (is_array($v)) $args[] = 'Array['.sizeof($v).']';
				else if (is_object($v)) $args[] = 'Object:'.get_class($v);
				else if (is_bool($v)) $args[] = $v ? 'true' : 'false';
				else {
					$v = (string) @$v;
					$str = htmlspecialchars(str_replace(array("\r","\n"),' ',substr($v,0,$MAXSTRLEN)));
					if (strlen($v) > $MAXSTRLEN) $str .= '...';
					$args[] = $str;
				}
			}
			$s .= $arr['function'].'('.implode(', ',$args).')';


			$s .= @sprintf($fmt, $arr['line'],$arr['file'],basename($arr['file']));

			$s .= "\n";
		}
		if ($html) $s .= '</pre>';
		//if ($printOrArr) print $s;
		
		return $s;
	}

	private function adodb_backtrace($printOrArr=true,$levels=9999,$ishtml=null)
	{
		
		$t = $this->_adodb_backtrace($printOrArr,$levels,0,$ishtml);
		print_r($t);
	}
}