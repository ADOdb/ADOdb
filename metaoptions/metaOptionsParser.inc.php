<?php
/**
*/
final class metaOptionsParser
{	
	protected $lines         = array();
	protected $primaryKeys   = array();
	protected $indexes       = array();
	private   $parsedOptions = array();
	private	  $metaObject	 = false;
	private   $fieldData	 = array();
	private   $indexData	 = array();
	private   $indexName     = '';
	
	public function __construct($dict,$metaObject)
	{
	
		switch ($metaObject->type)
		{
			case 'table':
			$this->parseTableObject($dict,$metaObject);
			break;

			case 'columns':
			$this->parseColumnObject($dict,$metaObject);
			break;
			
			case 'indexes':
			$this->parseIndexObject($dict,$metaObject);
			break;
		}
	}
	
	private function parseTableObject($dict,$object,$t=false)
	{
		
		/*
		* Stage 1 of development, we break the object down into the
		* table name, fields and options
		*/
	
		$this->tableName = (string)$object->name;
		
		if (!isset($object->attributes))
			$this->object = array();
		
		if (!isset($object->options))
			$object->options = array();
		
		if (!isset($object->columns))
			$object->columns = array();
		
		if (!isset($object->indexes))
			$object->indexes = array();
		
		$this->fieldData = new metaElementStructure;
		$this->fieldData->type = 'columns';
		$this->fieldData->options = $object->columns;
		
		$this->indexData = new metaElementStructure;
		$this->indexData->type = 'indexes';
		$this->indexData->options = $object->indexes;
		
		
		$t = array();
		foreach ($object->attributes as $o)
		{
			
			if ($o->platform)
				$t[strtoupper($o->platform)] = $o;
			
			else
			{
				$t[] = $o;
			}
		}
		
		$s = array();
		foreach ($t as $k=>$v)
			$s[$k] = $v->value;
		
		$this->options = $s;
		
		
		
	}
	
	public function getParsedTable()
	{
		
		return array($this->tableName,$this->fieldData,$this->options,$this->indexData);
	
	}
	
	public function getParsedIndex()
	{
		
		return array($this->indexName,$this->fieldData,$this->options,$this->indexData);
	
	}

	
	private function parseColumnObject($dict,$flds)
	{
		
		$pkey = array();
		$idxs = array();
		
		$columns = $flds->options;
		
		foreach ($columns as $key=>$fieldData)
		{
			
			$fieldName = $fieldData->name;
			/*
			* Get the type first because we need that for all the other
			* options
			*/
			if (isset($fieldData->value))
				$fieldType = $fieldData->value;
			else
				$fieldType = '';
			
			$line = $fieldName . ' ' . $fieldType;
			
			foreach ($fieldData->attributes as $a)
			{
				
				if ($a->platform && strcasecmp($a->platform,$dict->connection->dataProvider) <> 0)
					continue;
				
				list($replacementLine,$portableLineData,$poPrimaryKeys,$poIndex) = $this->processColumnAttributes($dict,$a);
				if ($replacementLine)
				{
					$line = $replacementLine;
				}
				
				if ($portableLineData)
					$line .= ' ' . $portableLineData;
				
				$pkey  = array_merge($pkey,$poPrimaryKeys);
				$idxs  = array_merge($idxs,$poIndex); 
				
			}
			
			$lines[] = $line;
		}
		$this->parsedOptions = array($lines,$pkey,$idxs);
	}
	
	/**
	* Takes any attribute objects associated with a column and parses it
	*
	* @param	obj		$dict	The Dictionary object
	* @param	obj		$attributes	An array of metaElementStructures
	*
	* @return	array
	*/
	protected function processColumnAttributes($dict,$attributes)
	{
		$primaryKeys     = array();
		$indexes 	     = array();
		$line            = '';
		$replacementLine = '';
		
		$attributeValue = $attributes->value;
		
		if (!is_array($attributeValue))
			$attributeValue = (array)$attributeValue;

		foreach($attributeValue as $key=>$value)
		{
			
			$arrayToPass = array($key=>$value);
			if (is_numeric($key))
			{
				$key = $value;
			    $value       = '';
				$arrayToPass = array($key=>$value);
			}
			
			/*
			* We cannot autoload a class name with special characters or
			* spaces in it, so it must be a custom value
			*/
			try {

				if (preg_match('/[^A-z0-9]/',$key))
					throw new Exception("NOT AN AUTOLOADABLE CLASS");
				
				$loader = 'metaOption_' . strtoupper($key);
				$optionHandler = new $loader($dict,$value);
			}
			catch (Exception $e)
			{
				$loader = 'metaOption_CUSTOM';
				$optionHandler = new $loader($dict,$value,$key);
			}

			if (is_object($optionHandler))
			{
				list($replacementLine, $lineItem,$primaryKey,$index) = $optionHandler->getAttributes();
				
				if ($lineItem)
					$line .= $lineItem;
				
				if ($primaryKey)
					$primaryKeys[] = $attributes->name;
				
				if ($index)
					$indexes[] = $attributes->name;
			}
		}

		return array($replacementLine, $line,$primaryKeys,$indexes);
	}
	
	final public function getParsedOptions()
	{
		return $this->parsedOptions;
	}
	
	private function parseIndexObject($dict,$metaObject,$f=false)
	{
		$this->parsedOptions = array();
		foreach ($metaObject->options as $indexObject)
		{
			if ($indexObject->platform && strcasecmp($indexObject->platform,$dict->connection->dataProvider) <> 0)
				continue;	
			
			$this->parsedOptions[$indexObject->name] = array('cols'=>array(),'opts'=>array());
			foreach ($indexObject->columns as $columnObject)
			{
				$line = $columnObject->name;
				foreach ($columnObject->attributes as $a)
				{
				
					if ($a->platform && strcasecmp($a->platform,$dict->connection->dataProvider) <> 0)
						continue;
					
					list($replacementLine,$portableLineData,$poPrimaryKeys,$poIndex) = $this->processColumnAttributes($dict,$a);
					if ($replacementLine)
					{
						$line = $replacementLine;
					}
					
					if ($portableLineData)
						$line .= ' ' . $portableLineData;
					
				}
												
				$this->parsedOptions[$indexObject->name]['cols'][] = $line;
			}
		}
	}
}	


?>