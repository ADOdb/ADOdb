<?php
/**
* GZIP Compression session management plugin for the Sessions package
*
* This file is part of the ADOdb package.
*
* @copyright 2020 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\addins\session\plugins;
use \ADOdb\addins\session;

class GZIPCompress extends \ADOdb\addins\session\plugins\ADOCompress {

	
	protected array $coreOptions = array(
		'minlength'=>1,
		'level'=>null,
		);
	
	
	/**
	* Loads any override options
	*
	* @param array $options
	*
	* @return void
	*/
	final public function loadOptions(?array $options=null): void {
	
		if (!$options)
			return;
			
		$this->integerQuality($options, 'minlength', 1);
		$this->integerQuality($options, 'level', 1, 9);
		
		$this->coreOptions = array_merge($this->coreOptions,$options);
	
	}
	
	/**
	* Compresses the text for the key
	*
	* @param string	$data
	* @param string	$key
	*
	* @return string The encrypted pair
	*/
	protected function compress(string $data,string $key) : string {
		
		if (strlen($data) < $this->coreOptions['minlength']) {
			return $data;
		}

		if (!is_null($this->coreOptions['level'])) {
			return gzcompress($data, $this->coreOptions['level']);
		} else {
			return gzcompress($data);
		}
	}

	/**
	* Decompresses the text for the key
	*
	* @param string	$data
	* @param string	$key
	*
	* @return string The decompresses pair
	*/
	protected function decompress(string $data,string $key) : string {

		return $data ? gzuncompress($data) : $data;
	
	}

}
