<?php
/**
 * LDAP driver.
 *
 * Provides a subset of ADOdb commands, allowing read-only access to an LDAP database.
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
 * @author Joshua Eldridge <joshuae74@hotmail.com>
 */

// security - hide paths
if (!defined('ADODB_DIR')) die();

if (!defined('LDAP_ASSOC')) {
	define('LDAP_ASSOC',ADODB_FETCH_ASSOC);
	define('LDAP_NUM',ADODB_FETCH_NUM);
	define('LDAP_BOTH',ADODB_FETCH_BOTH);
}

class ADODB_ldap extends ADOConnection
{
	public $databaseType = 'ldap';
	public $dataProvider = 'ldap';

	/**
	 * @var string[] Caches the version info
	 */
	protected $version;

	/**
	 * @var string Bind error message, eg. "Binding: invalid credentials"
	 */
	protected $_bind_errmsg = "Binding: %s";

	protected $connectionParameters = array(
		LDAP_OPT_PROTOCOL_VERSION => 3,
		LDAP_OPT_REFERRALS => 0
	);


	/**
	 * Connect to an LDAP Server
	 *
	 * @param string|null $ldapServer The LDAP Server to connect to.
	 * @param string|null $username The username to connect as.
	 * @param string|null $password The password to connect with.
	 * @param string|null $ldapbase The Base DN
	 *
	 * @return bool|null True if connected successfully, false if connection failed, or null if the ldap extension
	 * isn't currently loaded.
	 */
	public function _connect($ldapServer, $username, $password, $ldapbase)
	{
		global $LDAP_CONNECT_OPTIONS;

		if (!function_exists('ldap_connect')) {
			return null;
		}

		if (strpos($ldapServer, 'ldap://') !== 0 && strpos($ldapServer, 'ldaps://') !== 0) {
			/*
			* If ldap SSL not explicitly specified, fall back to insecure
			*/
			$ldapServer = sprintf('ldap://%s', $ldapServer);
		}

		$this->_connectionID = @ldap_connect($ldapServer);

		if (!$this->_connectionID) {
			$e = 'Could not connect to ' . $ldapServer;
			$this->_errorMsg = $e;
			if ($this->debug) {
				ADOConnection::outp($e);
			}

			return false;
		}

		if(!empty($LDAP_CONNECT_OPTIONS) && is_array($LDAP_CONNECT_OPTIONS)) {
			// Convert options to connectionParameters()
			trigger_error('$LDAP_CONNECT_OPTIONS is deprecated, use setConnectionParameter() instead', E_USER_DEPRECATED);	
			$this->_inject_bind_options($LDAP_CONNECT_OPTIONS);
		}

		// Iterate over any connection parameters
		foreach ($this->connectionParameters as $parameter => $value) {
			if (!ldap_set_option($this->_connectionID, $parameter, $value)) {
				if ($this->debug) {
					$message = sprintf('Warning - Setting connection parameter %s to value %s failed', $parameter,
						$value);
					ADOConnection::outp($message);
					$this->_errorMsg = $message;
				}
			}
		}

		if ($username) {
			$bind = @ldap_bind($this->_connectionID, $username, $password);
		} else {
			$bind = @ldap_bind($this->_connectionID);
		}

		if (!$bind) {
			$e = sprintf($this->_bind_errmsg, ldap_error($this->_connectionID));
			$this->_errorMsg = $e;
			if ($this->debug) {
				ADOConnection::outp($e);
			}
			return false;
		}

		$this->_errorMsg = '';
		$this->database = $ldapbase;

		return $this->_connectionID;
	}

/*
	Valid Domain Values for LDAP Options:

	LDAP_OPT_DEREF (integer)
	LDAP_OPT_SIZELIMIT (integer)
	LDAP_OPT_TIMELIMIT (integer)
	LDAP_OPT_PROTOCOL_VERSION (integer)
	LDAP_OPT_ERROR_NUMBER (integer)
	LDAP_OPT_REFERRALS (boolean)
	LDAP_OPT_RESTART (boolean)
	LDAP_OPT_HOST_NAME (string)
	LDAP_OPT_ERROR_STRING (string)
	LDAP_OPT_MATCHED_DN (string)
	LDAP_OPT_SERVER_CONTROLS (array)
	LDAP_OPT_CLIENT_CONTROLS (array)

	Make sure to set this BEFORE calling Connect()

	Example:

	$LDAP_CONNECT_OPTIONS = Array(
		Array (
			"OPTION_NAME"=>LDAP_OPT_DEREF,
			"OPTION_VALUE"=>2
		),
		Array (
			"OPTION_NAME"=>LDAP_OPT_SIZELIMIT,
			"OPTION_VALUE"=>100
		),
		Array (
			"OPTION_NAME"=>LDAP_OPT_TIMELIMIT,
			"OPTION_VALUE"=>30
		),
		Array (
			"OPTION_NAME"=>LDAP_OPT_PROTOCOL_VERSION,
			"OPTION_VALUE"=>3
		),
		Array (
			"OPTION_NAME"=>LDAP_OPT_ERROR_NUMBER,
			"OPTION_VALUE"=>13
		),
		Array (
			"OPTION_NAME"=>LDAP_OPT_REFERRALS,
			"OPTION_VALUE"=>FALSE
		),
		Array (
			"OPTION_NAME"=>LDAP_OPT_RESTART,
			"OPTION_VALUE"=>FALSE
		)
	);
*/

