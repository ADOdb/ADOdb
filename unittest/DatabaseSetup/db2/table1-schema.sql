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

CREATE TABLE testtable_2 (
	id INTEGER NOT NULL GENERATED ALWAYS AS IDENTITY (START WITH 1 INCREMENT BY 1),
	integer_field SMALLINT NOT NULL DEFAULT 0,
	date_field DATE,
    PRIMARY KEY (id)
);