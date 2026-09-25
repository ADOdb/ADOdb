<?php
/**
 * Firebird driver.
 *
 * Requires firebird client. Works on Windows and Unix.
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
 *
 * Driver was cloned from Interbase, so there's quite a lot of duplicated code
 * @noinspection DuplicatedCode
 * @noinspection PhpUnused
 */

// security - hide paths
if (!defined('ADODB_DIR')) die();

class ADODB_firebird extends ADOConnection {
	var $databaseType = "firebird";
	var $dataProvider = "firebird";
	var $replaceQuote = "''"; // string to use to replace quotes

	/**
	 * Output formats for DATE, TIMESTAMP and TIME columns.
	 *
	 * These are passed to the extension's ibase.dateformat, ibase.timestampformat
	 * and ibase.timeformat INI settings when connecting. They are expressed using
	 * strftime() rules, as expected by the PECL interbase extension and
	 * php-firebird up to 5.x. php-firebird 6.1+ interprets the INI settings
	 * using PHP date() rules instead; the driver detects this and converts the
	 * strings automatically (see {@see ADODB_firebird::strftimeToDateFormat()}).
	 * A format that does not contain a '%' is assumed to already use date()
	 * rules and is passed through unchanged.
	 */
	var $fbird_datefmt = '%Y-%m-%d'; // For hours,mins,secs change to '%Y-%m-%d %H:%M:%S';
	var $fmtDate = "'Y-m-d'";
	var $fbird_timestampfmt = "%Y-%m-%d %H:%M:%S";
	var $fbird_timefmt = "%H:%M:%S";
	var $fmtTimeStamp = "'Y-m-d, H:i:s'";
	var $concat_operator='||';
	var $_transactionID;

	/**
	 * Prepared statements, indexed by the resource id of the statement returned
	 * by prepare(): array('context' => string, 'stmt' => resource).
	 *
	 * A Firebird statement is bound to the transaction it was prepared in and
	 * becomes unusable once that transaction ends, so a statement returned by
	 * {@see prepare()} is transparently re-prepared when it is executed in
	 * another transaction (see {@see _getPreparedStatement()}). 'context'
	 * identifies the transaction the cached 'stmt' was prepared in. Entries
	 * are kept until the connection is closed.
	 *
	 * @var array
	 */
	private $preparedStatements = array();

	/**
	 * The transaction statements are executed in while in autocommit mode.
	 *
	 * The driver does not use the extension's default transaction (which is
	 * shared by every connection object using the same link, and whose
	 * isolation can only be configured globally): it starts its own, with the
	 * mode set through setTransactionMode(), commits it after every data
	 * modification and keeps it open across SELECTs while native result sets
	 * are being read.
	 *
	 * @var resource|false
	 */
	protected $autoTransactionID = false;

	/**
	 * Set when a rollback of the autocommit transaction failed: its content is
	 * then unknown (it may hold the partial work of a failed statement), so
	 * it must not be used or committed any more. Cleared by a successful
	 * rollback (rollbackTrans(), closing the failed recordset, close()).
	 *
	 * @var bool
	 */
	private $autoTransactionFailed = false;

	/**
	 * Error reported by the failed rollback: array(code, message).
	 *
	 * @var array
	 */
	private $autoTransactionError = array(0, '');

	/**
	 * Number of native result sets still open on the autocommit transaction.
	 * While it is greater than zero the transaction is committed with retain
	 * (see {@see _commitAutoTransaction()}) so that their cursors survive.
	 *
	 * @var int
	 */
	protected $openResults = 0;

	/**
	 * Affected rows of the last executed statement, read before the
	 * autocommit transaction is committed (the transaction resource is
	 * released by the commit).
	 *
	 * @var int|false
	 */
	private $lastAffectedRows = false;

	/**
	 * Transaction a result set returned by _query() belongs to, and whether
	 * the autocommit transaction must be committed when that result set is
	 * closed (rows are produced by a data-modifying statement, e.g. EXECUTE
	 * BLOCK ... SUSPEND, which keeps running while rows are fetched).
	 * Read by ADORecordset_firebird::_initrs().
	 *
	 * @var array array(resource|false $trans, bool $autocommit, bool $commitOnClose)
	 */
	private $lastResultInfo = array(false, false, false);

	/**
	 * Set when the result set produced by the last execute() call failed
	 * after ADOConnection::_Execute() obtained it: an error while fetching
	 * its rows, or a failed commit when it was closed. execute() then returns
	 * false, as the result set may have been materialised and closed within
	 * _Execute() already.
	 *
	 * @var bool
	 */
	private $lastResultFailed = false;

	/**
	 * Number of ADODB_firebird objects using each link resource, indexed by
	 * resource id.
	 *
	 * The extension returns the same link resource for repeated connections
	 * with identical parameters, and php-firebird 6.x closes the link on the
	 * first fbird_close() call regardless of how many connection objects use
	 * it, so the link is only closed when its last user is closed.
	 *
	 * @var int[]
	 */
	private static $linkUsers = array();

	/**
	 * Returns the (trimmed, lower-cased) relation name and its type (TABLE or VIEW).
	 */
	public $metaTablesSQL = "SELECT TRIM(LOWER(rdb\$relation_name)), CASE WHEN rdb\$view_blr IS NULL THEN 'TABLE' ELSE 'VIEW' END FROM rdb\$relations";
	//OPN STUFF start

	var $metaColumnsSQL = "select lower(a.rdb\$field_name), a.rdb\$null_flag, a.rdb\$default_source, b.rdb\$field_length, b.rdb\$field_scale, b.rdb\$field_sub_type, b.rdb\$field_precision, b.rdb\$field_type, b.rdb\$character_length from rdb\$relation_fields a, rdb\$fields b where a.rdb\$field_source = b.rdb\$field_name and a.rdb\$relation_name = '%s' order by a.rdb\$field_position asc";
	//OPN STUFF end

	/**
	 * The sequence is created without START WITH, whose meaning changed between
	 * Firebird 3 (sets the current value) and Firebird 4 (sets the next value);
	 * the current value is then set with SET GENERATOR, which behaves the same
	 * on all versions (see createSequence()).
	 */
	public $_genSeqSQL = "CREATE SEQUENCE %s";

	public $_setSeqSQL = "SET GENERATOR %s TO %s";

	public $_dropSeqSQL = "DROP SEQUENCE %s";

	var $hasGenID = true;
	var $_bindInputArray = true;
	var $sysDate = "cast('TODAY' as timestamp)";
	var $sysTimeStamp = "CURRENT_TIMESTAMP"; //"cast('NOW' as timestamp)";
	var $ansiOuter = true;
	var $hasAffectedRows = true;
	var $poorAffectedRows = false;
	var $blobEncodeType = 'C';
	/*
	* firebird custom optionally specifies the user role
	*/
	public $role = false;
	/*
	* firebird custom optionally specifies the connection buffers
	*/
	public $buffers = 0;

	/*
	* firebird custom optionally specifies database dialect
	*/
	public $dialect = 3;

	var $nameQuote = '';		/// string to use to quote identifiers and names

	function __construct()
	{
		parent::__construct();
		$this->setTransactionMode('');
	}

	/**
	 * Registers/unregisters a connection object using a link resource.
	 *
	 * @param resource $link
	 * @param int      $delta +1 or -1
	 *
	 * @return int Number of connection objects still using the link
	 */
	private static function _linkUsage($link, $delta)
	{
		$id = (int)$link;
		$count = (isset(self::$linkUsers[$id]) ? self::$linkUsers[$id] : 0) + $delta;
		if ($count > 0) {
			self::$linkUsers[$id] = $count;
		} else {
			unset(self::$linkUsers[$id]);
			$count = 0;
		}
		return $count;
	}

	/**
	 * Whether the connection is open.
	 *
	 * Overridden because a link closed through the extension (or by another
	 * connection object sharing it) is still a non-empty, albeit closed,
	 * resource; passing it to fbird_* functions throws a TypeError on PHP 8.
	 *
	 * @return bool
	 */
	public function isConnected()
	{
		return is_resource($this->_connectionID);
	}

	/**
	 * Returns the autocommit transaction, starting it if necessary.
	 *
	 * @return resource|false
	 */
	private function _autoTransaction()
	{
		if ($this->autoTransactionFailed && is_resource($this->autoTransactionID)) {
			$this->_reportAutoTransactionFailed();
			return false;
		}
		if (!is_resource($this->autoTransactionID)) {
			$this->autoTransactionID = @fbird_trans($this->_transmode, $this->_connectionID);
			$this->openResults = 0;
			if (!$this->autoTransactionID) {
				$this->_handleError();
				$this->autoTransactionID = false;
				return false;
			}
		}
		return $this->autoTransactionID;
	}

	/**
	 * Returns the transaction statements (and blob operations) currently run
	 * in: the explicit one started by beginTrans(), or the autocommit one.
	 *
	 * @return resource|false
	 */
	private function _currentTransaction()
	{
		if (!$this->autoCommit && is_resource($this->_transactionID)) {
			return $this->_transactionID;
		}
		return $this->_autoTransaction();
	}