	/**
	 * Converts old style global options into connection parameters
	 *
	 * @param array $options
	 * @return void
	 * @deprecated 5.23.0
	 */
	private function _inject_bind_options($options)
	{
		foreach ($options as $option) {
			$this->connectionParameters[$option["OPTION_NAME"]] = $option["OPTION_VALUE"];
			//ldap_set_option( $this->_connectionID, $option["OPTION_NAME"], $option["OPTION_VALUE"] );
			//	or die( "Unable to set server option: " . $option["OPTION_NAME"] );
		}
	}


	/**
	 * Execute a query against the AD Database.
	 *
	 * @param string|array $ldapQuery Query to execute.
	 * @param array $inputarr [Ignored] An optional array of parameters.
	 *
	 * @return resource|false
	 * @noinspection PhpParameterNameChangedDuringInheritanceInspection
	 */
	function _query($ldapQuery, $inputarr = false)
	{
		$rs = ldap_search($this->_connectionID, $this->database, $ldapQuery);

		$this->_errorMsg = ($rs) ? '' : 'Search error on ' . $ldapQuery . ': ' . ldap_error($this->_connectionID);

		return $rs;
	}


	/**
	 * Returns the last error number from previous AD operation.
	 *
	 * @return int The last error number.
	 */
	function errorNo()
	{
		return @ldap_errno($this->_connectionID);
	}

	/**
	 * closes the LDAP connection
	 *
	 * @return void
	 */
	function _close()
	{
		if (is_resource($this->_connectionID)) {
			@ldap_close($this->_connectionID);
		}

		$this->_connectionID = false;
	}

	/**
	 * Switches the baseDN to a new value. Just like _connect(),
	 * its impossible to determine if the parameter is correct 
	 * until you issue a query
	 *  
	 * @return bool true
	 * @noinspection PhpParameterNameChangedDuringInheritanceInspection
	 */
	public function selectDB($baseDN)
	{
		$this->database = $baseDN;
		return true;
	}

