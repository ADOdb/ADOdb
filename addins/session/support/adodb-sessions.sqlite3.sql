/*
* Database: SQLite3
* Creates a table that supports compressed/encrypted sessions
*/

CREATE TABLE sessions2(
	sesskey TEXT( 64 ) NOT NULL DEFAULT '',
	expiry INT NOT NULL ,
	expireref TEXT( 250 ) DEFAULT '',
	created INT NOT NULL ,
	modified INT NOT NULL ,
	sessdata BLOB,
	PRIMARY KEY ( sesskey )
);
CREATE INDEX sess2_expiry ON sessions2 ( expiry );
CREATE INDEX sess2_expireref ON sessions2 ( sess2_expireref );