	/**
	 * Commits the autocommit transaction.
	 *
	 * If native result sets are still open on it, their cursors would be
	 * closed by a full commit, so the transaction is committed with retain
	 * instead: the changes become durable while the transaction (its cursors,
	 * and the statements prepared in it) stays valid. Otherwise the
	 * transaction is ended; the next autocommit statement starts a new one.
	 *
	 * If the commit fails, the pending work (which the caller is told has
	 * failed) is rolled back so that a later commit cannot confirm it; the
	 * error reported through errorNo()/errorMsg() is the commit's. A
	 * transaction whose rollback failed is never committed.
	 *
	 * @param bool $force End the transaction even if result sets are open.
	 *
	 * @return bool
	 */
	protected function _commitAutoTransaction($force = false)
	{
		if (!is_resource($this->autoTransactionID)) {
			return true;
		}
		if ($this->autoTransactionFailed) {
			$this->_reportAutoTransactionFailed();
			return false;
		}
		if (!$force && $this->openResults > 0) {
			if ($this->_fbirdCommit($this->autoTransactionID, true)) {
				return true;
			}
		} elseif ($this->_fbirdCommit($this->autoTransactionID, false)) {
			$this->autoTransactionID = false;
			$this->openResults = 0;
			return true;
		}

		// Commit failed: report it, and discard the pending work
		$this->_handleError();
		$error = array($this->_errorCode, $this->_errorMsg);
		$this->_rollbackAutoTransaction($force);
		list($this->_errorCode, $this->_errorMsg) = $error;
		return false;
	}

	/**
	 * Commits through the extension. Tests override this boundary to fail
	 * before a native commit, leaving pending work for the normal recovery
	 * path in _commitAutoTransaction().
	 *
	 * @param resource $trans
	 * @param bool     $retain Keep the transaction and its native cursors.
	 *
	 * @return bool
	 */
	protected function _fbirdCommit($trans, $retain)
	{
		return $retain ? (bool)@fbird_commit_ret($trans) : (bool)@fbird_commit($trans);
	}

	/**
	 * Rolls back the autocommit transaction (with retain if native result sets
	 * are still open on it, see _commitAutoTransaction()).
	 *
	 * A failed rollback leaves the transaction in an unknown state: it is
	 * marked as failed and refused by _autoTransaction() and
	 * _commitAutoTransaction() until a rollback succeeds.
	 *
	 * @param bool $force End the transaction even if result sets are open.
	 *
	 * @return bool
	 */
	private function _rollbackAutoTransaction($force = false)
	{
		if (!is_resource($this->autoTransactionID)) {
			return true;
		}
		$retain = !$force && $this->openResults > 0;
		if (!$this->_fbirdRollback($this->autoTransactionID, $retain)) {
			$this->autoTransactionFailed = true;
			$code = fbird_errcode();
			$msg = fbird_errmsg();
			$this->autoTransactionError = array($code ?: -1, $msg ?: 'rollback failed');
			return false;
		}
		$this->autoTransactionFailed = false;
		if (!$retain) {
			$this->autoTransactionID = false;
			$this->openResults = 0;
		}
		return true;
	}

	/**
	 * Rolls back a transaction through the extension. Isolated so that tests
	 * can simulate a failure of the native call.
	 *
	 * @param resource $trans
	 * @param bool     $retain Roll back with retain (keep the transaction).
	 *
	 * @return bool
	 */
	protected function _fbirdRollback($trans, $retain)
	{
		return $retain ? (bool)@fbird_rollback_ret($trans) : (bool)@fbird_rollback($trans);
	}

	/**
	 * Sets the error state to describe the unusable autocommit transaction.
	 *
	 * @return void
	 */
	private function _reportAutoTransactionFailed()
	{
		$this->_errorCode = $this->autoTransactionError[0];
		$this->_errorMsg = 'The autocommit transaction could not be rolled back after a failed'
			. ' statement and cannot be used until it is rolled back (rollbackTrans())'
			. ' or the connection is closed: ' . $this->autoTransactionError[1];
	}

	/**
	 * Invokes the ADOdb error handler (raiseErrorFn), if one is set, for an
	 * error that occurred outside ADOConnection::_Execute(): while fetching
	 * rows or when committing on close. Call _handleError() first.
	 *
	 * @param string       $function 'FETCH' or 'CLOSE'
	 * @param string|false $sql      Statement concerned, if any
	 *
	 * @return void
	 */
	private function _raiseError($function, $sql = false)
	{
		if ($fn = $this->raiseErrorFn) {
			$fn($this->databaseType, $function, $this->ErrorNo(), $this->ErrorMsg(), $sql, false, $this);
		}
	}

	/**
	 * Called by ADORecordset_firebird when fetching a row fails.
	 *
	 * Records the error, so that errorNo()/errorMsg() report it, marks the
	 * result set of the current execute() call as failed and invokes the
	 * ADOdb error handler. If the statement modifies data, the work it did
	 * in the autocommit transaction is rolled back right away (with retain,
	 * as its own cursor is still open), so that no later commit (another
	 * statement, Close() of the connection) can confirm it, whenever the
	 * recordset is closed.
	 *
	 * @param string $sql         Statement being fetched
	 * @param int    $autoTransId Resource id of the autocommit transaction
	 *                            the result set was opened on, 0 for an
	 *                            explicit transaction
	 * @param bool   $modifies    Whether the statement modifies data
	 *
	 * @return void
	 */
	public function _fetchFailed($sql, $autoTransId = 0, $modifies = false)
	{
		$this->_handleError();
		$this->lastResultFailed = true;
		if ($modifies && $autoTransId && is_resource($this->autoTransactionID)
			&& $autoTransId === (int)$this->autoTransactionID
		) {
			// The statement's error is the one reported; a failed rollback
			// marks the transaction as unusable (see _rollbackAutoTransaction())
			$error = array($this->_errorCode, $this->_errorMsg);
			$this->_rollbackAutoTransaction();
			list($this->_errorCode, $this->_errorMsg) = $error;
		}
		// Last: the handler may throw
		$this->_raiseError('FETCH', $sql);
	}

	/**
	 * Called by ADORecordset_firebird when a native result set is released.
	 *
	 * For a data-modifying statement, the work done while its rows were being
	 * fetched (code after a SUSPEND in an EXECUTE BLOCK) is committed here;
	 * if fetching failed it is rolled back instead, as Firebird keeps the
	 * changes made before the failing row.
	 *
	 * @param int    $autoTransId   Resource id of the autocommit transaction
	 *                              the result set was opened on (0 if it was
	 *                              opened in an explicit transaction).
	 * @param bool   $commitOnClose Whether the statement modifies data.
	 * @param bool   $fetchFailed   Whether fetching its rows failed.
	 * @param string $sql           Statement, for error reporting.
	 *
	 * @return bool false if the final commit failed
	 */
	public function _releaseResult($autoTransId, $commitOnClose, $fetchFailed = false, $sql = false)
	{
		if (!$autoTransId || !is_resource($this->autoTransactionID)
			|| $autoTransId !== (int)$this->autoTransactionID
		) {
			return true;
		}
		if ($this->openResults > 0) {
			$this->openResults--;
		}
		if (!$commitOnClose) {
			return true;
		}
		if ($fetchFailed) {
			// Rolled back when the error occurred (_fetchFailed()); end the
			// transaction if nothing else depends on it
			if ($this->openResults == 0) {
				$this->_rollbackAutoTransaction();
			}
			return true;
		}
		if (!$this->_commitAutoTransaction()) {
			$this->lastResultFailed = true;
			// Last: the handler may throw
			$this->_raiseError('CLOSE', $sql);
			return false;
		}
		return true;
	}

	/**
	 * Information about the result set returned by the last _query() call,
	 * for ADORecordset_firebird::_initrs().
	 *
	 * @return array array(resource|false $trans, bool $autocommit, bool $commitOnClose)
	 */
	public function _lastResultInfo()
	{
		return $this->lastResultInfo;
	}

	/**
	 * Sets the transaction parameters used by this connection.
	 *
	 * The mode applies to explicit transactions (beginTrans()) and to the
	 * transaction autocommit statements are executed in. It only affects
	 * this connection object. The default is IBASE_WAIT | IBASE_REC_VERSION |
	 * IBASE_COMMITTED (read committed), a more practical choice than the
	 * extension's IBASE_DEFAULT (snapshot).
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:settransactionmode
	 *
	 * @param string|int $transaction_mode Either a combination of the
	 *                                     extension's IBASE_* transaction
	 *                                     flags, or one of the ADOdb names
	 *                                     'READ UNCOMMITTED', 'READ COMMITTED',
	 *                                     'REPEATABLE READ', 'SERIALIZABLE'.
	 *                                     Empty for the default.
	 *
	 * @return void
	 */
	public function setTransactionMode($transaction_mode)
	{
		if (!defined('IBASE_COMMITTED')) {
			// Extension not loaded; connecting will fail anyway
			$this->_transmode = 0;
			return;
		}

		if (is_string($transaction_mode) && !is_numeric($transaction_mode)) {
			switch (strtoupper(trim($transaction_mode))) {
				case 'REPEATABLE READ':
					$transaction_mode = IBASE_WAIT | IBASE_CONCURRENCY;
					break;
				case 'SERIALIZABLE':
					$transaction_mode = IBASE_WAIT | IBASE_CONSISTENCY;
					break;
				case 'READ UNCOMMITTED':
				case 'READ COMMITTED':
					$transaction_mode = IBASE_WAIT | IBASE_REC_VERSION | IBASE_COMMITTED;
					break;
				default:
					$transaction_mode = 0;
			}
		}

		$this->_transmode = (int)$transaction_mode;
		if (empty($this->_transmode)) {
			$this->_transmode = IBASE_WAIT | IBASE_REC_VERSION | IBASE_COMMITTED;
		}
	}

