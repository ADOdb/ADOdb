--- Standard format for the unit testing
-- All drivers must have the same table and column structure
-- so that all tests run the same way
-- but this is native inserts so the column types may differ across databases

-- insertion_table will be built by createTable tests
DROP TABLE IF EXISTS insertion_table;
DROP TABLE IF EXISTS insertion_table_renamed;

DROP TABLE IF EXISTS testtable_3;
-- Must drop testtable_2 before testtable_1 because of foreign key constraints
DROP TABLE IF EXISTS testtable_2;
DROP TABLE IF EXISTS testtable_1;
-- This table will be built by XMLschema tests
DROP TABLE IF EXISTS testxmltable_1;

-- Testtable_1 is used to test the basic functionality of the meta functions
-- It has a variety of data types but contains no data
CREATE TABLE testtable_1 (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	varchar_field VARCHAR(20),
	datetime_field DATETIME,
	date_field DATE,
	integer_field INT(2) DEFAULT 0,
	decimal_field decimal(12.2) DEFAULT 0,
	boolean_field BOOLEAN DEFAULT 0,
	empty_field VARCHAR(240) DEFAULT '',
	number_run_field INT(4) DEFAULT 0
);
CREATE	UNIQUE INDEX vdx1 ON testtable_1 (varchar_field);
CREATE	UNIQUE INDEX vdx2 ON testtable_1 (integer_field,date_field);
CREATE UNIQUE INDEX vdx3 ON testtable_1 (number_run_field);

-- testtable_2 is used to test foreign keys
-- There is no data in this table
CREATE TABLE testtable_2 (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	integer_field INT(2) DEFAULT 0,
	date_field DATE,
	blob_field BLOB,
	FOREIGN KEY (integer_field,date_field) REFERENCES testtable_1(integer_field,date_field)
);

-- Testtable_3 is loaded with data for testing the cache and sql functions

CREATE TABLE testtable_3 (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	varchar_field VARCHAR(20),
	datetime_field DATETIME,
	date_field DATE,
	integer_field INT(2) DEFAULT 0,
	decimal_field decimal(12.2) DEFAULT 0,
	boolean_field BOOLEAN DEFAULT 0,
	empty_field VARCHAR(240) DEFAULT '',
	number_run_field INT(4) DEFAULT 0
);
CREATE	UNIQUE INDEX vdx31 ON testtable_3 (varchar_field);
CREATE UNIQUE INDEX vdx33 ON testtable_3 (number_run_field);

-- This table is used to test the quoting of table and field names
DROP TABLE IF EXISTS 'table_name';
CREATE TABLE 'table_name' (
	'id' INTEGER PRIMARY KEY AUTOINCREMENT,
	'column_name' VARCHAR(20)
);