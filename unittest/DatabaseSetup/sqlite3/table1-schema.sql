-- Standard format for the unit testing
-- All drivers must have the same table and column structure
-- so that all tests run the same way
DROP TABLE IF EXISTS insertion_table;
DROP TABLE IF EXISTS insertion_table_renamed;
DROP TABLE IF EXISTS testtable_2;
DROP TABLE IF EXISTS testtable_1;
CREATE TABLE testtable_1 (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	varchar_field VARCHAR(20),
	datetime_field DATETIME,
	date_field DATE,
	integer_field INT(2) DEFAULT 0,
	decimal_field decimal(12.2) DEFAULT 0,
	empty_field VARCHAR(240) DEFAULT ''
);
CREATE	UNIQUE INDEX vdx1 ON testtable_1 (varchar_field);
CREATE	UNIQUE INDEX vdx2 ON testtable_1 (integer_field,date_field);

CREATE TABLE testtable_2 (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	integer_field INT(2) DEFAULT 0,
	date_field DATE,
	FOREIGN KEY (integer_field,date_field) REFERENCES testtable_1(integer_field,date_field)
);