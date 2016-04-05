<?php
/**
* Base Class for metaOption
*
*/
class metaOption
{
	protected $text;
	protected $primaryKey;
	protected $indexes;
	protected $replacementBase;
	/*
	* This is the default priority, it means that the option will be 
	* processed on a fifo basis
	*/
	protected $priority = 10;
	/*
	* This tells the metaOptionsParser to use the legacy parser to process
	* the attribute. This is the default behaviour right now.
	*/
	protected $legacyParser = true;
	
	/**
	  * The ADOconnection class is available to the class as 
      * func_get_args[0] in the constructor
	  * The value of the option is available at func_get_args[1]
	  * The key of the option is available at func_get_args[2]
	  *
	  * @return obj   The class ofject
	  */
	public function __construct(){}
	
	/**
	* Returns the constructed string
	*
	* @return string
	*/
	final public function getAttributes()
	{
		return array($this->replacementBase,$this->priority,$this->text,$this->primaryKey,$this->indexes);
	}
	
	/**
	* Returns whether the legacy parser should be used to handle the attribute
	*
	* @return bool
	*/
	final public function useLegacyParser()
	{
		return $this->legacyParser;
	}
}
?>