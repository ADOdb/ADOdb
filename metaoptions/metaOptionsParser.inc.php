<?php
/**
* Parses the metaObjectStructure object
*
* This allows data from the object to be extracted as necessary and sent
* into the original meta functions
*/
final class metaOptionsParser
{	
	/*
	* This represents an array of items, understandable by the old functions
	* the value may change dependant on the type of object (column, index etc)
	*/
	private   $parsedOptions = array();
	
	/*
	* This represents the actual name that is assigned to the object item
	*/
	private	  $itemName		 = '';
	
	/*
	* This represents the type that is assigned to the object item
	*/
	private	  $itemType		 = '';
	
	/*
	* A handle to the data dictionary object
	*/
	private   $dict			 = false;
	
	/*
	* Holds the parsed column information in a table type structure
	*/
	private   $fieldData	 = array();
	
	
	/*
	* Holds the parsed index information in a table type structure
	*/
	private   $indexData	 = array();
	
	/*
	* Holds the object attributes
	*/
	private $attributes		 = array();
	
	/*
	* Holds the new name of the object for renaming operations
	*/
	private $newName		 = '';
	
	
	
	/**
	* Parses the provided metaobjectstructure object
	*
	* @param	obj		$dict	The parent data dictionary structure
	* @param	obj		$metaObject	The metaObject structure of one of the
	*								returned types
	*
	* @return	obj
	*/
	public function __construct($dict,$metaObject)
	{
	
				
		/*
		* We need this to determine platform options
		*/
		$this->dict = $dict;
		
		$this->itemType = $metaObject->type;
		if (isset($metaObject->newName))
			$this->newName  = $metaObject->newName;
		
		switch ($metaObject->type)
		{
			case 'table':
			$this->parseTableObject($metaObject);
			break;

			case 'column':
			$this->parseColumnObject($metaObject);
			break;
			
			case 'columns':
			$this->parseColumnsObject($metaObject);
			break;
			
			case 'indexes':
			$this->parseIndexObject($metaObject);
			break;
			
			
		}
	}
	
	
	/**
	* Returns a list of items that reflect a table metaObjectStructure
	*/
	public function getParsedTable()
	{
		return array($this->itemName,$this->options);
	}
	
	/**
	* Returns an array of objects based on the type of object, in a form that
	* is acceptable to the legacy create/drop/change functions
	*
	* @return mixed
	*/
	final public function getLegacyParsedOptions()
	{
		if ($this->itemType == 'table')
			return $this->getParsedTable();
		
		return $this->parsedOptions;
	}
	
	final public function getTableColumnsObject()
	{
		return $this->fieldData;
	}
	
	/**
	* Returns an array of all the column names in the current Object
	*
	* @return array
	*/
	final public function getTableColumnNamesArray()
	{
		return array_keys($this->fieldData->options);
	}
	
	final public function getTableIndexesObject()
	{
		return $this->indexData;
	}
	
	/**
	* Returns a column object matching the provided name
	*
	* @return object
	*/	
	final public function getColumnObjectByName($columnName)
	{
		if (isset($this->fieldData->options[$columnName]))
			return $this->fieldData->options[$columnName];
	}
	
	/**
	* Finds the object new name, if set
	*
	* @return string
	*/
	final public function getObjectNewName()
	{
		return $this->newName;
	}
	
	/**
	* Finds an attribute of the current object by name
	*
	* @param	string	$attribute	The value to find
	* @return	string	The value if found or empty
	*/
	public function getObjectAttribute($attribute)
	{
		
		$matchingKeys = array();
		foreach($this->attributes as $platform=>$option)
		{
			if (strcmp($platform,$this->dict->connection->dataProvider) <> 0
			&&  $platform)
			continue;
			
			/*
			* Now loop through the remaining attributes and find the object with the
			* key value that matches
			*/
			foreach ($option as $key=>$value)
			{
				if (is_array($value))
				{
				    if(isset($value[$attribute]))
						return $value[$attribute];
					continue;
				}
				
				if (strcasecmp($value,$attribute) == 0)
					return $value;
				
			}
		}
	}
			
