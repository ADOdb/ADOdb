<?php
/**
* redis driver session configuration for the Sessions package
*
* This file is part of the ADOdb package.
*
* @copyright 2021 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\session\drivers\redis;

use \ADOdb\addins\session;

final class ADOSession extends \ADOdb\addins\session\ADOSession {


	/*
	* Does the driver need special largeObject handling
	*/
	protected string $largeObject = 'blob';

	/*
	* Session management has a logging object of its own
	*/
	protected ?object $loggingObject = null;


	protected ?string $sessionHasExpired = null;

	/*
	* The database connection. Must be passed to class
	*/
	protected ?object $connection = null;

	/*
	* The table in use
	*/
	protected ?string $tableName = null;

	protected ?string $selfName  = null;
	
	/*
	* Configuration for the session
	*/
	protected ?object $sessionDefinition = null;

	


	/**
	* Manual routine to regenerate the session id
	*
	* @return bool success
	*/
	final public function adodb_session_regenerate_id() : bool {

		$old_id = session_id();
		
		session_regenerate_id();

		$new_id = session_id();
		
		
		$ok = false;
		$tries = 0;
		while (!$ok && $tries < $this->collisionRetries)
		{
			$this->connection->rename($old_id,$new_id);
			$tries++;

		}

		if ($ok && $this->debug)
		{
			$message = sprintf('Regeneration of key %s succeeded',$key);
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		else if (!$ok)
		{
			$message = sprintf('Regeneration of key %s failed',$key);
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
		}

		return $ok ? true : false;
	}


	/**
	* Slurp in the session variables and return the serialized string
	* cannot type hint impleted class
	*
	* @param	string 	$key
	*
	* @return string
	*/
	final public function read($key) {


		if ($this->debug)
		{
			$message = 'Reading Session Data For key ' . $key;
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}

		$filter	= $this->filter();

/*
		* Get key
		$redis->rename('x', 'y');
		$redis->set('x', '42');
$now = time(NULL); // current timestamp
$redis->expireAt('x', $now + 3)
		$redis->setEx('key', 3600, 'value'); // sets key → value, with 1h TTL.
		// Will set the key, if it doesn't exist, with a ttl of 10 seconds
$redis->set('key', 'value', ['nx', 'ex'=>10]);

// Will set a key, if it does exist, with a ttl of 1000 miliseconds
$redis->set('key', 'value', ['xx', 'px'=>1000]);
		*/
		
		$record = $this->connection->get($key);

		/*
		$this->connection->param(false);
		$p0 = $this->connection->param('p0');
		$bind = array('p0'=>$key);

		$sql = sprintf("SELECT %s FROM %s WHERE sesskey = %s %s AND expiry >= %s",
					$this->sessionDefinition->readFields,
					$this->tableName,
					$this->binaryOption,
					$p0,
					$this->connection->sysTimeStamp);

		$rs = $this->connection->execute($sql, $bind);
		*/
		if (!$record) {
			if ($this->debug)
			{
				$message = 'No session data found for key ' . $key;
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
			$v = '';
		} else {
			if ($this->debug)
			{
				$message = 'Unpacking session data for for key ' . $key;
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
				$v = reset($rs->fields);
				$filter = array_reverse($filter);
				foreach ($filter as $f) {
					if (is_object($f)) {
						$v = $f->read($v, $this->_sessionKey());
					}
				}
				$v = rawurldecode($v);
			}

			$this->recordCRC = strlen($v) . crc32($v);
			return $v;
		}

		if ($this->debug)
		{
			$message = 'No session data found for key ' . $key;
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		
		return '';
	}

	/*!
	* Write the serialized data to a database.
	*
	* If the data has not been modified since the last read(), we do not write.
	*/
	public function write($key, $oval)
	{

		if ($this->sessionDefinition->readOnly)
			return false;

		$lifetime		= $this->lifetime();

		$sysTimeStamp = $this->connection->sysTimeStamp;

		$expiry = $this->connection->offsetDate($lifetime/(24*3600),$sysTimeStamp);

		$binary = $this->binaryOption;
		$crc	= $this->recordCRC;
		$table  = $this->tableName;

		$expire_notify	= $this->expireNotify();
		$filter         = $this->filter();

		$clob			= $this->largeObject;
		/*
		* We only update expiry date if there is no change to the session text
		*
		*/
		if ($crc !== '00' && $crc !== false && $crc == (strlen($oval) . crc32($oval)))
		{
			if ($this->debug) {
				$message = 'Only updating date - crc32 not changed';
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}

			$expirevar = '';
			if ($expire_notify) {
				$var = reset($expire_notify);
				global $$var;
				if (isset($$var)) {
					$expirevar = $$var;
				}
			}
			
			
			/*
			* The key for the redis record is the session id
			*
			$redis->setEx('key', 3600, 'value'); // sets key → value, with 1h TTL.
			key would be our session_id TTL is $expiry
			key sesskey VARCHAR( 64 ) NOT NULL DEFAULT '',
	        value 
			expiry DATETIME NOT NULL ,
	expireref VARCHAR( 250 ) DEFAULT '',
	created DATETIME NOT NULL ,
	modified DATETIME NOT NULL ,
	sessdata VARBINARY(MAX),
	PRIMARY KEY ( sesskey ) ,
	INDEX sess2_expiry( expiry ),
	INDEX sess2_expireref( expireref )
			*/
			
			$this->connection->param(false);
			$p0 = $this->connection->param('p0');
			$p1 = $this->connection->param('p1');

			$bind = array('p0'=>$expirevar,'p1'=>$key);

			$sql = "UPDATE $table
					SET expiry = $expiry ,expireref=$p0 modified = $sysTimeStamp
					 WHERE $binary sesskey = $p1
					 AND expiry >= $sysTimeStamp";

			$rs = $this->connection->execute($sql,$bind);
			return true;
		}

		if ($this->debug)
		{
			$message = 'Rewriting Session Data For key ' . $key;
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}
		
		$val = rawurlencode($oval);
		
		foreach ($filter as $f) {
			if (is_object($f)) {
				$val = $f->write($val, $this->_sessionKey());
			}
		}

		$expireref = 0;
		if ($expire_notify) 
		{
			$var = reset($expire_notify);
			global $$var;
			if (isset($$var)) {
				$expireref = $$var;
			}
		}
		
		if (!$clob) 
		{

			/*
			* no special lob handling for example in MySQL
			*/
			$this->connection->param(false);
			$p0 = $this->connection->param('p0');
			$bind = array('p0'=>$key);

			$sql = "SELECT COUNT(*) AS cnt
					  FROM $table
					 WHERE $binary sesskey = $p0";

			$rs = $this->connection->execute($sql,$bind);
			if ($rs)
				$rs->Close();

			$this->connection->param(false);
			$p0 = $this->connection->param('p0');
			$p1 = $this->connection->param('p1');
			$p2 = $this->connection->param('p2');

			$bind = array('p0'=>$val,
						  'p1'=>$expireref,
						  'p2'=>$key);

			if ($rs && reset($rs->fields) > 0)
			{
				/*
				* Set the new expiry time on the record, with new data.
				*/
				$sql = "UPDATE $table SET expiry=$expiry, sessdata=$p0, expireref=$p1,modified=$sysTimeStamp WHERE sesskey = $p2";

			} else {

				/*
				* Create session record with the specified key and lifetime/
				*/
				$sql = "INSERT INTO $table (expiry, sessdata, expireref, sesskey, created, modified) VALUES ($expiry, $p0,$p1,$p2, $sysTimeStamp, $sysTimeStamp)";
			}

			$rs = $this->connection->Execute($sql,$bind);

		
			if ($this->debug)
				$this->loggingObject->log($this->loggingObject::DEBUG,'Calling BLOB update method');

			
			//$qkey = $this->connection->qstr($key);
			$params = array('sesskey'=>$key);
			$rs2 = $this->connection->updateBlob($table, 'sessdata', $val, $params, strtoupper($clob));

			if ($this->debug)
				$this->loggingObject->log($this->loggingObject::DEBUG,'Committing BLOB');

			$this->connection->completeTrans();

		}

		if (!$rs) {
			$message = 'Session Replace: ' . $this->connection->errorMsg();
			$this->loggingObject->log($this->loggingObject::CRITICAL,$message);
			return false;
		}
		return $rs ? true : false;
	}

	/*
	* Session destruction - Part of sessionHandlerInterface
	*
	* @param string $key
	*
	* @return bool
	*/
	final public function destroy($key)
	{

		if ($this->debug)
		{
			$message = 'Destroying Session For key ' . $key;
			$this->loggingObject->log($this->loggingObject::DEBUG,$message);
		}

		$expire_notify	= $this->expireNotify();

		$qkey = $this->connection->quote($key);
		$binary = $this->binaryOption;
		$table  = $this->tableName;

		if ($expire_notify) {
			reset($expire_notify);

			$fn = next($expire_notify);

			$this->connection->setFetchMode($this->connection::ADODB_FETCH_NUM);
			$this->connection->param(false);
			$p1 = $this->connection->param('p1');
			$bind = array('p1'=>$key);

			$sql = "SELECT expireref, sesskey
					  FROM $table
					 WHERE $binary sesskey=$p1";

			$rs = $this->connection->execute($sql,$bind);

			$this->connection->setFetchMode($this->connection->coreFetchMode);
			if (!$rs) {
				return false;
			}
			if (!$rs->EOF) {
				$ref = $rs->fields[0];
				$key = $rs->fields[1];
				$fn($ref, $key);
			}
			$rs->close();
		}

		$sql = "DELETE FROM $table
				 WHERE $binary sesskey=$p1";

		$rs = $this->connection->execute($sql,$bind);
		if ($rs) {
			$rs->close();
			if ($this->debug){
				$message = 'SESSION: Successfully destroyed and cleaned up';
				$this->loggingObject->log($this->loggingObject::DEBUG,$message);
			}
		}

		return $rs ? true : false;
	}

	/*
	* Garbage Collection - Part of sessionHandlerInterface
	*
	* @param int $maxlifetime
	*
	* @return bool
	*/
	final public function gc($maxlifetime)
	{

		$expire_notify	= $this->expireNotify();
		$optimize		= $this->optimizeTable;

		if ($this->debug) {
			$COMMITNUM = 2;
		} else {
			$COMMITNUM = 20;
		}

		$sysTimeStamp = $this->connection->sysTimeStamp;

		$time = $this->connection->offsetDate(-$maxlifetime/24/3600,$sysTimeStamp);

		$binaryOption = $this->binaryOption;

		$table = $this->tableName;

		if ($expire_notify) {
			reset($expire_notify);
			$fn = next($expire_notify);
		} else {
			$fn = false;
		}

		$this->connection->SetFetchMode($this->connection::ADODB_FETCH_NUM);
		$sql = "SELECT expireref, sesskey
			      FROM $table
				 WHERE expiry < $time
	               ORDER BY 2"; # add order by to prevent deadlock
		$rs = $this->connection->selectLimit($sql,1000);

		$this->connection->setFetchMode($this->connection->coreFetchMode);

		if ($rs) {
			$this->connection->beginTrans();

			$keys = array();
			$ccnt = 0;

			while (!$rs->EOF) {

				$ref = $rs->fields[0];
				$key = $rs->fields[1];
				if ($fn)
					$fn($ref, $key);

				$this->connection->param(false);
				$p1 = $this->connection->param('p1');
				$bind = array($p1=>$key);

				$sql = "DELETE FROM $table
						 WHERE sesskey=$p1";

				$del = $this->connection->execute($sql,$bind);

				$rs->MoveNext();
				$ccnt += 1;

				if ($ccnt % $COMMITNUM == 0) {
					if ($this->debug) {
						$message = 'Garbage Collecton complete';
						$this->loggingObject->log($this->loggingObject::DEBUG,$message);
					}
					$this->connection->commitTrans();
					$this->connection->beginTrans();
				}
			}
			$rs->close();

			$this->connection->commitTrans();
		}

		
		return true;
	}

}
