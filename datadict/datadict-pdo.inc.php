<?php

/**
  V5.20dev  ??-???-2014  (c) 2000-2014 John Lim (jlim#natsoft.com). All rights reserved.
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.

  Set tabs to 4 for best viewing.

*/

// security - hide paths
if (!defined('ADODB_DIR')) {die();}
class ADODB2_pdo
{
	private $gADODB_DataDict = NULL;
	private $gCachedValuesBeforeConnectionSetup = array();

	function __get($pName)
		{return $this->gADODB_DataDict->$pName;}
	function __set($pName, $pValue)
	{
		if($this->gADODB_DataDict === NULL)
		{
			if($pName == "connection")
			{
				$tClassName = NULL;
				$tClassNamePostFix = $pValue->dsnType;
				
				if($tClassNamePostFix == 'oci')
					{$tClassNamePostFix = "oci8";}

				$tClassName = "ADODB2_".$tClassNamePostFix;

				include_once(ADODB_DIR.'/datadict/datadict-'.$tClassNamePostFix.'.inc.php');
				
				$this->gADODB_DataDict = new $tClassName();				
				
				foreach($this->gCachedValuesBeforeConnectionSetup as $tName=>$tValue)
					{$this->gADODB_DataDict->$tName = $tValue;}
				$this->gADODB_DataDict->connection = $pValue;
				$this->gCachedValuesBeforeConnectionSetup = NULL;
			}
			else
				{$this->gCachedValuesBeforeConnectionSetup[$pName] = $pValue;}
		}
		else
			{$this->gADODB_DataDict->$pName = $pValue;}
	}
	function __isset($pName)
		{return isset($this->gADODB_DataDict->$pName);}
	function __unset($pName)
		{unset($this->gADODB_DataDict->$pName);}
	function __call($pName, $pParameters)
	{
		switch(count($pParameters))
		{
			case 1:
				return $this->gADODB_DataDict->$pName($pParameters[0]);
				break;
			case 2:
				return $this->gADODB_DataDict->$pName($pParameters[0], $pParameters[1]);
				break;
			case 3:
				return $this->gADODB_DataDict->$pName($pParameters[0], $pParameters[1], 
						$pParameters[2]);
				break;
			case 4:
				return $this->gADODB_DataDict->$pName($pParameters[0], $pParameters[1], 
						$pParameters[2], $pParameters[3]);
				break;
			case 5:
				return $this->gADODB_DataDict->$pName($pParameters[0], $pParameters[1], 
						$pParameters[2], $pParameters[3], $pParameters[4]);
				break;
			case 6:
				return $this->gADODB_DataDict->$pName($pParameters[0], $pParameters[1], 
						$pParameters[2], $pParameters[3], $pParameters[4], $pParameters[5]);
				break;
			case 7:
				return $this->gADODB_DataDict->$pName($pParameters[0], $pParameters[1], 
						$pParameters[2], $pParameters[3], $pParameters[4], $pParameters[5],
						$pParameters[6]);
				break;
		}
	}
}