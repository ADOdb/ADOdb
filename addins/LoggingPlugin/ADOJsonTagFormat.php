<?php
/**
* This is the format of the logging tags object, that is encoded into the log tags
* If Plain Text logging is used
*
* This file is part of the ADOdb package.
*
* @copyright 2023 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\logger;


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

	public function __construct()
	{
		$this->php    = PHP_VERSION;
		$this->os     = PHP_OS;
		$this->source = isset($_SERVER['HTTP_USER_AGENT']) ? 'cgi' : 'cli';
        $this->host    = gethostname();
	}
}