<?php
/**
* Mcrypt Encryption session management plugin for the Sessions package
*
* Mcrypt is now deprecated as of PHP7.2. This plugin is provided for 
* reference only
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

class MCrypt extends \ADOdb\SessionPlugin\plugins\ADOCrypt {

	protected array $coreOptions = array(
		'cipher'=>MCRYPT_RIJNDAEL_256,
		'mode'=>MCRYPT_MODE_ECB,
		'source'=>MCRYPT_RAND
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
		
		$this->coreOptions = array_merge($this->coreOptions,$options);
	
	}

	/**
	 */
	final protected function encrypt(string $data, string $key) : string {
		
		$iv_size = mcrypt_get_iv_size($this->coreOptions['cipher'], $this->coreOptions['mode']);
		
		$iv = mcrypt_create_iv($iv_size, $this->coreOptions['source']);
		
		return mcrypt_encrypt($this->coreOptions['cipher'], 
							  $key, 
							  $data, 
							  $this->coreOptions['mode'], 
							  $iv);
	
	}

	/**
	 */
	final protected function decrypt(string $data, string $key) : string {
		$iv_size = mcrypt_get_iv_size($this->coreOptions['cipher'], $this->coreOptions['mode']);
		
		$iv = mcrypt_create_iv($iv_size, $this->coreOptions['source']);
		
		$rv = mcrypt_decrypt($this->coreOptions['cipher'],
						     $key,
							 $data, 
							 $this->coreOptions['mode'], 
							 $iv);
		
		return rtrim($rv, "\0");
	}

}
