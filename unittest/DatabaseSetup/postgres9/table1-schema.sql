DROP TABLE IF EXISTS testtable_1;
CREATE TABLE testtable_1 (
	id SERIAL PRIMARY KEY,
	varchar_field VARCHAR(20),
	datetime_field TIME,
	date_field DATE,
	integer_field SMALLINT DEFAULT 0,
	decimal_field decimal(12,2) DEFAULT 0.0
);

create unique index vdx1 ON testtable_1 (varchar_field);
create index vdx2 ON testtable_1 (integer_field);