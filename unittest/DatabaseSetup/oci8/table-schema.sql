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

-- These sequences are used to auto-increment the primary keys
DROP SEQUENCE IF EXISTS testtable_1_seq;
DROP SEQUENCE IF EXISTS testtable_2_seq;
DROP SEQUENCE IF EXISTS testtable_3_seq;
DROP SEQUENCE IF EXISTS table_name_seq;


-- Testtable_1 is used to test the basic functionality of the meta functions
-- It has a variety of data types but contains no data
CREATE TABLE testtable_1 (
	id INTEGER NOT NULL,
    varchar_field VARCHAR(20),
    datetime_field VARCHAR(20),
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


CREATE SEQUENCE testtable_1_seq
    INCREMENT BY 1
    START WITH 1;

-- This statement has an extraneous ; at end to force 
-- the procedure to be created in Oracle. It will be stripped
-- by the schema loader
CREATE OR REPLACE TRIGGER testable_1_t BEFORE insert ON testtable_1 FOR EACH ROW WHEN (NEW.id IS NULL OR NEW.id=0) BEGIN select testtable_1_seq.nextval into :new.id from dual; END; ;

-- testtable_2 is used to test foreign keys
-- There is no data in this table
CREATE TABLE testtable_2 (
	id INTEGER NOT NULL,
	integer_field SMALLINT NOT NULL,
	date_field DATE,
	blob_field BLOB,
    tt_id INTEGER NOT NULL
);
CREATE UNIQUE INDEX vdx21 ON testtable_2 (integer_field,date_field);
 
ALTER TABLE testtable_2 ADD CONSTRAINT vdx21
    FOREIGN KEY (tt_id)
    REFERENCES testtable_1 (id);

CREATE SEQUENCE testtable_2_seq
    INCREMENT BY 1
    START WITH 1;

CREATE OR REPLACE TRIGGER testable_2_t BEFORE insert ON testtable_2 FOR EACH ROW WHEN (NEW.id IS NULL OR NEW.id=0) BEGIN select testtable_2_seq.nextval into :new.id from dual; END; ;

-- Testtable_3 is loaded with data for testing the cache and sql functions

CREATE TABLE testtable_3 (
id INTEGER NOT NULL,
    varchar_field VARCHAR(20),
    datetime_field VARCHAR(20),
    date_field DATE,
    integer_field SMALLINT NOT NULL,
    decimal_field NUMBER(12,2),
    boolean_field NUMBER(1,0),
    empty_field VARCHAR(240) DEFAULT '',
    number_run_field SMALLINT NOT NULL
);

CREATE	UNIQUE INDEX vdx31 ON testtable_3 (varchar_field);
CREATE	UNIQUE INDEX vdx33 ON testtable_3 (number_run_field);

CREATE SEQUENCE testtable_3_seq
    INCREMENT BY 1
    START WITH 1;

CREATE OR REPLACE TRIGGER testable_3_t BEFORE insert ON testtable_3 FOR EACH ROW WHEN (NEW.id IS NULL OR NEW.id=0) BEGIN select testtable_3_seq.nextval into :new.id from dual; END; ;
-- This table is used to test the quoting of table and field names
-- It uses a reserved word as the table name and column names
DROP TABLE IF EXISTS "table_name";
CREATE TABLE "table_name" (
	"id" INTEGER NOT NULL,
	"column_name" VARCHAR(20)
);