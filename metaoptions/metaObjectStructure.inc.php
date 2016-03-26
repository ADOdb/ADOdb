<?php
/**
* The metaObjectStructure, for maniplulationg table srtuctures
*/
  
class metaObjectStructure
{
	/*
	* Available object items
	*
	* table, columns, column, column-attribute, indexes, index, index-item, constraint, foreignkey	
	*/
	
	public $type		= '';
	public $value		= '';
	public $platform	= '';
	public $newName	    = '';
	
	public $options     = array();
	public $attributes  = array();
	
	const TABLE_OBJECT 		= 'table';
	const COLUMN_OBJECT 	= 'column';
	const INDEX_OBJECT 		= 'index';
	const INDEXITEM_OBJECT 	= 'index-item';
	
	private $statusCode		= 0;
	private $objectList     = array('table','column','index','index-item');
	
	private $dict;
	
	/*
	* Constructor
	*
    * @param obj	 $dict      Handle to the data dictionary object	
	* @param string  $name	    The name of the object
	* @param string  $platform	Optional platform
	* @param string  $type      Optional structure type 
	*
	* @return obj
	*/
	public function __construct($dict,$name, $platform='',$type='table')
	{
		$this->dict		= $dict;
		$this->name 	= strtolower($name);
		$this->platform = strtolower($platform);
		$this->type		= strtolower($type);
	
		if (!in_array($this->type,$this->objectList))
		{
			$this->statusCode    = 1;
			if ($this->dict->connection->debug)
			{
				ADOconnection::outp(
				'metaObjectStructure type ' . $this->type . ' '. 
				'is not a valid type'
				);
			}
			return false;
		}
		
		if (!is_object($dict) || !isset($dict->connection))
		{
			
			/*
			* Cannot tell if in debug mode because no connection available
			* so always do this
			*/
			$this->statusCode    = 1;
			ADOconnection::outp(
			'Parameter 1 of metaObjectStructure construction must '. 
			'be a handle to a valid ADOdb data dictionary object'
			);
			
			return false;
		}
	}
	
	/**
	* Sets the new name of the object if a rename action is to be called
	*
	* @param	string	$newName
	*
	* return the current object so the commands can be chained
	*/
	public function setNewName($newName)
	{
		if ($this->type != 'table' && $this->type != 'column')
		{
			$this->statusCode    = 1;
			if ($this->dict->connection->debug)
			{
				ADOconnection::outp(
				'metaObjectStructure method setNewName ' .
				'only available to objects of type table or column'
				);
			}
			return false;
		}
		
		$this->newName = $newName;
		return $this;
	}
	
	/**
	* Adds an unvalidated attribute to the current object
	*
	* @param string  $value		  either string, associative or numeric array
	* @param string  $platform	  either string or provider or datatype
	*
	* return the current object so the commands can be chained
	*/
	public function addAttribute($value,$platform='')
	{
	
		if (!is_string($value) && !is_array($value))
		{
			$this->statusCode    = 1;
			if ($this->dict->connection->debug)
			{
				ADOconnection::outp(
				'Attributes must be either a string or an array '
				);
			}
			return false;
		}
		
		$o = new metaElementStructure;
		$o->type	 = $this->type;
		$o->name     = $this->name;
		$o->value    = $value;
		$o->platform = $platform;
		$this->attributes[] = $o;
		
		return $this;
	}

	/**
	* Adds an column to the object
	*
	* @param string  $columnName  Column name, must not already exist
	* @param string  $columnType  either string, associative or numeric array
	* @param string  $platform	  either string or provider or datatype
	*
	* @return object The child object
	*/
	public function addColumnObject($columnName,$columnType='',$platform='')
	{
		
		if ($this->type != 'table')
		{
			$this->statusCode    = 1;
			if ($this->dict->connection->debug)
			{
				ADOconnection::outp(
				'metaObjectStructure objects of type ' .
				'column can only be added to parent' .
				'objects of type table'
				);
			}
			return false;
		}
		
		$child = 'column';
		if (!isset($this->columns))
			$this->columns = array();
		
		if (isset($this->columns[$columnName]))
		{
			return false;
		}
		
		$columnObject = $this->addChild($child,$columnName,$columnType,$platform);
		return $columnObject;

	}
			
	/**
	* Adds an index to the object
	*
	* @param string  $indexName  parent name, must not already exist, if
	*                            it does, it will be discarded
	* @param string  $platform	 either null or provider or datatype
	*
	* @return object The child object
	*/
	public function addIndexObject($indexName,$platform='')
	{
		if ($this->type != 'table')
		{
			$this->statusCode    = 1;
			if ($this->dict->connection->debug)
			{
				ADOconnection::outp(
				'metaObjectStructure objects of type ' .
				'index can only be added to parent' .
				'objects of type table'
				);
			}
			return false;
		}
		
		$child = 'index';
		if (!isset($this->indexes))
			$this->indexes = array();
		
		$indexObject = $this->addChild($child,$indexName,'',$platform);
		return $indexObject;
	}
	
	/**
	* Adds a column to the index object
	*
	* @param string  $indexName  parent name, must not already exist
	* @param string  $platform	  either string or provider or datatype
	*
	* @return object The child object
	*/
	public function addIndexItemObject($columnName,$platform='')
	{
		
		if ($this->type != 'index')
		{
			$this->statusCode    = 1;
			if ($this->dict->connection->debug)
			{
				ADOconnection::outp(
				'metaObjectStructure objects of type ' .
				'index-item can only be added to parent' .
				'objects of type index'
				);
			}
			return false;
		}
		$child = 'index-item';
				
		$indexItemObject = $this->addChild($child,$columnName,'',$platform);
		return $indexItemObject;
	}
	
	/*
	* Returns the current error status
	*/
	final public function getErrorStatus()
	{
		return $this->statusCode;
	}
	
	/**
	* Adds a child to the object
	*
	* @param string  $child	  	  One of 'column,index,index-item';
	* @param string  $parentName  parent name, must not already exist
	* @param string  $value		  either string, associative or numeric array
	* @param string  $platform	  either string or provider or datatype
	*
	* @return int	The index of the child in the parent
	*/
	private function addChild($child,$parentName,$value,$platform='')
	{
		$attribute = strtolower($child);
				
		if ($attribute == 'index' && isset($this->indexes[$parentName]))
			/*
		    * We are adding another column to an index
			*/
			return $this->indexes[$parentName];
		
		/*
		* We recursively add a new structure to ourself
		*/
		$o           = new metaObjectStructure($this->dict,$parentName,$platform,$child);
		$o->type	 = $attribute;
		$o->name     = $parentName;
		$o->value    = $value;
		$o->platform = $platform;
		
		if ($attribute == 'index')
		{
			$o->columns = array();
			$this->indexes[$parentName] = $o;
		}	
		
		else if ($attribute == 'index-item')	
			$this->columns[] = $o;
		
		else
			$this->columns[$parentName] = $o;
		
		return $o;
	}
	
}
?>