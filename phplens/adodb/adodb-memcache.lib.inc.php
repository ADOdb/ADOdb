<?php

// security - hide paths
if (!defined('ADODB_DIR')) die();

global $ADODB_INCLUDED_MEMCACHE;
$ADODB_INCLUDED_MEMCACHE = 1;

/* 

  V4.98 13 Feb 2008  (c) 2000-2008 John Lim (jlim#natsoft.com). All rights reserved.
  Released under both BSD license and Lesser GPL library license. 
  Whenever there is any discrepancy between the two licenses, 
  the BSD license will take precedence. See License.txt. 
  Set tabs to 4 for best viewing.
  
  Latest version is available at http://adodb.sourceforge.net

Usage:
  
$db = NewADOConnection($driver);
$db->memCache = true; /// should we use memCache instead of caching in files
$db->memCacheHost = array($ip1, $ip2, $ip3);
$db->memCachePort = 11211; /// this is default memCache port
$db->memCacheCompress = false; /// Use 'true' to store the item compressed (uses zlib)

$db->Connect(...);
$db->CacheExecute($sql);
  
*/

	function getmemcache($key,&$err, $timeout=0, $hosts, $port)
	{
		$false = false;
		$err = false;

		if (!function_exists('memcache_pconnect')) {
			$err = 'Memcache module PECL extension not found!';
			return $false;
		}

		$memcache = new Memcache;
		
		if (!is_array($hosts)) $hosts = array($hosts);
		
		$failcnt = 0;
		foreach($hosts as $host) {
			if (!@$memcache->addServer($host,$port,true)) {
				$failcnt += 1;
			}
		}
		if ($failcnt == sizeof($hosts)) {
			$err = 'Can\'t connect to any memcache server';
			return $false;
		}
		
		$rs = $memcache->get($key);
		if (!$rs) {
			$err = 'Item with such key doesn\'t exists on the memcached server.';
			return $false;
		}

		$tdiff = intval($rs->timeCreated+$timeout - time());
		if ($tdiff <= 2) {
			switch($tdiff) {
				case 2: 
					if ((rand() & 15) == 0) {
						$err = "Timeout 2";
						return $false;
					}
					break;
				case 1:
					if ((rand() & 3) == 0) {
						$err = "Timeout 1";
						return $false;
					}
					break;
				default: 
					$err = "Timeout 0";
					return $false;
			}
		}
		return $rs;
	}

	function putmemcache($key, $rs, $host, $port, $compress, $debug=false)
	{
		$false = false;
		$true = true;

		if (!function_exists('memcache_pconnect')) {
			if ($debug) ADOConnection::outp(" Memcache module PECL extension not found!<br>\n");
			return $false;
		}

		$memcache = new Memcache;
		$failcnt = 0;
		foreach($hosts as $host) {
			if (!@$memcache->addServer($host,$port,true)) {
				$failcnt += 1;
			}
		}
		if ($failcnt == sizeof($hosts)) {
			$err = 'Can\'t connect to any memcache server';
			return $false;
		}

		$rs->timeCreated = time();
		if (!$memcache->set($key, $rs, $compress, 0)) {
			if ($debug) ADOConnection::outp(" Failed to save data at the memcached server!<br>\n");
			return $false;
		}
		return $true;
	}

	function flushmemcache($key=false, $host, $port, $debug=false)
	{
		if (!function_exists('memcache_pconnect')) {
			if ($debug) ADOConnection::outp(" Memcache module PECL extension not found!<br>\n");
			return;
		}

		$memcache = new Memcache;
		$failcnt = 0;
		foreach($hosts as $host) {
			if (!@$memcache->addServer($host,$port,true)) {
				$failcnt += 1;
			}
		}
		if ($failcnt == sizeof($hosts)) {
			$err = 'Can\'t connect to any memcache server';
			return $false;
		}

		if ($key) {
			if (!$memcache->delete($key)) {
				if ($debug) ADOConnection::outp("CacheFlush: $key entery doesn't exist on memcached server!<br>\n");
			} else {
				if ($debug) ADOConnection::outp("CacheFlush: $key entery flushed from memcached server!<br>\n");
			}
		} else {
			if (!$memcache->flush()) {
				if ($debug) ADOConnection::outp("CacheFlush: Failure flushing all enteries from memcached server!<br>\n");
			} else {
				if ($debug) ADOConnection::outp("CacheFlush: All enteries flushed from memcached server!<br>\n");
			}
		}
		return;
	}
?>