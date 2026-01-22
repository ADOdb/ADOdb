<?php
/**
* This is the format of the logging tags object, that is encoded into the log tags
* If Plain Text logging is used
*
* This file is part of the ADOdb package.
*
* @copyright 2023-2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\LoggingPlugin;


final class ADOjsonTagFormat
{
	/*
	* The host name
	*/
	public string $host = '';

	/*
	* Whether it is CLI or CGI
	*/
	public string $source = '';

	/*
	* The ADOdb driver
	*/
	public string $driver = '';

	/*
	* The PHP version
	*/
	public string $php = '';

	/*
	* The OS Version
	*/
	public string $os  = '';

	/*
	* The current AODdb version
	*/
	public ?string $ADOdbVersion = '';

	/**
	* Constructor
	*
	* @param ADOConnection $connection
	*/
	public function __construct(?object $connection)
	{
		global $ADODB_vers;

		$this->php    		= PHP_VERSION;
		$this->os     		= PHP_OS;
		$this->ADOdbVersion = $ADODB_vers;

		$this->source = isset($_SERVER['HTTP_USER_AGENT']) ? 'cgi' : 'cli';
        $this->host    = gethostname();

		if ($connection)
		{
			$this->driver       = $connection->databaseType;
			$this->ADOdbVersion	= $connection->version();
		}
	}
}