	/**
	 * Converts a strftime()-style format string to PHP date() rules.
	 *
	 * php-firebird 6.1+ interprets the ibase.dateformat, ibase.timeformat and
	 * ibase.timestampformat INI settings using date() rules (older versions of
	 * the extension use strftime() rules). This lets the driver, and existing
	 * code that customized the fbird_*fmt properties, keep using strftime()
	 * syntax regardless of the extension version.
	 *
	 * Only specifiers with an exact date() equivalent are converted (the
	 * space-padded %e, %k and %l become the unpadded j, G and g). Month and
	 * day names and the AM/PM marker (%a, %A, %b, %B, %h, %p, %P) depend on
	 * the LC_TIME locale with strftime() while date() always produces English
	 * names, so they are only converted when the current LC_TIME locale is C,
	 * POSIX or an English one. Specifiers without an equivalent (%j, %U, %W,
	 * %C, %g, the locale-dependent %c, %x, %X, %r, and the %E/%O modifiers)
	 * make the conversion fail, in which case false is returned and the caller
	 * should switch to a date() format.
	 *
	 * Format strings that do not contain a '%' are returned unchanged, as they
	 * are assumed to already follow date() rules.
	 *
	 * @param string $format strftime() format string.
	 *
	 * @return string|false date() format string, or false if the format
	 *                      cannot be converted.
	 */
	public static function strftimeToDateFormat($format)
	{
		if (strpos($format, '%') === false) {
			return $format;
		}

		static $map = array(
			'Y' => 'Y', 'y' => 'y', 'G' => 'o',
			'm' => 'm', 'd' => 'd', 'e' => 'j', 'u' => 'N', 'w' => 'w', 'V' => 'W',
			'H' => 'H', 'k' => 'G', 'I' => 'h', 'l' => 'g', 'M' => 'i', 'S' => 's',
			'z' => 'O', 'Z' => 'T', 's' => 'U',
			'F' => 'Y-m-d', 'T' => 'H:i:s', 'R' => 'H:i', 'D' => 'm/d/y',
			'n' => "\n", 't' => "\t", '%' => '%',
		);
		// Names, only equivalent in an English (or C/POSIX) LC_TIME locale
		static $names = array(
			'B' => 'F', 'b' => 'M', 'h' => 'M', 'A' => 'l', 'a' => 'D', 'p' => 'A', 'P' => 'a',
		);

		$locale = setlocale(LC_TIME, '0');
		$englishNames = $locale === false || $locale === 'C' || $locale === 'POSIX'
			|| preg_match('/^(C|POSIX|en)([._@-]|$)/i', $locale);

		$result = '';
		$len = strlen($format);
		for ($i = 0; $i < $len; $i++) {
			$ch = $format[$i];
			if ($ch == '%') {
				$spec = $i + 1 < $len ? $format[$i + 1] : '';
				if (isset($map[$spec])) {
					$result .= $map[$spec];
				} elseif (isset($names[$spec]) && $englishNames) {
					$result .= $names[$spec];
				} else {
					// No exact date() equivalent
					return false;
				}
				$i++;
			} elseif (($ch >= 'a' && $ch <= 'z') || ($ch >= 'A' && $ch <= 'Z') || $ch == '\\') {
				// Literal letters (and backslashes) must be escaped in date() formats
				$result .= '\\' . $ch;
			} else {
				$result .= $ch;
			}
		}

		return $result;
	}

	/**
	 * Connect to a database.
	 *
	 * @todo add: parameter int $port, parameter string $socket
	 *
	 * @param string|null $argHostname (Optional) The host to connect to.
	 * @param string|null $argUsername (Optional) The username to connect as.
	 * @param string|null $argPassword (Optional) The password to connect with.
	 * @param string|null $argDatabasename (Optional) The name of the database to start in when connected.
	 * @param bool $persist (Optional) Whether or not to use a persistent connection.
	 *
	 * @return bool|null True if connected successfully, false if connection failed, or null if the mysqli extension
	 * isn't currently loaded.
	 */
	public function _connect($argHostname, $argUsername, $argPassword, $argDatabasename,$persist=false)
	{
		if (!function_exists('fbird_pconnect'))
			return null;

		if ($argDatabasename)
			$argHostname .= ':'.$argDatabasename;

		$fn = ($persist) ? 'fbird_pconnect':'fbird_connect';

		/*
		* Now merge in the standard connection parameters setting
		*/
		foreach ($this->connectionParameters as $options)
		{
			foreach($options as $k=>$v)
			{
				switch($k){
				case 'role':
					$this->role = $v;
					break;
				case 'dialect':
					$this->dialect = $v;
					break;
				case 'buffers':
					$this->buffers = $v;
				}
			}
		}

		if ($this->role)
			$this->_connectionID = $fn($argHostname,$argUsername,$argPassword,
					$this->charSet,$this->buffers,$this->dialect,$this->role);
		else
			$this->_connectionID = $fn($argHostname,$argUsername,$argPassword,
					$this->charSet,$this->buffers,$this->dialect);

		if ($this->dialect == 1) { // http://www.ibphoenix.com/ibp_60_del_id_ds.html
			$this->replaceQuote = "";
		}
		if ($this->_connectionID === false) {
			$this->_handleError();
			return false;
		}

		// php-firebird 6.1+ (which introduced the IBASE_PHPDATE constant) expects
		// the format INI settings to follow PHP date() rules instead of strftime().
		$formats = array(
			'ibase.timestampformat' => $this->fbird_timestampfmt,
			'ibase.dateformat' => $this->fbird_datefmt,
			'ibase.timeformat' => $this->fbird_timefmt,
		);
		$useDateRules = defined('IBASE_PHPDATE');
		foreach ($formats as $ini => $fmt) {
			if ($useDateRules) {
				$converted = self::strftimeToDateFormat($fmt);
				if ($converted === false) {
					trigger_error(
						"ADOdb firebird: the strftime() format '$fmt' has no PHP date() equivalent;"
						. " $ini is left at its current value ('" . ini_get($ini) . "')."
						. " Set the corresponding fbird_*fmt property to a date() format.",
						E_USER_WARNING
					);
					continue;
				}
				$fmt = $converted;
			}
			ini_set($ini, $fmt);
		}

		$this->preparedStatements = array();
		$this->autoTransactionID = false;
		$this->autoTransactionFailed = false;
		$this->openResults = 0;
		self::_linkUsage($this->_connectionID, 1);

		return true;
	}

	/**
	 * Connect to a database with a persistent connection.
	 *
	 * @param string|null $argHostname The host to connect to.
	 * @param string|null $argUsername The username to connect as.
	 * @param string|null $argPassword The password to connect with.
	 * @param string|null $argDatabasename The name of the database to start in when connected.
	 *
	 * @return bool|null True if connected successfully, false if connection failed, or null if the mysqli extension
	 * isn't currently loaded.
	 */
	function _pconnect($argHostname, $argUsername, $argPassword, $argDatabasename)
	{
		return $this->_connect($argHostname, $argUsername, $argPassword, $argDatabasename,true);
	}


	public function metaPrimaryKeys($table,$owner_notused=false,$internalKey=false)
	{
		if ($internalKey) {
			return array('RDB$DB_KEY');
		}

		$table = strtoupper($table);

		$sql = 'SELECT S.RDB$FIELD_NAME AFIELDNAME
	FROM RDB$INDICES I JOIN RDB$INDEX_SEGMENTS S ON I.RDB$INDEX_NAME=S.RDB$INDEX_NAME
	WHERE I.RDB$RELATION_NAME=\''.$table.'\' and I.RDB$INDEX_NAME like \'RDB$PRIMARY%\'
	ORDER BY I.RDB$INDEX_NAME,S.RDB$FIELD_POSITION';

		$a = $this->GetCol($sql,false,true);
		if ($a && sizeof($a)>0) return $a;
		return false;
	}

