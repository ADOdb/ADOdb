/*
* Database: Postgresql
* Creates a table that supports compressed/encrypted sessions
*/
CREATE TABLE sessions2(
	sesskey VARCHAR( 64 ) NOT NULL DEFAULT '',
	expiry TIMESTAMP NOT NULL ,
	expireref VARCHAR( 250 ) DEFAULT '',
	created TIMESTAMP NOT NULL ,
	modified TIMESTAMP NOT NULL ,
	sessdata BYTEA,
	PRIMARY KEY ( sesskey )
);
CREATE INDEX sess2_expiry ON sessions2 ( expiry );
CREATE INDEX sess2_expireref ON sessions2 ( expireref );