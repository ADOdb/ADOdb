<?php
/**
* The main connection definitions for ADOdb sessions system
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\session;

final class ADOSessionDefinitions
{

	/*
	* Defines if sessions debugging is enabled. Not the same
	* as database driver debugging. Critical logging operations
	* ignore this flag if there is a logging method attached
	*/
	public bool $debug = false;

	/*
	* Attach an ADOdb logging object here
	*/
	public ?object $loggingObject = null;

	/*
	* Is the session connection readonly
	*/
	public bool $readOnly = false;

	/*
	* Defines the sessions table name
	*/
	public string $tableName = 'sessions2';

	/*
	* Most databases require large object handling if we are using compression
	*/
	public ?string $largeObject = 'blob';

	/*
	* What fields will be retrieved from the database on
	* read
	*/
	public string $readFields = 'sessdata';
	
	/*
	* Defines the crypto method. Default none
	*/
	const CRYPTO_NONE 	= 0;
	const CRYPTO_MD5  	= 1;
	const CRYPTO_MCRYPT = 2;
	const CRYPTO_SHA1   = 3;
	const CRYPTO_SECRET = 4;

	public int $cryptoMethod = 0;

	/*
	* Defines the compression method - Default none
	*/
	const COMPRESS_NONE = 0;
	const COMPRSS_BZIP  = 1;
	const COMPRESS_GZIP = 2;

	public int $compressionMethod = 0;

	/*
	* Serialization methods
	*/
	const SER_DEFINED 	 	   = 0;
	const SER_PHP		 	   = 1;
	const SER_PHP_BINARY	   = 2;
	const SER_PHP_SERIALIZABLE = 3;
	const SER_PHP_WDDX 		   = 4;

	public ?int $serializationMethod = 3;

	/*
	* You can activate this for MySQL or Postgres if you want,
	* but it is no longer recommended to do so
	*/
	public bool $optimizeTable = false;

	
	/**
	* Constructor
	*
	*/
	public function __construct(){}

}