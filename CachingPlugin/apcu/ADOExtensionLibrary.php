<?php
/**
* Transposes Caching Methods to apcu 
*
* This file is part of the ADOdb package.
*
* @copyright 2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\CachingPlugin\apcu;

final class ADOExtensionLibrary{

    /**
	* Writes an APCU cached data set
	*
	* @param string $recordsetKey
	* @param string $contents
	* @param int    $ttl
	*
	* @return bool
	*/
	public function set(
		string $recordsetKey, 
		string $contents, 
		int $ttl) : bool {

            return apcu_store($recordsetKey,$contents,$ttl);
    }

    /**
	* Reads an APCU cached data set
	*
	* @param string $recordsetKey
	*
	* @return string
	*/
    final public function get(string $recordsetKey) : string {

        $success = false;
        return apcu_fetch($recordsetKey,$success);
    }

	/**
	* Flushes all entries
	*
	* @return void
	*/
	final public function flush() : bool
	{
		return apcu_clear_cache();
	}

	/**
	* Flush an individual query from the apcu cache
	*
	* @param string $recordsetKey The md5 of the query
	*
	* @return void
	*/
	final public function delete(string $recordsetKey) : void 
	{	
		apcu_delete($recordsetKey);
	}

	/**
	* Returns an array of info about the cache
	*
	* @return array
	*/
	final public function info() : array
	{
		$info = apcu_cache_info();
		if (!is_array($info)) {
			return array();
		}
		return $info;
	}
}