<?php
/**
* Base Class for handling PRIMARY
*/
class metaOption_PRIMARY extends metaOption
{
	
	public function __construct()
	{
	    $this->text = 'PRIMARY';
		$this->primaryKey = true;
	}
}
?>
	