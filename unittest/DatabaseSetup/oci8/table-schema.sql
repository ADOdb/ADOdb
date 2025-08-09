-- Standard format for the unit testing
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


CREATE TABLE testtable_1 (
	id INTEGER NOT NULL,
    varchar_field VARCHAR(20),
    datetime_field TIMESTAMP,
    date_field DATE,
    integer_field SMALLINT NOT NULL,
    decimal_field NUMBER(12,2),
    boolean_field NUMBER(1,0),
    empty_field VARCHAR(240) DEFAULT '',
    number_run_field SMALLINT NOT NULL
);
CREATE	UNIQUE INDEX vdx1 ON testtable_1 (varchar_field);
CREATE	UNIQUE INDEX vdx2 ON testtable_1 (integer_field,date_field);
CREATE	UNIQUE INDEX vdx3 ON testtable_1 (number_run_field);



CREATE TABLE testtable_2 (
	id INTEGER NOT NULL,
	integer_field SMALLINT NOT NULL,
	date_field DATE,
	blob_field BLOB
    
);

CREATE TABLE testtable_3 (
id INTEGER NOT NULL,
    varchar_field VARCHAR(20),
    datetime_field TIMESTAMP,
    date_field DATE,
    integer_field SMALLINT NOT NULL,
    decimal_field NUMBER(12,2),
    boolean_field NUMBER(1,0),
    empty_field VARCHAR(240) DEFAULT '',
    number_run_field SMALLINT NOT NULL
);
CREATE	UNIQUE INDEX vdx31 ON testtable_3 (varchar_field);
CREATE	UNIQUE INDEX vdx33 ON testtable_3 (number_run_field);

DROP TABLE IF EXISTS "table_name";
CREATE TABLE "table_name" (
	"id" INTEGER NOT NULL,
	"column_name" VARCHAR(20)
);