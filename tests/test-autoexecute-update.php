<?php

require __DIR__ . "/../adodb.inc.php";

$db = NewADOConnection("sqlite3");
$db->Connect(":memory:");

$db->Execute("CREATE TABLE users(id primary key, name, year_of_birth)");
$db->Execute("INSERT INTO users VALUES (1, 'John', 2000)");
$db->Execute("INSERT INTO users VALUES (2, 'Jane', 1981)");

$new_value = 2000;

$db->autoExecute(
    "users",
    ["year_of_birth" => $new_value],
    "UPDATE",
    "id = 2",
    false
);

$updated_value = $db->GetOne("SELECT year_of_birth FROM users WHERE id = 2");

if ($updated_value != $new_value) {
    die("ERROR: updated_value $updated_value != new_value $new_value");
}

echo "Finished.\n";
