<?php

	include('../adodb.inc.php');
	include('../adodb-active-record.inc.php');
	
	// uncomment the following if you want to test exceptions
	#if (PHP_VERSION >= 5) include('../adodb-exceptions.inc.php');

	$db = NewADOConnection('mysql://root@localhost/northwind');
	$db->debug=1;
	ADOdb_Active_Record::SetDatabaseAdapter($db);

	$db->Execute("CREATE TEMPORARY TABLE `persons` (
	                `id` int(10) unsigned NOT NULL auto_increment,
	                `name_first` varchar(100) NOT NULL default '',
	                `name_last` varchar(100) NOT NULL default '',
	                `favorite_color` varchar(100) NOT NULL default '',
	                PRIMARY KEY  (`id`)
	            ) ENGINE=MyISAM;
	           ");
			   
	class Person extends ADOdb_Active_Record{}
	$person = new Person();
	
	echo "<p>Output of <b>getAttributeNames</b>: ";
	var_dump($person->getAttributeNames());
	
	/**
	 * Outputs the following:
	 * array(4) {
	 *    [0]=>
	 *    string(2) "id"
	 *    [1]=>
	 *    string(9) "name_first"
	 *    [2]=>
	 *    string(8) "name_last"
	 *    [3]=>
	 *    string(13) "favorite_color"
	 *  }
	 */
	
	$person = new Person();
	$person->nameFirst = 'Andi';
	$person->nameLast  = 'Gutmans';
	$person->save(); // this save() will fail on INSERT as favorite_color is a must fill...
	
	
	$person = new Person();
	$person->name_first     = 'Andi';
	$person->name_last      = 'Gutmans';
	$person->favorite_color = 'blue';
	$person->save(); // this save will perform an INSERT successfully
	
	echo "<p>The <b>Insert ID</b> generated:"; print_r($person->id);
	
	/**
	 * Outputs the following:
	 * string(1)
	 */
	
	
	$person->favorite_color = 'red';
	$person->save(); // this save() will perform an UPDATE
	
	
	// load record where id=1 into a new ADODB_Active_Record
	$person2 = new Person();
	$person2->Load('id=1');
	
	var_dump($person2);

?>