	/**
	 * Get information about the current Firebird server.
	 *
	 * @return array
	 */
	public function serverInfo()
	{
		$arr['dialect'] = $this->dialect;
		switch($arr['dialect']) {
			case '':
			case '1':
				$s = 'Firebird Dialect 1';
				break;
			case '2':
				$s = 'Firebird Dialect 2';
				break;
			default:
			case '3':
				$s = 'Firebird Dialect 3';
				break;
		}
		// Engine version is available through the SYSTEM context since Firebird 2.1
		$version = false;
		if ($this->isConnected()) {
			try {
				$version = @$this->getOne("SELECT rdb\$get_context('SYSTEM', 'ENGINE_VERSION') FROM rdb\$database");
			} catch (Exception $e) {
				// Query not supported, fall back to the dialect-based description
			}
		}
		if ($version) {
			$arr['version'] = trim($version);
			$s = 'Firebird ' . $arr['version'] . ', ' . $s;
		} else {
			$arr['version'] = ADOConnection::_findvers($s);
		}
		$arr['description'] = $s;
		return $arr;
	}

	/**
	 * Begin a Transaction. Must be followed by CommitTrans() or RollbackTrans().
	 *
	 * @return bool true if succeeded or false if database does not support transactions
	 */
	public function beginTrans()
	{
		if ($this->transOff) return true;
		if (!$this->isConnected()) return false;

		// End the autocommit transaction (it only holds SELECT work at this
		// point, modifications are committed immediately) unless a native
		// result set still depends on it, so that statements executed after
		// the explicit transaction is committed run in a fresh transaction.
		if ($this->openResults == 0) {
			// A failed commit is reported through errorNo(); the explicit
			// transaction is independent of the autocommit one
			$this->_commitAutoTransaction();
		}

		/*
		* We manage the transaction mode via fbird_trans
		*/
		$this->_transactionID = fbird_trans( $this->_transmode, $this->_connectionID );
		if (!$this->_transactionID) {
			$this->_handleError();
			$this->_transactionID = false;
			return false;
		}

		$this->transCnt += 1;
		$this->autoCommit = false;

		return true;
	}


	/**
	 * Commits a transaction.
	 *
	 * @param bool $ok  false to rollback transaction, true to commit
	 *
	 * @return bool
	 */
	public function commitTrans($ok=true)
	{
		if (!$ok) {
			return $this->rollbackTrans();
		}
		if ($this->transOff) {
			return true;
		}
		if ($this->transCnt) {
			$this->transCnt -= 1;
		}
		$this->autoCommit = true;
		if (is_resource($this->_transactionID)) {
			$ret = fbird_commit($this->_transactionID);
			if (!$ret) {
				$this->_handleError();
			}
		} else {
			// No explicit transaction: end the autocommit one (unless result
			// sets are still open on it) so that its work is final.
			$ret = $this->_commitAutoTransaction();
		}
		$this->_transactionID = false;
		return $ret;
	}

	function _affectedrows()
	{
		return $this->lastAffectedRows;
	}

	/**
	 * Rollback a smart transaction.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:rollbacktrans
	 *
	 * @return bool
	 */
	public function rollbackTrans()
	{
		if ($this->transOff) {
			return true;
		}
		if ($this->transCnt) {
			$this->transCnt -= 1;
		}
		$ret = false;
		$this->autoCommit = true;

		if (is_resource($this->_transactionID)) {
			$ret = fbird_rollback($this->_transactionID);
			if (!$ret) {
				$this->_handleError();
			}
		} else {
			// No explicit transaction: roll back the autocommit one (with
			// retain if result sets are still open on it). Committed
			// modifications are unaffected; this discards the pending work
			// of a failed statement whose rollback had failed, making the
			// transaction usable again.
			$ret = $this->_rollbackAutoTransaction();
			if (!$ret) {
				$this->_reportAutoTransactionFailed();
			}
		}
		$this->_transactionID = false;

		return $ret;
	}

	/**
	 * Get a list of indexes on the specified table.
	 *
	 * @param string $table The name of the table to get indexes for.
	 * @param bool $primary (Optional) Whether or not to include the primary key.
	 * @param bool $owner (Optional) Unused.
	 *
	 * @return array|bool An array of the indexes, or false if the query to get the indexes failed.
	 */
	public function metaIndexes($table, $primary = false, $owner = false)
	{
		// save old fetch mode
		global $ADODB_FETCH_MODE;
		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;

		if ($this->fetchMode !== FALSE) {
				$savem = $this->SetFetchMode(FALSE);
		}

		$table = strtoupper($table);
		$sql = "SELECT * FROM RDB\$INDICES WHERE RDB\$RELATION_NAME = '".$table."'";
		if (!$primary) {
			$sql .= " AND RDB\$INDEX_NAME NOT LIKE 'RDB\$%'";
		} else {
			$sql .= " AND RDB\$INDEX_NAME NOT LIKE 'RDB\$FOREIGN%'";
		}
		// get index details
		$rs = $this->execute($sql);
		if (!is_object($rs)) {
			// restore fetchmode
			if (isset($savem)) {
				$this->SetFetchMode($savem);
			}
			$ADODB_FETCH_MODE = $save;
			return false;
		}
		$indexes = array();
		while ($row = $rs->FetchRow()) {

			$index = trim($row[0]);
			if (!isset($indexes[$index])) {
				if (is_null($row[3])) {
					$row[3] = 0;
				}
				$indexes[$index] = array(
					'unique' => ($row[3] == 1),
					'columns' => array()
				);
			}
			$sql = sprintf("SELECT * FROM RDB\$INDEX_SEGMENTS WHERE RDB\$INDEX_NAME = '%s' ORDER BY RDB\$FIELD_POSITION ASC",$index);
			$rs1 = $this->execute($sql);
			while ($row1 = $rs1->FetchRow()) {
				$indexes[$index]['columns'][$row1[2]] = trim($row1[1]);
			}
		}

		// restore fetchmode
		if (isset($savem)) {
			$this->SetFetchMode($savem);
		}
		$ADODB_FETCH_MODE = $save;

		return $indexes;
	}

	/**
	 * Lock a table row for a duration of a transaction.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:rowlock
	 * @link https://firebirdsql.org/refdocs/langrefupd21-notes-withlock.html
	 *
	 * @param string $table The table(s) to lock rows for.
	 * @param string $where (Optional) The WHERE clause to use to determine which rows to lock.
	 * @param string $col (Optional) The columns to select.
	 *
	 * @return bool True if the locking SQL statement executed successfully, otherwise false.
	 */
	public function rowLock($table,$where,$col=false)
	{
		if ($this->transCnt==0)
			$this->beginTrans();

		if ($where) $where = ' where '.$where;
		$rs = $this->execute("SELECT $col FROM $table $where FOR UPDATE WITH LOCK");
		return !empty($rs);
	}

	/**
	 * Creates a sequence in the database.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:createsequence
	 *
	 * @param string $seqname The sequence name.
	 * @param int $startID The start id.
	 *
	 * @return ADORecordSet|bool A record set if executed successfully, otherwise false.
	 */
	public function createSequence($seqname='adodbseq', $startID = 1)
	{
		$ok = $this->execute(sprintf($this->_genSeqSQL, $seqname));
		if ($ok && $startID != 1) {
			// A new sequence's current value is 0, so the first value
			// generated is 1; adjust the current value for other start ids.
			$ok = $this->execute(sprintf($this->_setSeqSQL, $seqname, (int)$startID - 1));
		}
		return $ok;
	}

	/**
	 * A portable method of creating sequence numbers.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:genid
	 *
	 * @param string $seqname (Optional) The name of the sequence to use.
	 * @param int $startID (Optional) The point to start at in the sequence.
	 *
	 * @return int
	 */
	public function genID($seqname='adodbseq',$startID=1)
	{
		$getnext = ("SELECT Gen_ID($seqname,1) FROM RDB\$DATABASE");
		$rs = @$this->Execute($getnext);
		if (!$rs) {
			$this->createSequence($seqname, $startID);
			$rs = $this->Execute($getnext);
		}
		if ($rs && !$rs->EOF) {
			$this->genID = (int) reset($rs->fields);
		}
		else {
			$this->genID = 0; // false
		}

		if ($rs) {
			$rs->Close();
		}

		return $this->genID;
	}

	function selectDB($dbName)
	{
		return false;
	}

	function _handleError()
	{
		$this->_errorCode = fbird_errcode();
		$this->_errorMsg  = fbird_errmsg();
	}


	public function errorNo()
	{
		return (int) $this->_errorCode;
	}

	function errorMsg()
	{
			return $this->_errorMsg;
	}

	/**
	 * Prepares an SQL statement and returns a handle to use.
	 *
	 * The statement is prepared in the current transaction (the explicit one
	 * started by beginTrans(), or this object's own transaction in
	 * autocommit mode). Firebird statements are bound to the transaction they
	 * were prepared in, so when that transaction ends the driver transparently
	 * re-prepares the statement the next time it is executed. To get the full
	 * benefit of prepared statements (a single prepare for many executions),
	 * execute them inside an explicit transaction.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:prepare
	 *
	 * @param string $sql The SQL to prepare.
	 *
	 * @return bool|array The SQL that was provided and the prepared statement,
	 *                    or false if the preparation fails
	 */
	public function prepare($sql)
	{
		$conn = $this->_currentTransaction();
		if (!$conn) {
			return false;
		}
		$stmt = $this->_prepareStatement($conn, $sql);
		if (!$stmt) {
			return false;
		}
		$this->preparedStatements[(int)$stmt] = array(
			'context' => (int)$conn,
			'stmt' => $stmt,
		);
		return array($sql, $stmt);
	}

