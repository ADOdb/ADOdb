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
	
	public $options     = array();
	public $attributes  = array();
	
	const TABLE_OBJECT 		= 'table';
	const COLUMN_OBJECT 	= 'column';
	const INDEX_OBJECT 		= 'index';
	const INDEXITEM_OBJECT 	= 'index-item';
	const CONSTRAINT_OBJECT = 'constraint';
	const FOREIGNKEY_OBJECT = 'foreignkey';

	/*
	* Constructor
	* 
	* @param string  $name	    = '';
	* @param string  $platform	Optional platform
	* @param string  $type      Optional structure type 
	*
	* @return obj
	*/
	public function __construct($name, $platform='',$type='table')
	{
		
		$this->name 	= $name;
		$this->platform = $platform;
		$this->type		= $type;
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
		$child = 'index-item';
				
		$indexItemObject = $this->addChild($child,$columnName,'',$platform);
		return $indexItemObject;
	}
	
	/**
	* Adds a child to the object
	*
	* @param string  $child	  	  One of 'column,index,foreignkey,constraint';
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
		$o           = new metaObjectStructure($parentName,$platform,$child);
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
		{
			$containerNames = array(SELF::COLUMN_OBJECT=>'columns',
								   SELF::INDEX_OBJECT=>'indexes',
								   SELF::CONSTRAINT_OBJECT=>'constraints',
								   SELF::FOREIGNKEY_OBJECT=>'foreignkeys'
								   );
			$container 	 = $containerNames[$child];
			$this->{$container}[$parentName] = $o;
		}
		return $o;
	}
}
?>