-- This file contains SQL commands to set up the database for unit tests
INSERT INTO testtable_3 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 1','2025-01-01 00:00:01','2025-01-01',9001,1000.01,1);
INSERT INTO testtable_3 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 2','2025-01-01 01:00:01','2025-02-01',9002,1000.11,2);
INSERT INTO testtable_3 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 3','2025-01-01 02:00:01','2025-03-01',9003,1000.21,3);
INSERT INTO testtable_3 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 4','2025-01-01 03:00:01','2025-04-01',9004,1000.31,4);
INSERT INTO testtable_3 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 5','2025-01-01 04:00:01','2025-05-01',9005,1000.41,5);
INSERT INTO testtable_3 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 6','2025-01-01 05:00:01','2025-06-01',9006,1000.51,6);
INSERT INTO testtable_3 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 7','2025-01-01 06:00:01','2025-07-01',9007,1000.61,7);
INSERT INTO testtable_3 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 8','2025-01-01 07:00:01','2025-08-01',9008,1000.71,8);
INSERT INTO testtable_3 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 9','2025-01-01 08:00:01','1959-08-29',9009,1000.81,9);
INSERT INTO testtable_3 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 10','2025-01-01 09:00:01','2025-10-01',9010,1000.91,10);
INSERT INTO testtable_3 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 11','2025-01-01 23:00:01','1725-11-01',-9011,-1000.11,11);
-- Insert a record to test foreign key constraints
INSERT INTO testtable_1 (varchar_field,	datetime_field,date_field, integer_field,decimal_field,number_run_field) values ('LINE 2','2025-01-01 01:00:01','2025-02-01',9002,1000.11,2);