	/**
	 * Prepares a statement in the given transaction.
	 *
	 * @param resource $trans Transaction to prepare the statement in.
	 * @param string   $sql   The SQL to prepare.
	 *
	 * @return resource|false The statement, or false if preparation failed
	 *                        (error is available via errorNo()/errorMsg()).
	 */
	private function _prepareStatement($trans, $sql)
	{
		if (!$this->isConnected()) {
			return false;
		}

		$stmt = @fbird_prepare($trans, $sql);
		$this->_handleError();

		return $stmt ?: false;
	}

	/**
	 * Returns a statement resource usable in the given transaction for a
	 * prepared statement array returned by prepare().
	 *
	 * Each statement returned by prepare() keeps its own identity (two
	 * prepare() calls with the same SQL yield independent statements and
	 * cursors); a statement is only re-prepared when the transaction it was
	 * prepared in is not the one it is executed in.
	 *
	 * @param resource $trans Transaction the statement will be executed in.
	 * @param array    $sql   Array as returned by prepare().
	 *
	 * @return resource|false
	 */
	private function _getPreparedStatement($trans, $sql)
	{
		$key = isset($sql[1]) ? (int)$sql[1] : 0;
		$context = (int)$trans;

		if ($key && isset($this->preparedStatements[$key])
			&& $this->preparedStatements[$key]['context'] === $context
			&& is_resource($this->preparedStatements[$key]['stmt'])
		) {
			return $this->preparedStatements[$key]['stmt'];
		}

		// Prepared in a transaction that has ended, or on another connection
		// object: re-prepare in the current transaction (a previously
		// re-prepared statement for this key is released).
		$stmt = $this->_prepareStatement($trans, $sql[0]);
		if (!$stmt) {
			return false;
		}
		$this->preparedStatements[$key ?: (int)$stmt] = array('context' => $context, 'stmt' => $stmt);

		return $stmt;
	}

	/**
	 * Executes a statement.
	 *
	 * Adds to ADOConnection::execute() the failure of a result set that
	 * ADOConnection::_Execute() materialised (and closed) itself, with
	 * $ADODB_COUNTRECS on: an error while fetching its rows or a failed
	 * final commit make execute() return false.
	 *
	 * @param string|array $sql
	 * @param array|false  $inputarr
	 *
	 * @return ADORecordSet|bool
	 */
	public function execute($sql, $inputarr = false)
	{
		$this->lastResultFailed = false;
		$rs = parent::execute($sql, $inputarr);
		if ($rs && $this->lastResultFailed) {
			return false;
		}
		return $rs;
	}

	/**
	 * Execute a query.
	 *
	 * @param string|array $sql        Query to execute.
	 * @param array        $inputarr   An optional array of parameters.
	 *
	 * @return object|bool Query identifier or true if execution successful, false if failed.
	 */
	function _query($sql, $inputarr = false)
	{
		if (!$this->isConnected()) {
			return false;
		}

		$this->lastAffectedRows = false;
		$this->lastResultInfo = array(false, false, false);

		$explicit = !$this->autoCommit && is_resource($this->_transactionID);
		$conn = $explicit ? $this->_transactionID : $this->_autoTransaction();
		if (!$conn) {
			return false;
		}

		if (is_array($sql)) {
			// Prepared statement
			$stmt = $this->_getPreparedStatement($conn, $sql);
			if (!$stmt) {
				return false;
			}
			$fn = 'fbird_execute';
			$args = [$stmt];
		} else {
			$fn = 'fbird_query';
			$args = [$conn, $sql];
		}
		if (is_array($inputarr)) {
			// Firebird only supports positional parameters; drop any keys
			$args = array_merge($args, array_values($inputarr));
		}
		$ret = call_user_func_array($fn, $args);

		$this->_handleError();
		if ($ret === false) {
			return false;
		}

		// Read before the transaction is committed, which releases it
		$this->lastAffectedRows = @fbird_affected_rows($conn);

		// fbird_query() and fbird_execute() return the number of affected rows,
		// or NULL for a RETURNING / EXECUTE PROCEDURE statement that produced
		// no row; ADOConnection::_Execute() expects true in both cases.
		if (is_numeric($ret) || $ret === null) {
			$ret = true;
		}

		// Statements returning rows can modify data too (INSERT/UPDATE/DELETE/
		// MERGE ... RETURNING, EXECUTE PROCEDURE / BLOCK): everything but a
		// SELECT is committed in autocommit mode. A block may keep modifying
		// data while its rows are fetched (code after a SUSPEND), so the
		// transaction is committed again when such a result set is closed.
		$modifies = $ret === true
			|| !$this->_isSelectStatement(is_array($sql) ? $sql[0] : $sql);

		if (is_resource($ret)) {
			// Open cursor, released in ADORecordset_firebird::_close()
			if (!$explicit) {
				$this->openResults++;
			}
			$this->lastResultInfo = array($conn, !$explicit, !$explicit && $modifies);
		}

		if (!$explicit && $modifies && !$this->_commitAutoTransaction()) {
			// The statement is not effective, report failure to the caller
			// (error state set by _commitAutoTransaction())
			if (is_resource($ret)) {
				@fbird_free_result($ret);
				if ($this->openResults > 0 && is_resource($this->autoTransactionID)) {
					$this->openResults--;
				}
			}
			$this->lastResultInfo = array(false, false, false);
			return false;
		}

		return $ret;
	}

	/**
	 * Whether the statement is a SELECT (or a CTE), i.e. does not modify data.
	 *
	 * @param string $sql
	 *
	 * @return bool
	 */
	private function _isSelectStatement($sql)
	{
		// Skip leading whitespace and comments
		return (bool)preg_match('/^\s*(?:(?:--[^\r\n]*|\/\*.*?\*\/)\s*)*(?:select|with)\b/is', (string)$sql);
	}

	/**
	 * Closes the connection.
	 *
	 * The object is disconnected (and the link's user count decremented)
	 * exactly once, before the error handler is invoked for a failed final
	 * commit: the handler may throw, which would otherwise skip the reset of
	 * _connectionID in ADOConnection::Close() and make a repeated Close()
	 * decrement the count again, closing a link shared with another object.
	 *
	 * @return bool false if the final commit of the autocommit transaction
	 *              failed or the link could not be closed
	 */
	function _close()
	{
		$this->preparedStatements = array();

		if (!$this->isConnected()) {
			return false;
		}
		$link = $this->_connectionID;
		$this->_connectionID = false;

		if (is_resource($this->_transactionID)) {
			@fbird_rollback($this->_transactionID);
		}
		$this->_transactionID = false;
		$this->autoCommit = true;
		$this->transCnt = 0;

		// Make the autocommit transaction's work final (its cursors are lost);
		// a transaction whose rollback failed is rolled back again, never
		// committed. Whatever the outcome, drop the reference: the extension
		// rolls back a still active transaction when its resource is freed.
		if ($this->autoTransactionFailed) {
			$committed = $this->_rollbackAutoTransaction(true);
			if (!$committed) {
				$this->_reportAutoTransactionFailed();
			}
		} else {
			$committed = $this->_commitAutoTransaction(true);
		}
		$this->autoTransactionID = false;
		$this->openResults = 0;

		// The link is shared with other connection objects using the same
		// connection parameters; only close it when the last one is closed.
		$closed = true;
		if (self::_linkUsage($link, -1) == 0) {
			$closed = (bool)@fbird_close($link);
		}

		if (!$committed) {
			// Last: the handler may throw
			$this->_raiseError('CLOSE');
			return false;
		}
		return $closed;
	}

