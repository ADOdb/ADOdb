<?php
	function ar_assert($obj, $cond)
	{
		global $err_count;
		$res = var_export($obj, true);
		return (strpos($res, $cond));
	}

	include_once('adodb.inc.php');
	include_once('adodb-active-record.inc.php');
	

	$db = NewADOConnection('mysql://northwind@localhost/northwind');
	$db->debug=0;
	ADOdb_Active_Record::SetDatabaseAdapter($db);

	echo "\n\n---------------------------------------------------------------------------\n";
	echo "Preparing database using SQL queries (creating 'people', 'children')\n";

	$db->Execute("DROP TABLE `people`");
	$db->Execute("DROP TABLE `children`");

	$db->Execute("CREATE TABLE `people` (
	                `id` int(10) unsigned NOT NULL auto_increment,
	                `name_first` varchar(100) NOT NULL default '',
	                `name_last` varchar(100) NOT NULL default '',
	                `favorite_color` varchar(100) NOT NULL default '',
	                PRIMARY KEY  (`id`)
	            ) ENGINE=MyISAM;
	           ");
	$db->Execute("CREATE TABLE `children` (
	                `id` int(10) unsigned NOT NULL auto_increment,
					`person_id` int(10) unsigned NOT NULL,
	                `name_first` varchar(100) NOT NULL default '',
	                `name_last` varchar(100) NOT NULL default '',
	                `favorite_pet` varchar(100) NOT NULL default '',
	                PRIMARY KEY  (`id`)
	            ) ENGINE=MyISAM;
	           ");
			   
	
	$db->Execute("insert into children (person_id,name_first,name_last,favorite_pet) values (1,'Jill','Lim','tortoise')");
	$db->Execute("insert into children (person_id,name_first,name_last) values (1,'Joan','Lim')");
	$db->Execute("insert into children (person_id,name_first,name_last) values (1,'JAMIE','Lim')");
			   
	// This class _implicitely_ relies on the 'people' table (pluralized form of 'person')
	class Person extends ADOdb_Active_Record
	{
		function __construct()
		{
			parent::__construct();
			$this->hasMany('children');
		}
	}
	// This class _implicitely_ relies on the 'children' table
	class Child extends ADOdb_Active_Record
	{
		function __construct()
		{
			parent::__construct();
			$this->belongsTo('person');
		}
	}
	// This class _explicitely_ relies on the 'children' table and shares its metadata with Child
	class Kid extends ADOdb_Active_Record
	{
		function __construct()
		{
			parent::__construct('children');
			$this->belongsTo('person');
		}
	}
	// This class _explicitely_ relies on the 'children' table but does not share its metadata
	class Rugrat extends ADOdb_Active_Record
	{
		function __construct()
		{
			parent::__construct('children', false, false, array('new' => true));
		}
	}
	
	echo "Inserting person in 'people' table ('John Lim, he likes lavender')\n";
	echo "---------------------------------------------------------------------------\n";
	$person = new Person();
	$person->name_first     = 'John';
	$person->name_last      = 'Lim';
	$person->favorite_color = 'lavender';
	$person->save(); // this save will perform an INSERT successfully

	$err_count = 0;

	echo "\n\n---------------------------------------------------------------------------\n";
	echo "person->Find('id=1') [Lazy Method]\n";
	echo "person is loaded but its children will be loaded on-demand later on\n";
	echo "---------------------------------------------------------------------------\n";
	$person5 = new Person();
	$people5 = $person5->Find('id=1');
	echo (ar_assert($people5, "'name_first' => 'John'")) ? "[OK] Found John\n" : "[!!] Find failed\n";
	echo (ar_assert($people5, "'favorite_pet' => 'tortoise'")) ? "[!!] Found relation when I shouldn't\n" : "[OK] No relation yet\n";
	foreach($people5 as $person)
	{
		foreach($person->children as $child)
		{
			if($child->name_first);
		}
	}
	echo (ar_assert($people5, "'favorite_pet' => 'tortoise'")) ? "[OK] Found relation: child\n" : "[!!] Missing relation: child\n";

	echo "\n\n---------------------------------------------------------------------------\n";
	echo "person->Find('id=1' ... ADODB_WORK_AR) [Worker Method]\n";
	echo "person is loaded, and so are its children\n";
	echo "---------------------------------------------------------------------------\n";
	$person6 = new Person();
	$people6 = $person6->Find('id=1', false, false, array('loading' => ADODB_WORK_AR));
	echo (ar_assert($people6, "'name_first' => 'John'")) ? "[OK] Found John\n" : "[!!] Find failed\n";
	echo (ar_assert($people6, "'favorite_pet' => 'tortoise'")) ? "[OK] Found relation: child\n" : "[!!] Missing relation: child\n";

	echo "\n\n---------------------------------------------------------------------------\n";
	echo "person->Find('id=1' ... ADODB_JOIN_AR) [Join Method]\n";
	echo "person and its children are loaded using a single query\n";
	echo "---------------------------------------------------------------------------\n";
	$person7 = new Person();
	// When I specifically ask for a join, I have to specify which table id I am looking up
	// otherwise the SQL parser will wonder which table's id that would be.
	$people7 = $person7->Find('people.id=1', false, false, array('loading' => ADODB_JOIN_AR));
	echo (ar_assert($people7, "'name_first' => 'John'")) ? "[OK] Found John\n" : "[!!] Find failed\n";
	echo (ar_assert($people7, "'favorite_pet' => 'tortoise'")) ? "[OK] Found relation: child\n" : "[!!] Missing relation: child\n";
	
	echo "\n\n---------------------------------------------------------------------------\n";
	echo "person->Load('people.id=1') [Join Method]\n";
	echo "Load() always uses the join method since it returns only one row\n";
	echo "---------------------------------------------------------------------------\n";
	$person2 = new Person();
	// Under the hood, Load(), since it returns only one row, always perform a join
	// Therefore we need to clarify which id we are talking about.
	$person2->Load('people.id=1');
	echo (ar_assert($person2, "'name_first' => 'John'")) ? "[OK] Found John\n" : "[!!] Find failed\n";
	echo (ar_assert($person2, "'favorite_pet' => 'tortoise'")) ? "[OK] Found relation: child\n" : "[!!] Missing relation: child\n";

	echo "\n\n---------------------------------------------------------------------------\n";
	echo "child->Load('children.id=1') [Join Method]\n";
	echo "We are now loading from the 'children' table, not from 'people'\n";
	echo "---------------------------------------------------------------------------\n";
	$ch = new Child();
	$ch->Load('children.id=1');
	echo (ar_assert($ch, "'name_first' => 'Jill'")) ? "[OK] Found Jill\n" : "[!!] Find failed\n";
	echo (ar_assert($ch, "'favorite_color' => 'lavender'")) ? "[OK] Found relation: person\n" : "[!!] Missing relation: person\n";

	echo "\n\n---------------------------------------------------------------------------\n";
	echo "child->Find('children.id=1' ... ADODB_WORK_AR) [Worker Method]\n";
	echo "---------------------------------------------------------------------------\n";
	$ch2 = new Child();
	$ach2 = $ch2->Find('id=1', false, false, array('loading' => ADODB_WORK_AR));
	echo (ar_assert($ach2, "'name_first' => 'Jill'")) ? "[OK] Found Jill\n" : "[!!] Find failed\n";
	echo (ar_assert($ach2, "'favorite_color' => 'lavender'")) ? "[OK] Found relation: person\n" : "[!!] Missing relation: person\n";

	echo "\n\n---------------------------------------------------------------------------\n";
	echo "kid->Find('children.id=1' ... ADODB_WORK_AR) [Worker Method]\n";
	echo "Where we see that kid shares relationships with child because they are stored\n";
	echo "in the common table's metadata structure.\n";
	echo "---------------------------------------------------------------------------\n";
	$ch3 = new Kid('children');
	$ach3 = $ch3->Find('children.id=1', false, false, array('loading' => ADODB_WORK_AR));
	echo (ar_assert($ach3, "'name_first' => 'Jill'")) ? "[OK] Found Jill\n" : "[!!] Find failed\n";
	echo (ar_assert($ach3, "'favorite_color' => 'lavender'")) ? "[OK] Found relation: person\n" : "[!!] Missing relation: person\n";

	echo "\n\n---------------------------------------------------------------------------\n";
	echo "kid->Find('children.id=1' ... ADODB_LAZY_AR) [Lazy Method]\n";
	echo "Of course, lazy loading also retrieve medata information...\n";
	echo "---------------------------------------------------------------------------\n";
	$ch32 = new Kid('children');
	$ach32 = $ch32->Find('children.id=1', false, false, array('loading' => ADODB_LAZY_AR));
	echo (ar_assert($ach32, "'name_first' => 'Jill'")) ? "[OK] Found Jill\n" : "[!!] Find failed\n";
	echo (ar_assert($ach32, "'favorite_color' => 'lavender'")) ? "[!!] Found relation when I shouldn't\n" : "[OK] No relation yet\n";
	foreach($ach32 as $akid)
	{
		if($akid->person);
	}
	echo (ar_assert($ach32, "'favorite_color' => 'lavender'")) ? "[OK] Found relation: person\n" : "[!!] Missing relation: person\n";
	
	echo "\n\n---------------------------------------------------------------------------\n";
	echo "rugrat->Find('children.id=1' ... ADODB_WORK_AR) [Worker Method]\n";
	echo "In rugrat's constructor it is specified that\nit must forget any existing relation\n";
	echo "---------------------------------------------------------------------------\n";
	$ch4 = new Rugrat('children');
	$ach4 = $ch4->Find('children.id=1', false, false, array('loading' => ADODB_WORK_AR));
	echo (ar_assert($ach4, "'name_first' => 'Jill'")) ? "[OK] Found Jill\n" : "[!!] Find failed\n";
	echo (ar_assert($ach4, "'favorite_color' => 'lavender'")) ? "[!!] Found relation when I shouldn't\n" : "[OK] No relation found\n";

	echo "\n\n---------------------------------------------------------------------------\n";
	echo "kid->Find('children.id=1' ... ADODB_WORK_AR) [Worker Method]\n";
	echo "Note how only rugrat forgot its relations - kid is fine.\n";
	echo "---------------------------------------------------------------------------\n";
	$ch5 = new Kid('children');
	$ach5 = $ch5->Find('children.id=1', false, false, array('loading' => ADODB_WORK_AR));
	echo (ar_assert($ach5, "'name_first' => 'Jill'")) ? "[OK] Found Jill\n" : "[!!] Find failed\n";
	echo (ar_assert($ach5, "'favorite_color' => 'lavender'")) ? "[OK] I did not forget relation: person\n" : "[!!] I should not have forgotten relation: person\n";
	
	echo "\n\n---------------------------------------------------------------------------\n";
	echo "rugrat->Find('children.id=1' ... ADODB_WORK_AR) [Worker Method]\n";
	echo "---------------------------------------------------------------------------\n";
	$ch6 = new Rugrat('children');
	$ch6s = $ch6->Find('children.id=1', false, false, array('loading' => ADODB_WORK_AR));
	$ach6 = $ch6s[0];
	echo (ar_assert($ach6, "'name_first' => 'Jill'")) ? "[OK] Found Jill\n" : "[!!] Find failed\n";
	echo (ar_assert($ach6, "'favorite_color' => 'lavender'")) ? "[!!] Found relation when I shouldn't\n" : "[OK] No relation yet\n";
	echo "\nLoading relations:\n";
	$ach6->belongsTo('person');
	$ach6->LoadRelations('person', 'order by id', 0, 2);
	echo (ar_assert($ach6, "'favorite_color' => 'lavender'")) ? "[OK] Found relation: person\n" : "[!!] Missing relation: person\n";

	echo "\n\n---------------------------------------------------------------------------\n";
	echo "Test suite complete.\n";
	echo "---------------------------------------------------------------------------\n";
?>
