-- Standard format for the unit testing
-- All drivers must have the same table and column structure
-- so that all tests run the same way
DROP TABLE IF EXISTS insertion_table;
DROP TABLE IF EXISTS insertion_table_renamed;
DROP TABLE IF EXISTS testtable_2;
DROP TABLE IF EXISTS testtable_1;
CREATE TABLE testtable_1 (
	id INT IDENTITY(1,1) PRIMARY KEY,
	varchar_field VARCHAR(20),
	datetime_field DATETIME,
	date_field DATE,
	integer_field SMALLINT DEFAULT 0,
	decimal_field DECIMAL(12,2) DEFAULT 0,
	boolean_field BIT DEFAULT 0,
	empty_field VARCHAR(240) DEFAULT '',
);
CREATE	unique index vdx1 ON testtable_1 (varchar_field);
CREATE	unique index vdx2 ON testtable_1 (integer_field,date_field);

CREATE TABLE testtable_2 (
   id INT IDENTITY(1,1) PRIMARY KEY,
    integer_field SMALLINT DEFAULT 0,
	date_field DATE,
    FOREIGN KEY (integer_field,date_field) REFERENCES testtable_1(integer_field,date_field)
);


-- This table is used to test the quoting of table and field names
DROP TABLE IF EXISTS [table_name];
CREATE TABLE [table_name] (
	[id] IINTEGER PRIMARY KEY AUTOINCREMENT,
	[column_name] VARCHAR(20)
);