	//OPN STUFF start
	/**
	 * Sets the type, max_length and scale of a field object from the
	 * RDB$FIELDS system table values.
	 *
	 * @param ADOFieldObject $fld
	 * @param int            $ftype      RDB$FIELD_TYPE (blr type code)
	 * @param int            $flen       RDB$FIELD_LENGTH (bytes)
	 * @param int            $fscale     RDB$FIELD_SCALE
	 * @param int            $fsubtype   RDB$FIELD_SUB_TYPE
	 * @param int            $fprecision RDB$FIELD_PRECISION
	 * @param bool           $dialect3
	 * @param int|null       $fcharlen   RDB$CHARACTER_LENGTH (characters)
	 *
	 * @return void
	 */
	function _ConvertFieldType(&$fld, $ftype, $flen, $fscale, $fsubtype, $fprecision, $dialect3, $fcharlen = null)
	{
		$fscale = abs($fscale);
		$fld->max_length = $flen;
		$fld->scale = null;
		switch($ftype){
		case 7:  // SMALLINT
		case 8:  // INTEGER
		case 16: // BIGINT
		case 26: // INT128 (Firebird 4+)
			$intTypes = array(7 => 'smallint', 8 => 'integer', 16 => 'bigint', 26 => 'int128');
			if ($dialect3) {
				// Exact numerics are stored in an integer type; the sub_type
				// tells whether the column was declared NUMERIC or DECIMAL
				switch($fsubtype){
					case 1:
						$fld->type = 'numeric';
						$fld->max_length = $fprecision;
						$fld->scale = $fscale;
						break;
					case 2:
						$fld->type = 'decimal';
						$fld->max_length = $fprecision;
						$fld->scale = $fscale;
						break;
					default:
						$fld->type = $intTypes[$ftype];
						break;
				} // switch
			} else {
				if ($fscale !=0) {
					$fld->type = 'decimal';
					$fld->scale = $fscale;
					$fld->max_length = ($ftype == 7 ? 4 : 9);
				} else {
					$fld->type = $intTypes[$ftype];
				}
			}
			break;
		case 10:
			$fld->type = 'float';
			break;
		case 14:
			$fld->type = 'char';
			if ($fcharlen) {
				$fld->max_length = $fcharlen;
			}
			break;
		case 23: // BOOLEAN (Firebird 3+)
			$fld->type = 'boolean';
			$fld->max_length = 1;
			break;
		case 24: // DECFLOAT(16) (Firebird 4+)
			$fld->type = 'decfloat';
			$fld->max_length = 16;
			break;
		case 25: // DECFLOAT(34) (Firebird 4+)
			$fld->type = 'decfloat';
			$fld->max_length = 34;
			break;
		case 27:
			if ($fscale !=0) {
				$fld->type = 'decimal';
				$fld->max_length = 15;
				$fld->scale = 5;
			} else {
				$fld->type = 'double';
			}
			break;
		case 28: // Firebird 4+
			$fld->type = 'time with time zone';
			break;
		case 29: // Firebird 4+
			$fld->type = 'timestamp with time zone';
			break;
		case 35:
			if ($dialect3) {
				$fld->type = 'timestamp';
			} else {
				$fld->type = 'date';
			}
			break;
		case 12:
			$fld->type = 'date';
			break;
		case 13:
			$fld->type = 'time';
			break;
		case 37:
			$fld->type = 'varchar';
			if ($fcharlen) {
				$fld->max_length = $fcharlen;
			}
			break;
		case 40:
			$fld->type = 'cstring';
			if ($fcharlen) {
				$fld->max_length = $fcharlen;
			}
			break;
		case 261:
			$fld->type = 'blob';
			$fld->max_length = -1;
			break;
		default:
			// Unknown type: report the raw type code rather than nothing
			$fld->type = 'type' . (int)$ftype;
			break;
		} // switch
	}
	//OPN STUFF end


	/**
	 * Return an array of information about a table's columns.
	 *
	 * @param string $table The name of the table to get the column info for.
	 * @param bool $normalize (Optional) Unused.
	 *
	 * @return ADOFieldObject[]|bool An array of info for each column,
	 * or false if it could not determine the info.
	 */
	public function metaColumns($table, $normalize = true)
	{

		global $ADODB_FETCH_MODE;

		$save = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_NUM;
		if ($this->fetchMode !== false) {
			$savem = $this->SetFetchMode(false);
		}

		$rs = $this->execute(sprintf($this->metaColumnsSQL,strtoupper($table)));

		if (isset($savem)) {
			$this->SetFetchMode($savem);
		}
		$ADODB_FETCH_MODE = $save;

		if ($rs === false) {
			return false;
		}

		$retarr = array();
		//OPN STUFF start
		$dialect3 = $this->dialect == 3;
		//OPN STUFF end
		while (!$rs->EOF) { //print_r($rs->fields);
			$fld = new ADOFieldObject();
			$fld->name = trim($rs->fields[0]);
			//OPN STUFF start
				//print_r($rs->fields);
			$this->_ConvertFieldType(
				$fld, $rs->fields[7], $rs->fields[3], $rs->fields[4], $rs->fields[5], $rs->fields[6], $dialect3,
				isset($rs->fields[8]) ? $rs->fields[8] : null);
			if (isset($rs->fields[1]) && $rs->fields[1]) {
				$fld->not_null = true;
			}
			if (isset($rs->fields[2])) {

				$fld->has_default = true;
				$d = substr($rs->fields[2],strlen('default '));
				switch ($fld->type) {
					case 'smallint':
					case 'integer':
					case 'bigint':
						$fld->default_value = (int)$d;
						break;
					case 'boolean':
						$fld->default_value = strtoupper(trim($d)) === 'TRUE';
						break;
					case 'char':
					case 'blob':
					case 'text':
					case 'varchar':
						$fld->default_value = (string)substr($d, 1, strlen($d) - 2);
						break;
					case 'double':
					case 'float':
						$fld->default_value = (float)$d;
						break;
					default:
						$fld->default_value = $d;
						break;
				}
		//	case 35:$tt = 'TIMESTAMP'; break;
			}
			if ((isset($rs->fields[5])) && ($fld->type == 'blob')) {
				$fld->sub_type = $rs->fields[5];
			} else {
				$fld->sub_type = null;
			}
			//OPN STUFF end
			if ($ADODB_FETCH_MODE == ADODB_FETCH_NUM) $retarr[] = $fld;
			else $retarr[strtoupper($fld->name)] = $fld;

			$rs->MoveNext();
		}
		$rs->Close();
		if ( empty($retarr))
			return false;
		else return $retarr;
	}

	/**
	 * Retrieves a list of tables based on given criteria
	 *
	 * @param string|bool $ttype (Optional) Table type = 'TABLE', 'VIEW' or false=both (default)
	 * @param string|bool $showSchema (Optional) schema name, false = current schema (default)
	 * @param string|bool $mask (Optional) filters the table by name
	 *
	 * @return array list of tables
	 */
	public function metaTables($ttype = false, $showSchema = false, $mask = false)
	{
		$save = $this->metaTablesSQL;
		$where = array();

		// Firebird has no schemas; a truthy $showSchema includes system relations
		if (!$showSchema) {
			$where[] = "COALESCE(rdb\$system_flag, 0) = 0";
		}

		if ($mask) {
			$where[] = "LOWER(rdb\$relation_name) LIKE " . $this->qstr(strtolower($mask));
		}

		if ($where) {
			$this->metaTablesSQL .= ' WHERE ' . implode(' AND ', $where);
		}
		$this->metaTablesSQL .= ' ORDER BY 1';

		$ret = ADOConnection::metaTables($ttype, $showSchema);

		$this->metaTablesSQL = $save;
		return $ret;
	}

	/**
	 * Encodes a blob, then assigns an id ready to be used
	 *
	 * @param string $blob The blob to be encoded
	 *
	 * @return bool success
	 */
	public function blobEncode( $blob )
	{
		$trans = $this->_currentTransaction();
		if (!$trans) {
			return false;
		}
		$blobid = fbird_blob_create($trans);
		fbird_blob_add( $blobid, $blob );
		return fbird_blob_close( $blobid );
	}

	/**
	 * Manually decode a blob
	 *
	 * since we auto-decode all blob's since 2.42,
	 * BlobDecode should not do any transforms
	 *
	 * @param string $blob
	 *
	 * @return string the same blob
	 */
	public function blobDecode($blob)
	{
		return $blob;
	}

	/**
	 * Auto function called on read of blob to decode
	 *
	 * @param string        $blob  Value to decode
	 * @param resource|null $trans Transaction the blob id was fetched in
	 *                             (default: the current transaction)
	 *
	 * @return string Decoded blob
	 */
	public function _blobDecode($blob, $trans = null)
	{
		if ($blob === null) {
			return '';
		}

		if (!is_resource($trans)) {
			$trans = $this->_currentTransaction();
			if (!$trans) {
				return '';
			}
		}

		$blob_data = @fbird_blob_info($trans, $blob);
		$blobId = @fbird_blob_open($trans, $blob);
		if (!$blob_data || !$blobId) {
			$this->_handleError();
			return '';
		}

		if ($blob_data[0] > $this->maxblobsize) {
			$realBlob = fbird_blob_get($blobId, $this->maxblobsize);
			while ($string = fbird_blob_get($blobId, 8192)) {
				$realBlob .= $string;
			}
		} else {
			$realBlob = fbird_blob_get($blobId, $blob_data[0]);
		}

		fbird_blob_close($blobId);
		return $realBlob;
	}

	/**
	 * Insert blob data into a database column directly
	 * from file
	 *
	 * @param string $table table to insert
	 * @param string $column column to insert
	 * @param string $path  physical file name
	 * @param string $where string to find unique record
	 * @param string $blobtype BLOB or CLOB
	 *
	 * @return bool success
	 */
	public function updateBlobFile($table,$column,$path,$where,$blobtype='BLOB')
	{
		$fd = fopen($path,'rb');
		if ($fd === false)
			return false;

		$trans = $this->_currentTransaction();
		if (!$trans) {
			return false;
		}
		$blob_id = fbird_blob_create($trans);

		/* fill with data */

		while ($val = fread($fd,32768)){
			fbird_blob_add($blob_id, $val);
		}

		/* close and get $blob_id_str for inserting into table */
		$blob_id_str = fbird_blob_close($blob_id);

		fclose($fd);
		return $this->Execute("UPDATE $table SET $column=(?) WHERE $where",array($blob_id_str)) != false;
	}

