-- Standard format for the unit testing
-- All drivers must have the same table and column structure
-- so that all tests run the same way
DROP TABLE IF EXISTS insertion_table;
DROP TABLE IF EXISTS insertion_table_renamed;
DROP TABLE IF EXISTS testtable_2;
DROP TABLE IF EXISTS testtable_1;
CREATE TABLE testtable_1 (
	id INTEGER NOT NULL GENERATED ALWAYS AS IDENTITY (START WITH 1 INCREMENT BY 1),
	varchar_field VARCHAR(20),
	datetime_field DATETIME,
	date_field DATE,
	integer_field SMALLINT NOT NULL DEFAULT 0,
	decimal_field decimal(12,2) DEFAULT 0,
    empty_field VARCHAR(240) DEFAULT '',
    PRIMARY KEY (id)
);
CREATE	UNIQUE INDEX vdx1 ON testtable_1 (varchar_field);
CREATE	UNIQUE INDEX vdx2 ON testtable_1 (integer_field,date_field);

-- Currently missing support for composite foreign key tests in DB2 driver
-- This is solely because I don't know how to create a composite foreign key in DB2
CREATE TABLE testtable_2 (
	id INTEGER NOT NULL GENERATED ALWAYS AS IDENTITY (START WITH 1 INCREMENT BY 1),
	integer_field SMALLINT NOT NULL DEFAULT 0,
	date_field DATE,
    PRIMARY KEY (id)
);