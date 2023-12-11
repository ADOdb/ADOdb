<?php
/**
 * Test for Oracle Variable Cursors, which are treated as ADOdb recordsets.
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @package ADOdb
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v2.1 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-2.1-or-later
 *
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
 */

/*
	We have 2 examples. The first shows us using the Parameter statement.
	The second shows us using the new ExecuteCursor($sql, $cursorName)
	function.

------------------------------------------------------------------
-- TEST PACKAGE YOU NEED TO INSTALL ON ORACLE - run from sql*plus
------------------------------------------------------------------


-- TEST PACKAGE
CREATE OR REPLACE PACKAGE adodb AS
TYPE TabType IS REF CURSOR RETURN tab%ROWTYPE;
PROCEDURE open_tab (tabcursor IN OUT TabType,tablenames in varchar);
PROCEDURE data_out(input IN varchar, output OUT varchar);

procedure myproc (p1 in number, p2 out number);
END adodb;
/

CREATE OR REPLACE PACKAGE BODY adodb AS
PROCEDURE open_tab (tabcursor IN OUT TabType,tablenames in varchar) IS
	BEGIN
		OPEN tabcursor FOR SELECT * FROM tab where tname like tablenames;
	END open_tab;

PROCEDURE data_out(input IN varchar, output OUT varchar) IS
	BEGIN
		output := 'Cinta Hati '||input;
	END;

procedure myproc (p1 in number, p2 out number) as
begin
p2 := p1;
end;
END adodb;
/

------------------------------------------------------------------
-- END PACKAGE
------------------------------------------------------------------

*/

include('../adodb.inc.php');
include('../tohtml.inc.php');

	error_reporting(E_ALL);
	$db = ADONewConnection('oci8');
	$db->PConnect('','scott','natsoft');
	$db->debug = 99;


/*
*/

	define('MYNUM',5);


	$rs = $db->ExecuteCursor("BEGIN adodb.open_tab(:RS,'A%'); END;");

	if ($rs && !$rs->EOF) {
		print "Test 1 RowCount: ".$rs->RecordCount()."<p>";
	} else {
		print "<b>Error in using Cursor Variables 1</b><p>";
	}

	print "<h4>Testing Stored Procedures for oci8</h4>";

	$stid = $db->PrepareSP('BEGIN adodb.myproc('.MYNUM.', :myov); END;');
	$db->OutParameter($stid, $myov, 'myov');
	$db->Execute($stid);
	if ($myov != MYNUM) print "<p><b>Error with myproc</b></p>";


	$stmt = $db->PrepareSP("BEGIN adodb.data_out(:a1, :a2); END;",true);
	$a1 = 'Malaysia';
	//$a2 = ''; # a2 doesn't even need to be defined!
	$db->InParameter($stmt,$a1,'a1');
	$db->OutParameter($stmt,$a2,'a2');
	$rs = $db->Execute($stmt);
	if ($rs) {
		if ($a2 !== 'Cinta Hati Malaysia') print "<b>Stored Procedure Error: a2 = $a2</b><p>";
		else echo  "OK: a2=$a2<p>";
	} else {
		print "<b>Error in using Stored Procedure IN/Out Variables</b><p>";
	}


	$tname = 'A%';

	$stmt = $db->PrepareSP('select * from tab where tname like :tablename');
	$db->Parameter($stmt,$tname,'tablename');
	$rs = $db->Execute($stmt);
	rs2html($rs);
