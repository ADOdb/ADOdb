/*
* Database: Oracle
* Creates a table that supports compressed/encrypted sessions
*/

CREATE TABLE SESSIONS2
(
  SESSKEY    VARCHAR2(64 BYTE)                  NOT NULL,
  EXPIRY     DATE                               NOT NULL,
  EXPIREREF  VARCHAR2(200 BYTE),
  CREATED    DATE                               NOT NULL,
  MODIFIED   DATE                               NOT NULL,
  SESSDATA   BLOB,
  PRIMARY KEY(SESSKEY)
);

CREATE INDEX SESS2_EXPIRY ON SESSIONS2(EXPIRY);
CREATE UNIQUE INDEX SESS2_PK ON SESSIONS2(SESSKEY);
CREATE INDEX SESS2_EXP_REF ON SESSIONS2(EXPIREREF);
