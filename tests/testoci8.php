<html>
<body>
<?php
/**
 * ADOdb tests - oci8
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

error_reporting(E_ALL | E_STRICT);
include("../adodb.inc.php");
include("../tohtml.inc.php");

if (0) {
	$db = ADONewConnection('oci8po');

	$db->PConnect('','scott','natsoft');
	if (!empty($testblob)) {
		$varHoldingBlob = 'ABC DEF GEF John TEST';
		$num = time()%10240;
		// create table atable (id integer, ablob blob);
		$db->Execute('insert into ATABLE (id,ablob) values('.$num.',empty_blob())');
		$db->UpdateBlob('ATABLE', 'ablob', $varHoldingBlob, 'id='.$num, 'BLOB');

		$rs = $db->Execute('select * from atable');

		if (!$rs) die("Empty RS");
		if ($rs->EOF) die("EOF RS");
		rs2html($rs);
	}
	$stmt = $db->Prepare('select * from adoxyz where id=?');
	for ($i = 1; $i <= 10; $i++) {
	$rs = $db->Execute(
		$stmt,
		array($i));

		if (!$rs) die("Empty RS");
		if ($rs->EOF) die("EOF RS");
		rs2html($rs);
	}
}
if (1) {
	$db = ADONewConnection('oci8');
	$db->PConnect('','scott','natsoft');
	$db->debug = true;
	$db->Execute("delete from emp where ename='John'");
	print $db->Affected_Rows().'<BR>';
	$stmt = $db->Prepare('insert into emp (empno, ename) values (:empno, :ename)');
	$rs = $db->Execute($stmt,array('empno'=>4321,'ename'=>'John'));
	// prepare not quite ready for prime time
	//$rs = $db->Execute($stmt,array('empno'=>3775,'ename'=>'John'));
	if (!$rs) die("Empty RS");

	$db->setfetchmode(ADODB_FETCH_NUM);

	$vv = 'A%';
	$stmt = $db->PrepareSP("BEGIN adodb.open_tab2(:rs,:tt); END;",true);
	$db->OutParameter($stmt, $cur, 'rs', -1, OCI_B_CURSOR);
	$db->OutParameter($stmt, $vv, 'tt');
	$rs = $db->Execute($stmt);
	while (!$rs->EOF) {
		adodb_pr($rs->fields);
		$rs->MoveNext();
	}
	echo " val = $vv";

}

if (0) {
	$db = ADONewConnection('odbc_oracle');
	if (!$db->PConnect('local_oracle','scott','tiger')) die('fail connect');
	$db->debug = true;
	$rs = $db->Execute(
		'select * from adoxyz where firstname=? and trim(lastname)=?',
		array('first'=>'Caroline','last'=>'Miranda'));
	if (!$rs) die("Empty RS");
	if ($rs->EOF) die("EOF RS");
	rs2html($rs);
}