	/**
	 * Get server version info.
	 *
	 * @return string[]|false Array with multiple string elements that define the connection
	 */
	public function serverInfo()
	{
		if (!empty($this->version)) {
			return $this->version;
		}

		if (!is_resource($this->_connectionID)) {
			return false;
		}

		$version = array();
		/*
		Determines how aliases are handled during search.
		LDAP_DEREF_NEVER (0x00)
		LDAP_DEREF_SEARCHING (0x01)
		LDAP_DEREF_FINDING (0x02)
		LDAP_DEREF_ALWAYS (0x03)
		The LDAP_DEREF_SEARCHING value means aliases are dereferenced during the search but
		not when locating the base object of the search. The LDAP_DEREF_FINDING value means
		aliases are dereferenced when locating the base object but not during the search.
		Default: LDAP_DEREF_NEVER
		*/
		ldap_get_option($this->_connectionID, LDAP_OPT_DEREF, $version['LDAP_OPT_DEREF']);
		switch ($version['LDAP_OPT_DEREF']) {
			case 0:
				$version['LDAP_OPT_DEREF'] = 'LDAP_DEREF_NEVER';
				break;
			case 1:
				$version['LDAP_OPT_DEREF'] = 'LDAP_DEREF_SEARCHING';
				break;
			case 2:
				$version['LDAP_OPT_DEREF'] = 'LDAP_DEREF_FINDING';
				break;
			case 3:
				$version['LDAP_OPT_DEREF'] = 'LDAP_DEREF_ALWAYS';
				break;
		}

		/*
		A limit on the number of entries to return from a search.
		LDAP_NO_LIMIT (0) means no limit.
		Default: LDAP_NO_LIMIT
		*/
		ldap_get_option($this->_connectionID, LDAP_OPT_SIZELIMIT, $version['LDAP_OPT_SIZELIMIT']);
		if ($version['LDAP_OPT_SIZELIMIT'] == 0) {
			$version['LDAP_OPT_SIZELIMIT'] = 'LDAP_NO_LIMIT';
		}

		/*
		A limit on the number of seconds to spend on a search.
		LDAP_NO_LIMIT (0) means no limit.
		Default: LDAP_NO_LIMIT
		*/
		ldap_get_option($this->_connectionID, LDAP_OPT_TIMELIMIT, $version['LDAP_OPT_TIMELIMIT']);
		if ($version['LDAP_OPT_TIMELIMIT'] == 0) {
			$version['LDAP_OPT_TIMELIMIT'] = 'LDAP_NO_LIMIT';
		}

		/*
		Determines whether the LDAP library automatically follows referrals returned by LDAP servers or not.
		LDAP_OPT_ON
		LDAP_OPT_OFF
		Default: ON
		*/
		ldap_get_option($this->_connectionID, LDAP_OPT_REFERRALS, $version['LDAP_OPT_REFERRALS']);
		if ($version['LDAP_OPT_REFERRALS'] == 0) {
			$version['LDAP_OPT_REFERRALS'] = 'LDAP_OPT_OFF';
		} else {
			$version['LDAP_OPT_REFERRALS'] = 'LDAP_OPT_ON';
		}

		/*
		Determines whether LDAP I/O operations are automatically restarted if they abort prematurely.
		LDAP_OPT_ON
		LDAP_OPT_OFF
		Default: OFF
		*/
		ldap_get_option($this->_connectionID, LDAP_OPT_RESTART, $version['LDAP_OPT_RESTART']);
		if ($version['LDAP_OPT_RESTART'] == 0) {
			$version['LDAP_OPT_RESTART'] = 'LDAP_OPT_OFF';
		} else {
			$version['LDAP_OPT_RESTART'] = 'LDAP_OPT_ON';
		}

		/*
		This option indicates the version of the LDAP protocol used when communicating with the primary LDAP server.
		LDAP_VERSION2 (2)
		LDAP_VERSION3 (3)
		Default: LDAP_VERSION2 (2)
		*/
		ldap_get_option($this->_connectionID, LDAP_OPT_PROTOCOL_VERSION, $version['LDAP_OPT_PROTOCOL_VERSION']);
		if ($version['LDAP_OPT_PROTOCOL_VERSION'] == 2) {
			$version['LDAP_OPT_PROTOCOL_VERSION'] = 'LDAP_VERSION2';
		} else {
			$version['LDAP_OPT_PROTOCOL_VERSION'] = 'LDAP_VERSION3';
		}

		/* The host name (or list of hosts) for the primary LDAP server. */
		ldap_get_option($this->_connectionID, LDAP_OPT_HOST_NAME, $version['LDAP_OPT_HOST_NAME']);
		ldap_get_option($this->_connectionID, LDAP_OPT_ERROR_NUMBER, $version['LDAP_OPT_ERROR_NUMBER']);
		ldap_get_option($this->_connectionID, LDAP_OPT_ERROR_STRING, $version['LDAP_OPT_ERROR_STRING']);
		ldap_get_option($this->_connectionID, LDAP_OPT_MATCHED_DN, $version['LDAP_OPT_MATCHED_DN']);

		return $this->version = $version;
	}
}

/*--------------------------------------------------------------------------------------
	Class Name: Recordset
--------------------------------------------------------------------------------------*/

class ADORecordSet_ldap extends ADORecordSet
{

	public $databaseType = "ldap";
	public $canSeek = false;

	protected $_entryID; /* keeps track of the entry resource identifier */

	protected $seekData = array();

	/**
	 * Constructor
	 *
	 * @param resource $queryID
	 * @param boolean $mode
	 */
	public function __construct($queryID, $mode = false)
	{
		parent::__construct($queryID, $mode);

		switch ($this->adodbFetchMode) {
			case ADODB_FETCH_NUM:
				$this->fetchMode = LDAP_NUM;
				break;
			case ADODB_FETCH_ASSOC:
				$this->fetchMode = LDAP_ASSOC;
				break;
			case ADODB_FETCH_DEFAULT:
			case ADODB_FETCH_BOTH:
			default:
				$this->fetchMode = LDAP_BOTH;
				break;
		}
	}

