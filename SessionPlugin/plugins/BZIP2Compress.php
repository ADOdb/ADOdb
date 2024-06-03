<?php
/**
* BZIP2 Compression session management plugin for the Sessions package
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

class BZIP2Compress extends \ADOdb\SessionPlugin\plugins\ADOCompress {

	protected array $coreOptions = array(
		'minlength'=>1,
		'blocksize'=>null,
		'worklevel'=>null,
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
		$this->integerQuality($options, 'blocksize', 1, 9);
		$this->integerQuality($options, 'worklevel', 0, 250);
		
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
	final protected function compress(string $data, string $key) : string {
		
		if (strlen($data) < $this->coreOptions['minlength']) {
			
			return $data;
		
		}

		if (!is_null($this->coreOptions['blocksize'])) {
			
			if (!is_null($this->coreOptions['worklevel'])) {
				
				return bzcompress(
						$data, 
						$this->coreOptions['blocksize'], 
						$this->coreOptions['worklevel']
						);
			
			} else {
				
				return bzcompress(
						$data, 
						$this->coreOptions['blocksize']
						);
			}
		}

		return bzcompress($data);
	}

	/**
	* Decompresses the text for the key
	*
	* @param string	$data
	* @param string	$key
	*
	* @return string The decompresses pair
	*/
	final protected function decompress(string $data, string $key) : string {
		
		return $data ? bzdecompress($data) : $data;
	
	}
}
