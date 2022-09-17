/*
* Database: MYSQL
* Creates a table that supports compressed/encrypted sessions
*/
CREATE TABLE sessions2(
	sesskey VARCHAR( 64 ) NOT NULL DEFAULT '',
	expiry TIMESTAMP NOT NULL ,
	expireref VARCHAR( 250 ) DEFAULT '',
	created TIMESTAMP NOT NULL ,
	modified TIMESTAMP NOT NULL ,
	sessdata LONGBLOB,
	PRIMARY KEY ( sesskey ) ,
	INDEX sess2_expiry( expiry ),
	INDEX sess2_expireref( expireref )
)