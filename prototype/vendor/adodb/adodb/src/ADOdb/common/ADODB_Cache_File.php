<?php
namespace ADOdb;
//------------------
// class for caching
class ADODB_Cache_File {

	var $createdir = true; // requires creation of temp dirs

	function __construct() {
		global $ADODB_INCLUDED_CSV;
		if (empty($ADODB_INCLUDED_CSV)) {
			include_once(ADODB_DIR.'/adodb-csvlib.inc.php');
		}
	}

	// write serialised recordset to cache item/file
	function writecache($filename, $contents,  $debug, $secs2cache) {
		return adodb_write_file($filename, $contents,$debug);
	}

	// load serialised recordset and unserialise it
	function &readcache($filename, &$err, $secs2cache, $rsClass) {
		$rs = csv2rs($filename,$err,$secs2cache,$rsClass);
		return $rs;
	}

	// flush all items in cache
	function flushall($debug=false) {
		global $ADODB_CACHE_DIR;

		$rez = false;

		if (strlen($ADODB_CACHE_DIR) > 1) {
			$rez = $this->_dirFlush($ADODB_CACHE_DIR);
			if ($debug) {
				ADOConnection::outp( "flushall: $ADODB_CACHE_DIR<br><pre>\n". $rez."</pre>");
			}
		}
		return $rez;
	}

	// flush one file in cache
	function flushcache($f, $debug=false) {
		if (!@unlink($f)) {
			if ($debug) {
				ADOConnection::outp( "flushcache: failed for $f");
			}
		}
	}

	function getdirname($hash) {
		global $ADODB_CACHE_DIR;
		if (!isset($this->notSafeMode)) {
			$this->notSafeMode = !ini_get('safe_mode');
		}
		return ($this->notSafeMode) ? $ADODB_CACHE_DIR.'/'.substr($hash,0,2) : $ADODB_CACHE_DIR;
	}

	// create temp directories
	function createdir($hash, $debug) {
		global $ADODB_CACHE_PERMS;

		$dir = $this->getdirname($hash);
		if ($this->notSafeMode && !file_exists($dir)) {
			$oldu = umask(0);
			if (!@mkdir($dir, empty($ADODB_CACHE_PERMS) ? 0771 : $ADODB_CACHE_PERMS)) {
				if(!is_dir($dir) && $debug) {
					ADOConnection::outp("Cannot create $dir");
				}
			}
			umask($oldu);
		}

		return $dir;
	}

	/**
	* Private function to erase all of the files and subdirectories in a directory.
	*
	* Just specify the directory, and tell it if you want to delete the directory or just clear it out.
	* Note: $kill_top_level is used internally in the function to flush subdirectories.
	*/
	function _dirFlush($dir, $kill_top_level = false) {
		if(!$dh = @opendir($dir)) return;

		while (($obj = readdir($dh))) {
			if($obj=='.' || $obj=='..') continue;
			$f = $dir.'/'.$obj;

			if (strpos($obj,'.cache')) {
				@unlink($f);
			}
			if (is_dir($f)) {
				$this->_dirFlush($f, true);
			}
		}
		if ($kill_top_level === true) {
			@rmdir($dir);
		}
		return true;
	}
}