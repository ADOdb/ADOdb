CREATE TABLE IF NOT EXISTS testtable_1 (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	varchar_field VARCHAR(20),
	datetime_field DATETIME
	date_field DATE,
	integer_field INT(2) DEFAULT 0,
	decimal_field decimal(12.2) DEFAULT 0,
	unique index vdx1 (varchar_field),
	index vdx2 (integer_field)
)