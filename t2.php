<?php
include 'adodb.inc.php';

// connect
$db = ADONewConnection("mysqli");
$db->connect("localhost", "root", "password", "test");

// output some details
$fetchModeMap = [
	ADODB_FETCH_DEFAULT => "default (driver specific)",
	ADODB_FETCH_NUM => "numeric",
	ADODB_FETCH_ASSOC => "associative",
	ADODB_FETCH_BOTH => "both",
];

$ADODB_FETCH_MODE = (int)$argv[1] ?? ADODB_FETCH_DEFAULT;

$extension_yn = !empty($GLOBALS["ADODB_EXTENSION"]) ? "yes" : "no";
$php_version = phpversion();
echo "extension: {$extension_yn}\n";
echo "fetchmode: {$fetchModeMap[$GLOBALS["ADODB_FETCH_MODE"]]}\n";
echo "php version: {$php_version}\n\n";

// temp table for example
$tmp_table = $db->execute("
	CREATE TEMPORARY TABLE `lookup_example` (
	  `name` VARCHAR(45) NOT NULL,
	  `value` VARCHAR(45) NULL,
	  `id` INT NOT NULL AUTO_INCREMENT,
	  PRIMARY KEY (`id`)
    ) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4
");

// populate temp table with dummy results
foreach ([
			 "NAME1" => "Name1 description",
			 "NAME2" => "Name2 description",
			 "NAME3" => "Name3 description",
		 ] as $name => $value) {
	$db->execute("INSERT INTO `lookup_example` (`name`, `value`) VALUES ({$db->qstr($name)}, {$db->qstr($value)})");
}

// normal execution and access of associative values
$results = $db->execute("SELECT `name`, `value` FROM `lookup_example`");

print_r($results->getRows());
foreach ($results as $result) {
	if (array_key_exists('name', $result)) {
		$name = $result['name'];
		$value = $result['value'];
	}
	else {
		[$name, $value] = $result;
	}

	echo "{$name}: {$value}\n";
}

// move back to start and pull associative on recordset
$results->move(0);
echo "\n";
print_r($results->getAssoc(false, false));
$results->move(0);
print_r($results->getAssoc(false, true));
$results->move(0);
print_r($results->getAssoc(true, false));
$results->move(0);
print_r($results->getAssoc(true, true));
