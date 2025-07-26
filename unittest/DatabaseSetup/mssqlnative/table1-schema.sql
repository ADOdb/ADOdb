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
