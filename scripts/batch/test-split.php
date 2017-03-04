<?php

$t = 
"/*

@version V5.20dev  ??-???-2014  (c) 2000-2014 John Lim (jlim#natsoft.com). All rights reserved.
  Latest version is available at http://adodb.sourceforge.net

  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence.

  Active Record implementation. Superset of Zend Framework's.

  Version 0.92

  See http://www-128.ibm.com/developerworks/java/library/j-cb03076/?ca=dgr-lnxw01ActiveRecord
  	for info on Ruby on Rails Active Record implementation
*/


global $_ADODB_ACTIVE_DBS;
global $ADODB_ACTIVE_CACHESECS; // set to true to enable caching of metadata such as field info
global $ACTIVE_RECORD_SAFETY; // set to false to disable safety checks
global $ADODB_ACTIVE_DEFVALS; // use default values of table definition when creating new active record.
";


function extractHeaderComment($t){
	
	
	$q = preg_split('/(\/\*|\*\/|\.\n)+/',$t);
	print_r($q);
	exit;
	$w = $q[2];
	$wa = preg_split('/[\r|\n]+/',$w);
	$wa1 = array();
	foreach($wa as $k=>$v){
		$v = trim($v);
		$v = trim($v,'.');
		if ($v == ''){
			unset($wa[$k]);
			continue;
		}
		if (strlen($v)> 76){
			$va = chunk_split($v);
			$vae = explode("\r\n",$va);
			
			foreach($vae as $vas ){
				if ($vas)
					$wa1[] = $vas;
			}	
		   continue;
		}
		$wa1[] = $v;
	}

	foreach($wa1 as $k=>$v)
		$wa1[$k] = '* ' . $v;
    
	$comx = implode("\n",$wa1);
	return $comx;
}

$comx = extractHeaderComment($t);
print $comx;
/* Find anything after precedence */