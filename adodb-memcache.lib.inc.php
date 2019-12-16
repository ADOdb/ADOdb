<?php

// security - hide paths
if (!defined('ADODB_DIR')) die();

global $ADODB_INCLUDED_MEMCACHE;
$ADODB_INCLUDED_MEMCACHE = 1;

/*

  @version   v5.21.0-dev  ??-???-2016
  @copyright (c) 2000-2013 John Lim (jlim#natsoft.com). All rights reserved.
  @copyright (c) 2014      Damien Regad, Mark Newnham and the ADOdb community
  Released under both BSD license and Lesser GPL library license.
  Whenever there is any discrepancy between the two licenses,
  the BSD license will take precedence. See License.txt.
  Set tabs to 4 for best viewing.

  Latest version is available at http://adodb.org/



  Class instance is stored in $ADODB_CACHE
*/

class ADODB_Cache_MemCache 
{
	/*
	* Prevents parent class calling non-existant function
	*/
	public $createdir = false; 

	/*
	* $library will be populated with the proper library on connect
	* and is used later when there are differences in specific calls
	* between memcache and memcached
	*/
	private $library = false;
	
	/*
	* array of hosts
	*/
	private $hosts;	
	
	/*
	* Connection Port, uses default
	*/
	private $port 	= 11211;
	
	/*
	* memcache compression with zlib
	*/
	private $compress = false; 

	
	/*
	* Internal flag indicating connection
	*/
	private $_connected = false;
	
	/*
	* Handle for the Memcache library
	*/
	private $_memcache = false;

	/**
	* constructor passes in a ADONewConnection Object
	*
	* @param	$db	ADONewConnection object
	*
	* @return obj
	*/
	public function __construct(&$db)
	{
		$this->hosts 	= $db->memCacheHost;
		$this->port  	= $db->memCachePort;
		$this->compress = $db->memCacheCompress;
	}

	/**
	* implement as lazy connection. The connection only occurs on CacheExecute call
	*
	* @param string	$err
	*
	* @return bool success of connecting to a server
	*/
	public function connect(&$err)
	{
		/*
		* do we have memcache or memcached? see the note
		* at adodb.org on memcache
		*/
		if (class_exists('Memcache')) {
			$this->library='Memcache';
			$memcache = new MemCache;
		} elseif (class_exists('Memcached')) {
			$this->library='Memcached';
			$memcache = new MemCached;
		} else {
			$err = 'Neither the Memcache nor Memcached PECL extensions were found!';
			return false;
		}

		if (!is_array($this->hosts)) 
			$this->hosts = array($this->hosts);

		$failcnt = 0;
		foreach($this->hosts as $host) 
		{
			if (!@$memcache->addServer($host,$this->port)) {
				$failcnt += 1;
			}
		}
		if ($failcnt == sizeof($this->hosts)) {
			$err = 'Can\'t connect to any memcache server';
			return false;
		}
		$this->_connected = true;
		$this->_memcache = $memcache;
		return true;
	}

	/**
	* Writes a cached query to the server
	*
	* @param string $filename The MD5 of the query to cache
	* @param string $contents The query results
	* @param bool	$debug
	* @param int	$secs2cache
	*
	* @return bool true or false. true if successful save
	*/
	public function writeCache($filename, $contents, $debug, $secs2cache)
	{
		if (!$this->_connected) {
			$err = '';
			if (!$this->connect($err) && $debug) ADOConnection::outp($err);
		}
		if (!$this->_memcache) return false;

		$failed=false;
		switch ($this->library) {
			case 'Memcache':
				if (!$this->_memcache->set($filename, $contents, $this->compress ? MEMCACHE_COMPRESSED : 0, $secs2cache)) {
					$failed=true;
				}
				break;
			case 'Memcached':
				if (!$this->_memcache->set($filename, $contents, $secs2cache)) {
					$failed=true;
				}
				break;
			default:
				$failed=true;
				break;
		}

		if($failed) {
			if ($debug) ADOConnection::outp(" Failed to save data at the memcache server!<br>\n");
			return false;
		}

		return true;
	}

	/**
	* Reads a cached query to the server
	*
	* @param string $filename The MD5 of the query to read
	* @param string $err The query results
	* @param int	$secs2cache
	* @param obj	$rsClass **UNUSED**
	
	* @return the record or false.
	*/
	public function readCache($filename, &$err, $secs2cache, $rsClass)
	{
		if (!$this->_connected) $this->connect($err);
		if (!$this->_memcache) 
			return false;

		$rs = $this->_memcache->get($filename);
		if (!$rs) 
		{
			$err = 'Item with such key doesn\'t exist on the memcache server.';
			return false;
		}

		// hack, should actually use _csv2rs
		$rs = explode("\n", $rs);
		unset($rs[0]);
		$rs = join("\n", $rs);
		$rs = unserialize($rs);
		if (! is_object($rs)) {
			$err = 'Unable to unserialize $rs';
			return $false;
		}
		if ($rs->timeCreated == 0) 
			return $rs; // apparently have been reports that timeCreated was set to 0 somewhere

		$tdiff = intval($rs->timeCreated+$secs2cache - time());
		if ($tdiff <= 2) 
		{
			switch($tdiff)
			{
				case 2:
					if ((rand() & 15) == 0) {
						$err = "Timeout 2";
						return false;
					}
					break;
				case 1:
					if ((rand() & 3) == 0) {
						$err = "Timeout 1";
						return false;
					}
					break;
				default:
					$err = "Timeout 0";
					return false;
			}
		}
		return $rs;
	}

	/**
	* Flushes all of the stored memcache data
	*
	* @param	bool	$debug
	* 
	* @return int The response from the memcache server
	*/
	public function flushAll($debug=false)
	{
		if (!$this->_connected) {
			$err = '';
			if (!$this->connect($err) && $debug) ADOConnection::outp($err);
		}
		if (!$this->_memcache) 
			return false;

		$del = $this->_memcache->flush();

		if ($debug)
			if (!$del) 
				ADOConnection::outp("flushall: failed!<br>\n");
			else 
				ADOConnection::outp("flushall: succeeded!<br>\n");

		return $del;
	}

	/**
	* Flushes the contents of a specified query
	*
	* @param	str		$filname	The MD5 of the query to flush
	* @param	bool	$debug
	* 
	* @return int The response from the memcache server
	*/
	public function flushCache($filename, $debug=false)
	{
		if (!$this->_connected) 
		{
			$err = '';
			if (!$this->connect($err) && $debug) ADOConnection::outp($err);
		}
		if (!$this->_memcache) 
			return false;

		$del = $this->_memcache->delete($filename);

		if ($debug)
			if (!$del) ADOConnection::outp("flushcache: $key entry doesn't exist on memcache server!<br>\n");
			else ADOConnection::outp("flushcache: $key entry flushed from memcache server!<br>\n");

		return $del;
	}

}
