<?php

include_once dirname(__FILE__) . '/../adodb.inc.php';

/* @var $db ADODB_pdo */
$db = ADONewConnection('pdo');
$db->connect(
	'mysql:host=localhost',
	'root',
	'',
	array('database' => 'northwind', \PDO::MYSQL_ATTR_FOUND_ROWS => true)
	#'northwind'
);
echo "Database: ", $db->database, "<br>\n";

$db->Execute("CREATE TEMPORARY TABLE `children` (
				`id` int(10) unsigned NOT NULL auto_increment,
				`name_first` varchar(100) NOT NULL default '',
				`name_last` varchar(100) NOT NULL default '',
				PRIMARY KEY  (`id`)
			)"
			);
$db->Execute("INSERT INTO children (name_first, name_last) VALUES ('Jill', 'Lim')");
$db->Execute("INSERT INTO children (name_first, name_last) VALUES ('Joan', 'Kim')");

$db->Execute('UPDATE children SET name_last = "Lim"');
echo "Affected Rows (2): ", $db->Affected_Rows(), "<br>\n";  // 2, would be 1 with MYSQL_ATTR_FOUND_ROWS = false
