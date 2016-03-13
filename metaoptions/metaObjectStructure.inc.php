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
	* @param string  $name	= '';
	* @param string  $platform	= '';
	*
	* @return obj
	*/
	public function __construct($type,$name, $platform='')
	{
		
		$this->name 	= $name;
		$this->platform = $platform;
		$this->type		= $type;
		$this->action   = 0;
	
	}
	
	/**
	* Adds an attribute to one of the following: table,column,index
	*
	* @param string  $attribute	  One of 'table,column,index';
	* @param string  $parentName  parent name, must exist
	* @param string  $value		  either string, associative or numeric array
	* @param string  $platform	  either string or provider or datatype
	*
	* return bool, success or failure
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
		
		$o           = new metaObjectStructure($child,$parentName,$platform);
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
	
	/**
	* Adds an column to the object
	*
	* @param string  $columnName  parent name, must not already exist
	* @param string  $columnType  either string, associative or numeric array
	* @param string  $platform	  either string or provider or datatype
	*
	* @return object The child object
	*/
	public function addColumn($columnName,$columnType,$platform='')
	{
		
		$child = 'column';
		if (!isset($this->columns))
			$this->columns = array();
		$columnObject = $this->addChild($child,$columnName,$columnType,$platform);
		return $columnObject;

	}
			
	/**
	* Adds an index to the object
	*
	* @param string  $indexName  parent name, must not already exist
	* @param string  $platform	  either string or provider or datatype
	*
	* @return object The child object
	*/
	public function addIndex($indexName,$platform='')
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
	public function addIndexItem($columnName,$platform='')
	{
		$child = 'index-item';
				
		$indexItemObject = $this->addChild($child,$columnName,'',$platform);
		return $indexItemObject;
	}
}
?>