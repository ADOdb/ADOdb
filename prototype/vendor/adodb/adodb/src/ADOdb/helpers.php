<?php

	
	

	/**
	 * Instantiate a new Connection class for a specific database driver.
	 *
	 * @param [db]  is the database Connection object to create. If undefined,
	 *	use the last database driver that was loaded by ADOLoadCode().
	 *
	 * @return the freshly created instance of the Connection class.
	 */
	function ADONewConnection($db='') {
		global $ADODB_NEWCONNECTION, $ADODB_LASTDB;

		if (!defined('ADODB_ASSOC_CASE')) {
			define('ADODB_ASSOC_CASE', ADODB_ASSOC_CASE_NATIVE);
		}
		$errorfn = (defined('ADODB_ERROR_HANDLER')) ? ADODB_ERROR_HANDLER : false;
		if (($at = strpos($db,'://')) !== FALSE) {
			$origdsn = $db;
			$fakedsn = 'fake'.substr($origdsn,$at);
			if (($at2 = strpos($origdsn,'@/')) !== FALSE) {
				// special handling of oracle, which might not have host
				$fakedsn = str_replace('@/','@adodb-fakehost/',$fakedsn);
			}

			if ((strpos($origdsn, 'sqlite')) !== FALSE && stripos($origdsn, '%2F') === FALSE) {
				// special handling for SQLite, it only might have the path to the database file.
				// If you try to connect to a SQLite database using a dsn
				// like 'sqlite:///path/to/database', the 'parse_url' php function
				// will throw you an exception with a message such as "unable to parse url"
				list($scheme, $path) = explode('://', $origdsn);
				$dsna['scheme'] = $scheme;
				if ($qmark = strpos($path,'?')) {
					$dsn['query'] = substr($path,$qmark+1);
					$path = substr($path,0,$qmark);
				}
				$dsna['path'] = '/' . urlencode($path);
			} else
				$dsna = @parse_url($fakedsn);

			if (!$dsna) {
				return false;
			}
			$dsna['scheme'] = substr($origdsn,0,$at);
			if ($at2 !== FALSE) {
				$dsna['host'] = '';
			}

			if (strncmp($origdsn,'pdo',3) == 0) {
				$sch = explode('_',$dsna['scheme']);
				if (sizeof($sch)>1) {
					$dsna['host'] = isset($dsna['host']) ? rawurldecode($dsna['host']) : '';
					if ($sch[1] == 'sqlite') {
						$dsna['host'] = rawurlencode($sch[1].':'.rawurldecode($dsna['host']));
					} else {
						$dsna['host'] = rawurlencode($sch[1].':host='.rawurldecode($dsna['host']));
					}
					$dsna['scheme'] = 'pdo';
				}
			}

			$db = @$dsna['scheme'];
			if (!$db) {
				return false;
			}
			$dsna['host'] = isset($dsna['host']) ? rawurldecode($dsna['host']) : '';
			$dsna['user'] = isset($dsna['user']) ? rawurldecode($dsna['user']) : '';
			$dsna['pass'] = isset($dsna['pass']) ? rawurldecode($dsna['pass']) : '';
			$dsna['path'] = isset($dsna['path']) ? rawurldecode(substr($dsna['path'],1)) : ''; # strip off initial /

			if (isset($dsna['query'])) {
				$opt1 = explode('&',$dsna['query']);
				foreach($opt1 as $k => $v) {
					$arr = explode('=',$v);
					$opt[$arr[0]] = isset($arr[1]) ? rawurldecode($arr[1]) : 1;
				}
			} else {
				$opt = array();
			}
		}
	/*
	 *  phptype: Database backend used in PHP (mysql, odbc etc.)
	 *  dbsyntax: Database used with regards to SQL syntax etc.
	 *  protocol: Communication protocol to use (tcp, unix etc.)
	 *  hostspec: Host specification (hostname[:port])
	 *  database: Database to use on the DBMS server
	 *  username: User name for login
	 *  password: Password for login
	 */
		if (!empty($ADODB_NEWCONNECTION)) {
			$obj = $ADODB_NEWCONNECTION($db);

		}

		if(empty($obj)) {

			if (!isset($ADODB_LASTDB)) {
				$ADODB_LASTDB = '';
			}
			if (empty($db)) {
				$db = $ADODB_LASTDB;
			}
			if ($db != $ADODB_LASTDB) {
				$db = ADOLoadCode($db);
			}

			if (!$db) {
				if (isset($origdsn)) {
					$db = $origdsn;
				}
				if ($errorfn) {
					// raise an error
					$ignore = false;
					$errorfn('ADONewConnection', 'ADONewConnection', -998,
							"could not load the database driver for '$db'",
							$db,false,$ignore);
				} else {
					ADOConnection::outp( "<p>ADONewConnection: Unable to load database driver '$db'</p>",false);
				}
				return false;
			}

			$cls = 'ADODB_'.$db;
			if (!class_exists($cls)) {
				adodb_backtrace();
				return false;
			}

			$obj = new $cls();
		}

		# constructor should not fail
		if ($obj) {
			if ($errorfn) {
				$obj->raiseErrorFn = $errorfn;
			}
			if (isset($dsna)) {
				if (isset($dsna['port'])) {
					$obj->port = $dsna['port'];
				}
				foreach($opt as $k => $v) {
					switch(strtolower($k)) {
					case 'new':
										$nconnect = true; $persist = true; break;
					case 'persist':
					case 'persistent':	$persist = $v; break;
					case 'debug':		$obj->debug = (integer) $v; break;
					#ibase
					case 'role':		$obj->role = $v; break;
					case 'dialect':	$obj->dialect = (integer) $v; break;
					case 'charset':		$obj->charset = $v; $obj->charSet=$v; break;
					case 'buffers':		$obj->buffers = $v; break;
					case 'fetchmode':   $obj->SetFetchMode($v); break;
					#ado
					case 'charpage':	$obj->charPage = $v; break;
					#mysql, mysqli
					case 'clientflags': $obj->clientFlags = $v; break;
					#mysql, mysqli, postgres
					case 'port': $obj->port = $v; break;
					#mysqli
					case 'socket': $obj->socket = $v; break;
					#oci8
					case 'nls_date_format': $obj->NLS_DATE_FORMAT = $v; break;
					case 'cachesecs': $obj->cacheSecs = $v; break;
					case 'memcache':
						$varr = explode(':',$v);
						$vlen = sizeof($varr);
						if ($vlen == 0) {
							break;
						}
						$obj->memCache = true;
						$obj->memCacheHost = explode(',',$varr[0]);
						if ($vlen == 1) {
							break;
						}
						$obj->memCachePort = $varr[1];
						if ($vlen == 2) {
							break;
						}
						$obj->memCacheCompress = $varr[2] ?  true : false;
						break;
					}
				}
				if (empty($persist)) {
					$ok = $obj->Connect($dsna['host'], $dsna['user'], $dsna['pass'], $dsna['path']);
				} else if (empty($nconnect)) {
					$ok = $obj->PConnect($dsna['host'], $dsna['user'], $dsna['pass'], $dsna['path']);
				} else {
					$ok = $obj->NConnect($dsna['host'], $dsna['user'], $dsna['pass'], $dsna['path']);
				}

				if (!$ok) {
					return false;
				}
			}
		}
		return $obj;
	}
	
