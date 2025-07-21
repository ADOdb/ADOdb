DROP TABLE IF EXISTS testtable_1;
CREATE TABLE testtable_1 (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	varchar_field VARCHAR(20),
	datetime_field DATETIME,
	date_field DATE,
	integer_field INT(2) DEFAULT 0,
	decimal_field decimal(12.2) DEFAULT 0
);
CREATE	unique index vdx1 ON testtable_1 (varchar_field);
CREATE	index vdx2 ON testtable_1 (integer_field);