	/**
	 * Recordset initialization stub
	 *
	 * @return void
	 */
	protected function _initrs()
	{
		// This could be tweaked to respect the $COUNTRECS directive from ADODB
		// It's currently being used in the _fetch() function and the GetAssoc() function
		$this->_numOfRows = ldap_count_entries($this->connection->_connectionID, $this->_queryID);
	}


	/**
	 * Returns raw, database specific information about a field.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:recordset:fetchfield
	 *
	 * @param int $fieldOffset (Optional) The field number to get information for.
	 *
	 * @return ADOFieldObject|bool
	 */
	public function fetchField($fieldOffset = -1)
	{

		if (!array_key_exists($fieldOffset, $this->seekData)) {
			return false;
		}

		$o = new ADOFieldObject;
		$o->name = $this->seekData[$fieldOffset];
		$o->max_length = 1024;
		$o->type = 'C';

		return $o;
	}

	/**
	 * Attempt to fetch a result row using the current fetch mode and return whether or not this was successful.
	 *
	 * @return bool True if row was fetched successfully, otherwise false.
	 */
	public function _fetch()
	{
		if ($this->_currentRow >= $this->_numOfRows && $this->_numOfRows >= 0) {
			$this->EOF = true;
			return false;
		}
		$this->EOF = false;

		if ($this->_currentRow == 0) {
			$this->_entryID = ldap_first_entry($this->connection->_connectionID, $this->_queryID);
		} else {
			$this->_entryID = ldap_next_entry($this->connection->_connectionID, $this->_entryID);
		}

		$r = ldap_get_attributes($this->connection->_connectionID, $this->_entryID);

		if (!$r) {
			$this->EOF = true;
			return false;
		}

		$rowentries = $r['count'];
		unset($r['count']);

		$objectClass = false;

		if ($r[0] == 'objectClass') {
			/*
			* Always an array, move to the end of the row we want to use getAssoc
			*/
			$objectClass = $r['objectClass'];
			unset($r[0]);
			unset($r['objectClass']);
			unset($objectClass['count']);
		}

		$rowData = array();
		$rowIndex = 0;
		for ($rowentry = 0; $rowentry < $rowentries; $rowentry++) {
			if (!array_key_exists($rowentry, $r)) /*
				* We moved objectClass
				*/ {
				continue;
			}

			$eName = $r[$rowentry];
			$eData = $r[$eName];
			$dataEntries = $eData['count'];

			unset($eData['count']);

			if ($dataEntries == 1) {
				$element = $eData[0];
			} else {
				$element = $eData;
			}

			switch ($this->fetchMode) {
				case LDAP_ASSOC:
					$rowData[$eName] = $element;
					break;
				case LDAP_NUM:
					$rowData[$rowIndex] = $element;
					break;
				case LDAP_BOTH:
				default:
					$rowData[$eName] = $element;
					$rowData[$rowIndex] = $element;
					break;
			}

			$this->seekData[$rowIndex] = $eName;

			$rowIndex++;
		}

		if ($objectClass) {
			// Append the element onto the end of the row
			switch ($this->fetchMode) {
				case LDAP_ASSOC:
					$rowData['objectClass'] = $objectClass;
					break;
				case LDAP_NUM:
					$rowData[$rowIndex] = $objectClass;
					break;
				case LDAP_BOTH:
				default:
					$rowData['objectClass'] = $objectClass;
					$rowData[$rowIndex] = $objectClass;
					break;
			}
		}

		switch (ADODB_ASSOC_CASE) {
			case ADODB_ASSOC_CASE_UPPER:
				$rowData = array_change_key_case($rowData, CASE_UPPER);
				array_map('strtoupper', $this->seekData);
				break;
			case ADODB_ASSOC_CASE_LOWER:
				$rowData = array_change_key_case($rowData, CASE_LOWER);
				array_map('strtolower', $this->seekData);
				break;
		}

		$this->bind = array_flip($this->seekData);

		$this->fields = $rowData;
		$this->_numOfFields = count($rowData);

		return is_array($this->fields);
	}

	/**
	 * Frees the memory from a query recordset
	 *
	 * @return void
	 */
	public function _close()
	{
		if (is_resource($this->_queryID)) {
			@ldap_free_result($this->_queryID);
		}

		$this->_queryID = false;
	}

}