	/**
	* Takes any attribute objects associated with an object and parses it
	*
	* @param	obj		$attributes	An array of metaElementStructures
	*
	* @return	array
	*/
	protected function processAttributes($attributes)
	{
		$primaryKeys     = array();
		$indexes 	     = array();
		$line            = '';
		$replacementLine = '';
		
		$attributeValue = $attributes->value;
		
		/*
		* $attribute may be an array or a string, to simplify processing,
		* we convert as necessary
		*/
		if (!is_array($attributeValue))
			$attributeValue = (array)$attributeValue;

		foreach($attributeValue as $key=>$value)
		{
			
			$arrayToPass = array($key=>$value);
			if (is_numeric($key))
			{
				$key 		 = $value;
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
				$optionHandler = new $loader($this->dict,$value);
			}
			catch (Exception $e)
			{
				$loader = 'metaOption_CUSTOM';
				$optionHandler = new $loader($this->dict,$value,$key);
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
	
	/**
	* Takes a table object and creates an old format array of data
	*
	* @param	object	$tableObject	A metaObjectStructure representing a table
	*/
	private function parseTableObject($tableObject)
	{
		/*
		* Stage 1 of development, we break the object down into the
		* table name, fields and options
		*/
	
		$this->itemName = (string)$tableObject->name;
		
		if (!isset($tableObject->attributes))
			$this->object = array();
		
		if (!isset($tableObject->options))
			$tableObject->options = array();
		
		if (!isset($tableObject->columns))
			$tableObject->columns = array();
		
		if (!isset($tableObject->indexes))
			$tableObject->indexes = array();
		
		$this->fieldData = new metaElementStructure;
		$this->fieldData->type = 'columns';
		$this->fieldData->options = $tableObject->columns;
		
		$this->indexData = new metaElementStructure;
		$this->indexData->type = 'indexes';
		$this->indexData->options = $tableObject->indexes;
		
		
		$t = array();
		foreach ($tableObject->attributes as $o)
		{
			
			if ($o->platform)
				$platform = strtoupper($o->platform);
			else
				$platform = 'DEFAULT';
			
			if (!isset($t[$platform]))
				$t[$platform] = array();
			
			$t[$platform][] = $o->value;
			
		}
		
		$this->options = $t;
	
	}
	
	/**
	* Takes a columns object and creates an old format array of data
	*
	* @param	object	$columnsObject	A metaObjectStructure representing columns
	*/
	private function parseColumnsObject($columnsObject)
	{
		
		$lines = array();
		$pkey  = array();
		$idxs  = array();
		/*
		* The actual columns are held in the options array of the 'columns'
		* object
		*/
		$columns = $columnsObject->options;
		
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
				
				if ($a->platform && strcasecmp($a->platform,$this->dict->connection->dataProvider) <> 0)
					/*
				     * Is this a Platform-specific option to be ignored
					 */
					continue;
				
				/*
				* Convert any column attributes from objects to strings
				*/
				list($replacementLine,$portableLineData,$poPrimaryKeys,$poIndex) = $this->processAttributes($a);
				if ($replacementLine)
				{
					/*
					* The returned data completely replaces what we have
					* now
					*/
					$line = $replacementLine;
				}
				
				
				if ($portableLineData)
					/*
				     * Add the attribute to the string representing the 
					 * column
					 * @example add 'NOTNULL' to the column definition
					 */
					$line .= ' ' . $portableLineData;
				
				/*
				* If the column is part of a primary key, add it to the 
				* primary key list
				*/
				$pkey  = array_merge($pkey,$poPrimaryKeys);
				
				/*
				* If the column is part of an index, add it to the 
				* index list
				* @todo does this work?
				*/
				$idxs  = array_merge($idxs,$poIndex); 
				
			}
			
			/*
			* Add the string line to the array, which is understandable by the
			* old functions
			*/
			$lines[$fieldName] = $line;
		}
		$this->parsedOptions = array($lines,$pkey,$idxs);
	}
	
	/**
	* Parses an individual column object
	*
	* @param	obj	$metaObject
	*/
	private function parseColumnObject($metaObject)
	{
		
		$this->attributes = $metaObject->attributes;
		
		$fieldName = $metaObject->name;
		/*
		* Get the type first because we need that for all the other
		* options
		*/
		if (isset($metaObject->value))
			$fieldType = $metaObject->value;
		else
			$fieldType = '';
		
		$line = $fieldType;
		
		foreach ($metaObject->attributes as $a)
		{
			
			if ($a->platform && strcasecmp($a->platform,$this->dict->connection->dataProvider) <> 0)
				/*
				 * Is this a Platform-specific option to be ignored
				 */
				continue;
			
			/*
			* Convert any column attributes from objects to strings
			*/
			list($replacementLine,$portableLineData,$poPrimaryKeys,$poIndex) = $this->processAttributes($a);
			if ($replacementLine)
			{
				/*
				* The returned data completely replaces what we have
				* now
				*/
				$line = $replacementLine;
			}
			
			
			if ($portableLineData)
				/*
				 * Add the attribute to the string representing the 
				 * column
				 * @example add 'NOTNULL' to the column definition
				 */
				$line .= ' ' . $portableLineData;
			
			/*
			* If the column is part of a primary key, add it to the 
			* primary key list
			* This value is currently discarded
			*/
			$pkey  = $poPrimaryKeys;
			
			/*
			* If the column is part of an index, add it to the 
			* index list
			* @todo does this work?
			* This value is currently discarded
			*/
			$idxs  = $poIndex; 
		}
		$this->parsedOptions = $line;
	}
	
	
	/*
	* Parse an object that represents an index
	*
	* @param	object	$metaObject	The index object
	* @return null
	*/
	private function parseIndexObject($metaObject)
	{
		
		$this->parsedOptions = array();
		foreach ($metaObject->options as $indexObject)
		{
			if ($indexObject->platform 
			&& strcasecmp($indexObject->platform,
						  $this->dict->connection->dataProvider) <> 0)
				continue;	
			
			$this->parsedOptions[$indexObject->name] = array('cols'=>array(),
															 'opts'=>array());
			
			foreach ($indexObject->columns as $columnObject)
			{
				$line = $columnObject->name;
				foreach ($columnObject->attributes as $a)
				{
				
					if ($a->platform && strcasecmp($a->platform,$this->dict->connection->dataProvider) <> 0)
						continue;
					
					list($replacementLine,
						 $portableLineData,
						 $poPrimaryKeys,
						 $poIndex) = $this->processAttributes($a);
					if ($replacementLine)
					{
						$line = $replacementLine;
					}
					
					if ($portableLineData)
						$line .= ' ' . $portableLineData;
					
				}
												
				$this->parsedOptions[$indexObject->name]['cols'][$columnObject->name] = $line;
			}
		}
	}
}	

?>