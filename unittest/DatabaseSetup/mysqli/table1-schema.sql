DROP TABLE IF EXISTS testtable_2;
DROP TABLE IF EXISTS testtable_1;
CREATE TABLE testtable_1 (
	id INT NOT NULL AUTO_INCREMENT,
	varchar_field VARCHAR(20),
	datetime_field DATETIME,
	date_field DATE,
	integer_field INT(2) DEFAULT 0,
	decimal_field decimal(12.2) DEFAULT 0,
	empty_field VARCHAR(240) DEFAULT '',
	PRIMARY KEY(id),
	unique INDEX vdx1 (varchar_field),
	UNIQUE INDEX vdx2 (integer_field,date_field)
);

CREATE TABLE testtable_2 (
    id INT NOT NULL AUTO_INCREMENT,
    integer_field INT(2),
	date_field DATE,
	PRIMARY KEY(id),
    FOREIGN KEY (integer_field,date_field) REFERENCES testtable_1(integer_field,date_field)
);