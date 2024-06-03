<?php
/**
* Core Encryption session management plugin for the Sessions package
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\SessionPlugin\plugins;
use \ADOdb\SessionPlugin;


class ADOCrypt {
	
	protected ?object $connection = null;
	
	public function __construct($connection)
	{
		$this->connection = $connection;
	}
	
	/**
	* Returns the key for the necessary encoding
	*
	* @return string
	*/
	protected function fetchEncryptionKey() :string {}
	
	/**
	* Loads any override options
	*
	* @param array $options
	*
	* @return void
	*/
	public function loadOptions(?array $options=null): void {}
	
	/**
	* Converts inbound data to be written
	* @param string	$txt
	* @param string	$key
	*
	* @return string The encrypted pair
	*/
	final public function write(string $data, string $key) : string {
		
		return $this->encrypt($data, $key);
	
	}

	/**
	* Converts outbound data to be read
	*
	* @param string	$txt
	* @param string	$key
	*
	* @return string The encrypted pair
	*/
	final public function read(string $data, string $key) : string {
		
		return $this->decrypt($data, $key);
	
	}
	
	/**
	* Encrypts the text for the key
	*
	* @param string	$txt
	* @param string	$key
	*
	* @return string The encrypted pair
	*/
	protected function encrypt(string $txt,string $key) : string
	{
		$encrypt_key = $this->fetchEncryptionKey();
		$ctr	= 0;
		$tmp	= '';
		
		for ($i=0;$i<strlen($txt);$i++)
		{
			if ($ctr==strlen($encrypt_key)) 
				$ctr=0;
			
			$tmp.= substr($encrypt_key,$ctr,1) .
			(substr($txt,$i,1) ^ substr($encrypt_key,$ctr,1));
			
			$ctr++;
		}
		
		return base64_encode($this->keyED($tmp,$key));
	}

	/**
	* Decrypts the text for the key
	*
	* @param string	$txt
	* @param string	$key
	*
	* @return string The decrypted pair
	*/
	protected function decrypt(string $txt, string $key) : string {
		
		$txt = $this->keyED(base64_decode($txt),$key);
		$tmp = "";
		for ($i=0;$i<strlen($txt);$i++){
			$decryptionKey = substr($txt,$i,1);
			$i++;
			$tmp.= (substr($txt,$i,1) ^ $decryptionKey);
		}
		
		return $tmp;
	}
	
	/**
	* Does something
	*
	* @param string	$txt
	* @param string	$key
	*
	* @return string
	*/
	protected function keyED(string $txt,string $encrypt_key) : string
	{
		$encrypt_key = md5($encrypt_key);
		$ctr=0;
		$tmp = "";
		
		for ($i=0;$i<strlen($txt);$i++){
			
			if ($ctr==strlen($encrypt_key)) $ctr=0;
			$tmp.= substr($txt,$i,1) ^ substr($encrypt_key,$ctr,1);
			$ctr++;
		
		}
		
		return $tmp;
	}

	/**
	* Generates a random password
	*
	* @return string The password
	*/
	public function randPass() : string	{
		
		$randomPassword = "";
		for($i=0;$i<8;$i++)
		{
			$randnumber = rand(48,120);

			while (($randnumber >= 58 && $randnumber <= 64) || ($randnumber >= 91 && $randnumber <= 96))
			{
				$randnumber = rand(48,120);
			}

			$randomPassword .= chr($randnumber);
		}
		return $randomPassword;
	}

}
