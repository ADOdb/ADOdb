DROP TABLE IF EXISTS insertion_table;
DROP TABLE IF EXISTS insertion_table_renamed;

DROP TABLE IF EXISTS testtable_3;

DROP TABLE IF EXISTS testtable_2;
DROP TABLE IF EXISTS testtable_1;

DROP TABLE IF EXISTS testxmltable_1;

DROP SEQUENCE testtable_3_seq;
DROP SEQUENCE table_name_seq;

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

CREATE OR REPLACE TRIGGER testable_1_t BEFORE insert ON testtable_1 FOR EACH ROW WHEN (NEW.id IS NULL OR NEW.id=0) BEGIN select testtable_1_seq.nextval into :new.id from dual; END; ;


CREATE TABLE testtable_2 (
	id INTEGER NOT NULL,
	integer_field SMALLINT NOT NULL,
	date_field DATE,
	blob_field BLOB
);
CREATE UNIQUE INDEX vdx21 ON testtable_2 (integer_field,date_field);
 
ALTER TABLE testtable_2 ADD CONSTRAINT fk_column
    FOREIGN KEY (integer_field,date_field)
    REFERENCES testtable_1 (integer_field,date_field);

CREATE SEQUENCE testtable_2_seq
    INCREMENT BY 1
    START WITH 1;

CREATE OR REPLACE TRIGGER testable_2_t BEFORE insert ON testtable_2 FOR EACH ROW WHEN (NEW.id IS NULL OR NEW.id=0) BEGIN select testtable_2_seq.nextval into :new.id from dual; END; ;


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

DROP TABLE IF EXISTS "table_name";
CREATE TABLE "table_name" (
	"id" INTEGER NOT NULL,
	"column_name" VARCHAR(20)
);