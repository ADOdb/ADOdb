<?php

// security - hide paths
if (!defined('ADODB_DIR')) die();

global $ADODB_INCLUDEDmemcacheLibrary;
$ADODB_INCLUDEDmemcacheLibrary = 1;

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

class ADODB_CachememcacheLibrary 
{
	/*
	* Prevents parent class calling non-existant function
	*/
	public $createdir = false; 

	/*
	* populated with the proper library on connect
	* and is used later when there are differences in specific calls
	* between memcache and memcached
	*/
	private $memCacheLibrary = false;
	
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
	* Array of options for memcached only
	*/
	private $options = false;
	
	/*
	* Internal flag indicating successful connection
	*/
	private $isConnected = false;
	
	/*
	* Handle for the Memcache library
	*/
	private $memcacheLibrary = false
	
	/*
	* New server feature controller lists available servers
	*/
	private $serverControllers = array();
	
	/*
	* New server feature controller uses granular
	* server controller
	*/
	private $serverControllerTemplate = array(
		'host'=>'',
		'port'=>11211,
		'weight'=>0,
		'key'=>''
		);

	
	/*
	* An integer index into the libraries
	*/
	const MCLIB  = 1;
	const MCLIBD = 2;
	
	/*
	* Xrefs the library flag to the actual class name
	*/
	private $libraries = array(
		1=>'Memcache',
		2=>'Memcached'
		);
	
	/*
	* An indicator of which library we are using
	*/
	private $libraryFlag;
	
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
		$this->options  = $db->memCacheOptions;
		
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
		if (class_exists('Memcache')) 
			$this->libraryFlag = self::MCLIB;
		elseif (class_exists('Memcached'))
			$this->libraryFlag = self::MCLIB;
		else
		{
			$err = 'Neither the Memcache nor Memcached PECL extensions were found!';
			return false;
		}
		
		$this->memCacheLibrary = new {$this->libraries[$libraryFlag]};
		if (!$this->memcacheLibrary)
		{
			$err = 'Memcache library failed to initialize';
			return false;
		}
		
		/*
		* Convert simple compression flag for memcached
		*/
		if ($this->libraryFlag == self::MCLIBD && $this->compress)
		{
			/*
			* Value of Memcached::OPT_COMPRESSION = 2;
			*/
			$this->options[2] == 1
		}
		/*
		* Are there any options available for memcached
		*/
		if ($this->libraryFlag == self::MCLIBD && count($this->options) > 0)
		{
			$optionSuccess = $this->memCacheLibrary->setOptions($this->options);
			if (!$optionSuccess)
			{
				$err = 'Invalid option parameters passed to Memecached';
				return false;
			}
		}
		
		/*
		* Have we passed a controller array
		*/
		if (!is_array($this->hosts)) 
			$this->hosts = array($this->hosts);
		
		if (array_values($this->hosts) == $this->hosts)
		{
			/*
			* Old way, convert to controller
			*/
			foreach ($this->hosts as $ipAddress)
			{
				$connector = $this->serverControllerTemplate;
				
				$connector['host'] = $ipAddress;
				$connector['port'] = $this->port;
				
				$this->serverControllers[] = $connector;
			}
		}
		else
		{
			/*
			* New way, must validate port, etc
			*/
			foreach ($this->hosts as $controller)
			{
				$connector = array_merge($this->serverControllerTemplate,$controller);
				if ($this->libraryFlag == self::MCLIB)
				{
					/*
					* Cannot use a key or weight in memcache, simply discard
					*/
					$connector['key'] = '';
					$connector['weight'] = 0;
					
				}
				else
					$connector['weight'] = $connector['weight'] ? (int)$connector['weight']:0;
				
				$this->serverControllers[] = $connector;
			}
		}

		/*
		* Checks for existing connections ( but only for memcached )
		*/
		if ($this->libraryFlag == self::MCLIBD)
		{
			$existingServers = $memCache->getServerList();
			if (is_array($existingServers))
			{
				/*
				* Use the existing configuration
				*/
				$this->isConnected = true;
				$this->memcacheLibrary = $memcache;
				return true;
			}
		}
		$failcnt = 0;
		foreach($this->serverControllers as $controller) 
		{
			switch($this->libraryFlag)
			{
				case self::MCLIB:
				if (!@$this->memcacheLibrary->addServer($controller['host'],$controller['port']))
					$failcnt++;
				break;
				default:
				if (!@$this->memcacheLibrary->addServer($controller['host'],$controller['port'],$controller['weight']))
					$failcnt++;
			}
			
		}
		if ($failcnt == sizeof($this->serverControllers))
		{
			$err = 'Can\'t connect to any memcache server';
			return false;

		}
		
		/*
		* A valid memcache connection is available
		*/
		$this->isConnected = true;
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
		$err = '';
		if (!$this->isConnected  && $debug) 
		{
			/*
			* Call to writecache before connect, try
			* to connect
			*/
			if (!$this->connect($err)) 
				ADOConnection::outp($err);
		}
		else if (!$this->isConnected) 
			$this->connect($err);
		
		if (!$this->memcacheLibrary) 
			return false;

		$failed=false;
		switch ($this->libraryFlag)
		{
			case self::MCLIB:
				if (!$this->memcacheLibrary->set($filename, $contents, $this->compress ? MEMCACHE_COMPRESSED : 0, $secs2cache)) {
					$failed=true;
				}
				break;
			case self::MCLIBD:
				if (!$this->memcacheLibrary->set($filename, $contents, $secs2cache)) {
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
		if (!$this->isConnected) $this->connect($err);
		if (!$this->memcacheLibrary) 
			return false;

		$rs = $this->memcacheLibrary->get($filename);
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
		if (!$this->isConnected) {
			$err = '';
			if (!$this->connect($err) && $debug) ADOConnection::outp($err);
		}
		if (!$this->memcacheLibrary) 
			return false;

		$del = $this->memcacheLibrary->flush();

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
		if (!$this->isConnected) 
		{
			$err = '';
			if (!$this->connect($err) && $debug) ADOConnection::outp($err);
		}
		if (!$this->memcacheLibrary) 
			return false;

		$del = $this->memcacheLibrary->delete($filename);

		if ($debug)
			if (!$del) ADOConnection::outp("flushcache: $key entry doesn't exist on memcache server!<br>\n");
			else ADOConnection::outp("flushcache: $key entry flushed from memcache server!<br>\n");

		return $del;
	}

}