	/**
	 * Insert blob data into a database column
	 *
	 * @param string $table table to insert
	 * @param string $column column to insert
	 * @param string $val    value to insert
	 * @param string $where string to find unique record
	 * @param string $blobtype BLOB or CLOB
	 *
	 * @return bool success
	 */
	public function updateBlob($table,$column,$val,$where,$blobtype='BLOB')
	{
		$trans = $this->_currentTransaction();
		if (!$trans) {
			return false;
		}
		$blob_id = fbird_blob_create($trans);

		// fbird_blob_add($blob_id, $val);

		// replacement that solves the problem by which only the first modulus 64K /
		// of $val are stored at the blob field ////////////////////////////////////
		// Thx Abel Berenstein  aberenstein#afip.gov.ar
		$len = strlen($val);
		$chunk_size = 32768;
		$tail_size = $len % $chunk_size;
		$n_chunks = ($len - $tail_size) / $chunk_size;

		for ($n = 0; $n < $n_chunks; $n++) {
			$start = $n * $chunk_size;
			$data = substr($val, $start, $chunk_size);
			fbird_blob_add($blob_id, $data);
		}

		if ($tail_size) {
			$start = $n_chunks * $chunk_size;
			$data = substr($val, $start, $tail_size);
			fbird_blob_add($blob_id, $data);
		}
		// end replacement /////////////////////////////////////////////////////////

		$blob_id_str = fbird_blob_close($blob_id);

		return $this->execute("UPDATE $table SET $column=(?) WHERE $where",array($blob_id_str)) != false;

	}


	/**
	 * Returns a portably-formatted date string from a timestamp database column.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:sqldate
	 *
	 * Firebird does not support an AM/PM format, so the A indicator always shows AM
	 *
	 * @param string $fmt The date format to use.
	 * @param string|bool $col (Optional) The table column to date format, or if false, use NOW().
	 *
	 * @return string The SQL DATE_FORMAT() string, or empty if the provided date format was empty.
	 */
	public function sqlDate($fmt, $col=false)
	{
		if (!$col)
			$col = 'CURRENT_TIMESTAMP';

		$s = '';

		$len = strlen($fmt);
		for ($i=0; $i < $len; $i++) {
			if ($s) $s .= '||';
			$ch = $fmt[$i];
			$choice = strtoupper($ch);
			switch($choice) {
			case 'Y':
				$s .= "EXTRACT(YEAR FROM $col)";
				break;
			case 'M':
				$s .= "RIGHT('0' || TRIM(EXTRACT(MONTH FROM $col)),2)";
				break;
			case 'W':
				// The more accurate way of doing this is with a stored procedure
				// See http://wiki.firebirdsql.org/wiki/index.php?page=DATE+Handling+Functions for details
				$s .= "((EXTRACT(YEARDAY FROM $col) - EXTRACT(WEEKDAY FROM $col - 1) + 7) / 7)";
				break;
			case 'Q':
				$s .= "CAST(((EXTRACT(MONTH FROM $col)+2) / 3) AS INTEGER)";
				break;
			case 'D':
				$s .= "RIGHT('0' || TRIM(EXTRACT(DAY FROM $col)),2)";
				break;
			case 'H':
				$s .= "RIGHT('0' || TRIM(EXTRACT(HOUR FROM $col)),2)";
				break;
			case 'I':
				$s .= "RIGHT('0' || TRIM(EXTRACT(MINUTE FROM $col)),2)";
				break;
			case 'S':
				// EXTRACT(SECOND) returns a decimal with fractions (e.g. 9.0000),
				// FLOOR() gives the integer part (CAST AS INTEGER would round)
				$s .= "RIGHT('0' || TRIM(FLOOR(EXTRACT(SECOND FROM $col))),2)";
				break;
			case 'A':
				$s .= $this->qstr('AM');
				break;
			default:
				if ($ch == '\\') {
					$i++;
					$ch = substr($fmt,$i,1);
				}
				$s .= $this->qstr($ch);
				break;
			}
		}
		return $s;
	}

	/**
	 * Creates a portable date offset field, for use in SQL statements.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:offsetdate
	 *
	 * @param float $dayFraction A day in floating point
	 * @param string|bool $date (Optional) The date to offset. If false, uses CURDATE()
	 *
	 * @return string
	 */
	public function offsetDate($dayFraction, $date=false)
	{
		if (!$date)
			$date = $this->sysTimeStamp;

		$fraction = $dayFraction * 24 * 3600;
		return sprintf("DATEADD (second, %s, %s)  FROM RDB\$DATABASE",$fraction,$date);
	}


	// Note that Interbase 6.5 uses this ROWS instead - don't you love forking wars!
	// 		SELECT col1, col2 FROM table ROWS 5 -- get 5 rows
	//		SELECT col1, col2 FROM TABLE ORDER BY col1 ROWS 3 TO 7 -- first 5 skip 2
	/**
	 * Executes a provided SQL statement and returns a handle to the result, with the ability to supply a starting
	 * offset and record count.
	 *
	 * @link https://adodb.org/dokuwiki/doku.php?id=v5:reference:connection:selectlimit
	 *
	 * @param string $sql The SQL to execute.
	 * @param int $nrows (Optional) The limit for the number of records you want returned. By default, all results.
	 * @param int $offset (Optional) The offset to use when selecting the results. By default, no offset.
	 * @param array|bool $inputarr (Optional) Any parameter values required by the SQL statement, or false if none.
	 * @param int $secs2cache (Optional) If greater than 0, perform a cached execute. By default, normal execution.
	 *
	 * @return ADORecordSet|false The query results, or false if the query failed to execute.
	 */
	public function selectLimit($sql,$nrows=-1,$offset=-1,$inputarr=false, $secs2cache=0)
	{
		$nrows = (int) $nrows;
		$offset = (int) $offset;
		$str = 'SELECT ';
		if ($nrows >= 0) $str .= "FIRST $nrows ";
		$str .=($offset>=0) ? "SKIP $offset " : '';

		$sql = preg_replace('/^[ \t]*select/i',$str,$sql);
		if ($secs2cache)
			$rs = $this->cacheExecute($secs2cache,$sql,$inputarr);
		else
			$rs = $this->execute($sql,$inputarr);

		return $rs;
	}

	/**
	 * Returns SQL to obtain the length of data in a column, including
	 * CHAR fields
	 *
	 * @param string $fieldName The field length to measure
 	 * 
	 * @return string
	 */
	public function length(string $fieldName): string
	{
		return sprintf('CHAR_LEN(TRIM(%s))', $fieldName);
	}

}

/**
 * Class ADORecordset_firebird
 */
class ADORecordset_firebird extends ADORecordSet
{
	var $databaseType = "firebird";
	var $bind = false;

	/**
	 * @var ADOFieldObject[] Holds a cached version of the metadata
	 */
	private $fieldObjects = false;

	/**
	 * @var bool Flag to indicate if the result has a blob
	 */
	private $fieldObjectsHaveBlob = false;

	/**
	 * @var resource|false Transaction this result set was opened in
	 */
	private $trans = false;

	/**
	 * @var int Resource id of the autocommit transaction this result set was
	 *          opened on, 0 if it was opened in an explicit transaction
	 */
	private $autoTransId = 0;

	/**
	 * @var bool Whether the statement modifies data, so that the autocommit
	 *           transaction must be committed when the result set is closed
	 */
	private $commitOnClose = false;

	/**
	 * @var bool Whether fetching a row failed (as opposed to reaching the end
	 *           of the result set)
	 */
	private $fetchFailed = false;


	/**
	 * Returns: an object containing field information.
	 *
	 * Get column information in the Recordset object. fetchField()
	 * can be used in order to obtain information about fields in a
	 * certain query result. If the field offset isn't specified,
	 * the next field that wasn't yet retrieved by fetchField()
	 * is retrieved.
	 *
	 * $param int $fieldOffset (optional default=-1 for all
	 * @return mixed an ADOFieldObject, or array of objects
	 */
	private function _fetchField($fieldOffset = -1)
	{
		if ($this->fieldObjectsRetrieved) {
			if ($this->fieldObjects) {
				// Already got the information
				if ($fieldOffset == -1) {
					return $this->fieldObjects;
				} else {
					return $this->fieldObjects[$fieldOffset];
				}
			} else {
				// No metadata available
				return false;
			}
		}

		// Populate the field objects cache
		$this->fieldObjectsRetrieved = true;
		$this->fieldObjectsHaveBlob = false;
		$this->_numOfFields = fbird_num_fields($this->_queryID);
		for ($fieldIndex = 0; $fieldIndex < $this->_numOfFields; $fieldIndex++) {
			$fld = new ADOFieldObject;
			$ibf = fbird_field_info($this->_queryID, $fieldIndex);

			$name = empty($ibf['alias']) ? $ibf['name'] : $ibf['alias'];

			switch (ADODB_ASSOC_CASE) {
				case ADODB_ASSOC_CASE_UPPER:
					$fld->name = strtoupper($name);
					break;
				case ADODB_ASSOC_CASE_LOWER:
					$fld->name = strtolower($name);
					break;
				case ADODB_ASSOC_CASE_NATIVE:
				default:
					$fld->name = $name;
					break;
			}

			$fld->type = $ibf['type'];
			$fld->max_length = $ibf['length'];

			// This needs to be populated from the metadata
			$fld->not_null = false;
			$fld->has_default = false;
			$fld->default_value = 'null';

			$this->fieldObjects[$fieldIndex] = $fld;
			$this->fieldObjectsIndex[$fld->name] = $fieldIndex;

			if ($fld->type == 'BLOB') {
				$this->fieldObjectsHaveBlob = true;
			}
		}

		if ($fieldOffset == -1) {
			return $this->fieldObjects;
		}

		return $this->fieldObjects[$fieldOffset];
	}

	/**
	 * Fetchfield copies the oracle method, it loads the field information
	 * into the _fieldobjs array once, to save multiple calls to the
	 * fbird_ function
	 *
	 * @param int $fieldOffset (optional)
	 *
	 * @return adoFieldObject|false
	 */
	public function fetchField($fieldOffset = -1)
	{
		if ($fieldOffset == -1) {
			return $this->fieldObjects;
		}

		return $this->fieldObjects[$fieldOffset];
	}

	function _initrs()
	{
		$this->_numOfRows = -1;

		if (is_resource($this->_queryID) && $this->connection instanceof ADODB_firebird) {
			list($this->trans, $autocommit, $this->commitOnClose) = $this->connection->_lastResultInfo();
			$this->autoTransId = $autocommit && is_resource($this->trans) ? (int)$this->trans : 0;
		}

		/*
		* Retrieve all of the column information first. We copy
		* this method from oracle
		*/
		$this->_fetchField();

	}

	function _seek($row)
	{
		return false;
	}

	public function _fetch()
	{
		// Case conversion function for use in Closure defined below
		$localFnCaseConv = null;

		if (!is_resource($this->_queryID)) {
			// Result set released, e.g. connection closed
			$this->fields = false;
			return false;
		}

		if ($this->fetchMode & ADODB_FETCH_ASSOC) {
			// Handle either associative or fetch both
			$localNumeric = false;

			$f = @fbird_fetch_assoc($this->_queryID);
			if (is_array($f)) {
				// Optimally do the case_upper or case_lower
				if (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_LOWER) {
					$f = array_change_key_case($f, CASE_LOWER);
					$localFnCaseConv = 'strtolower';
				} elseif (ADODB_ASSOC_CASE == ADODB_ASSOC_CASE_UPPER) {
					$f = array_change_key_case($f, CASE_UPPER);
					$localFnCaseConv = 'strtoupper';
				}
			}
		} else {
			// Numeric fetch mode
			$localNumeric = true;
			$f = @fbird_fetch_row($this->_queryID);
		}

		if ($f === false) {
			$this->fields = false;
			// The extension resets its error state before fetching, so an
			// error now means the fetch failed rather than reached the end
			// (e.g. an exception raised by an EXECUTE BLOCK after a SUSPEND).
			if (!$this->fetchFailed && (fbird_errmsg() !== false || fbird_errcode() !== false)) {
				$this->fetchFailed = true;
				if ($this->connection instanceof ADODB_firebird) {
					$this->connection->_fetchFailed($this->sql, $this->autoTransId, $this->commitOnClose);
				}
			}
			return false;
		}

		// OPN stuff start - optimized
		// fix missing nulls and decode blobs automatically
		global $ADODB_ANSI_PADDING_OFF;
		$rtrim = !empty($ADODB_ANSI_PADDING_OFF);

		// For optimal performance, only process if there is a possibility of something to do
		if ($this->fieldObjectsHaveBlob || $rtrim) {
			$localFieldObjects = $this->fieldObjects;
			$localFieldObjectIndex = $this->fieldObjectsIndex;
			/** @var ADODB_firebird $localConnection */
			$localConnection = &$this->connection;

			/**
			 * Closure for an efficient method of iterating over the elements.
			 * @param mixed      $value
			 * @param string|int $key
			 * @return void
			 */
			$localTrans = $this->trans;
			$rowTransform = function ($value, $key) use (
				&$f,
				$rtrim,
				$localFieldObjects,
				$localConnection,
				$localTrans,
				$localNumeric,
				$localFnCaseConv,
				$localFieldObjectIndex
			) {
				if ($localNumeric) {
					$localKey = $key;
				} else {
					// Cross-reference the associative key back to numeric
					// with appropriate case conversion
					$index = $localFnCaseConv ? $localFnCaseConv($key) : $key;
					$localKey = $localFieldObjectIndex[$index];
				}

				// As we iterate the elements check for blobs and padding
				if ($localFieldObjects[$localKey]->type == 'BLOB') {
					$f[$key] = $localConnection->_BlobDecode($value, $localTrans);
				} else {
					if ($rtrim && is_string($value)) {
						$f[$key] = rtrim($value);
					}
				}

			};

			// Walk the array, applying the above closure
			array_walk($f, $rowTransform);
		}

		if (!$localNumeric && $this->fetchMode & ADODB_FETCH_NUM) {
			// Creates a fetch both
			$fNum = array_values($f);
			$f = array_merge($f, $fNum);
		}

		$this->fields = $f;

		return true;
	}

	/**
	 * Get the value of a field in the current row by column name.
	 * Will not work if ADODB_FETCH_MODE is set to ADODB_FETCH_NUM.
	 *
	 * @param string $colname is the field to access
	 *
	 * @return mixed the value of $colname column
	 */
	public function fields($colname)
	{
		if ($this->fetchMode & ADODB_FETCH_ASSOC) {
			return $this->fields[$colname];
		}

		if (!$this->bind) {
			// fieldsObjectIndex populated by the recordset load
			$this->bind = array_change_key_case($this->fieldObjectsIndex, CASE_UPPER);
		}

		return $this->fields[$this->bind[strtoupper($colname)]];
	}


	/**
	 * Releases the result set.
	 *
	 * For a data-modifying statement executed in autocommit mode this is
	 * where the work done while fetching is committed, or rolled back if
	 * fetching failed.
	 *
	 * @return bool false if fetching had failed or the final commit fails
	 */
	function _close()
	{
		// _queryID is false for metadata-only recordsets, and may be an
		// already closed resource if the connection was closed first
		$ret = true;
		if (is_resource($this->_queryID)) {
			$ret = (bool)@fbird_free_result($this->_queryID);
		}

		// Reset the state first: the release below may invoke an error
		// handler that throws
		$autoTransId = $this->autoTransId;
		$this->autoTransId = 0;
		$this->trans = false;

		if ($autoTransId && $this->connection instanceof ADODB_firebird) {
			$ret = $this->connection->_releaseResult(
				$autoTransId, $this->commitOnClose, $this->fetchFailed, $this->sql
			) && $ret;
		}

		return $ret && !$this->fetchFailed;
	}

	public function metaType($t, $len = -1, $fieldObj = false)
	{
		if (is_object($t)) {
			$fieldObj = $t;
			$t = $fieldObj->type;
			$len = $fieldObj->max_length;
		}

		$t = strtoupper($t);

		if (array_key_exists($t, $this->connection->customActualTypes)) {
			return $this->connection->customActualTypes[$t];
		}

		// Type names as reported by fbird_field_info() and the datadict;
		// numeric types with a scale are reported as NUMERIC(p,s)
		switch ($t) {
			case 'CHAR':
			case 'CSTRING':
				return 'C';

			case 'TEXT':
			case 'VARCHAR':
			case 'VARYING':
				if ($len <= $this->blobSize) {
					return 'C';
				}
				return 'X';
			case 'BLOB':
				return 'B';

			case 'DATE':
				return 'D';

			case 'TIMESTAMP':
			case 'TIMESTAMP WITH TIME ZONE':
			case 'EXTENDED TIMESTAMP WITH TIME ZONE':
			case 'TIME':
			case 'TIME WITH TIME ZONE':
			case 'EXTENDED TIME WITH TIME ZONE':
				return 'T';

			case 'BOOLEAN':
				return 'L';

			// Too large for PHP integers, returned as strings by the extension
			case 'INT128':
			case 'DECFLOAT':
			case 'DECFLOAT(16)':
			case 'DECFLOAT(34)':
				return 'N';

			case 'INT':
			case 'SHORT':
			case 'SMALLINT':
			case 'INTEGER':
			case 'BIGINT':
			case 'INT64':
				return 'I';

			// INT128, DECFLOAT, FLOAT, DOUBLE PRECISION, NUMERIC(p,s), DECIMAL...
			default:
				return ADODB_DEFAULT_METATYPE;
		}
	}

}
