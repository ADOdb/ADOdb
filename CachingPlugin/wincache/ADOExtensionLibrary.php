<?php
/**
* Transposes Caching Methods to wincache 
*
* This file is part of the ADOdb package.
*
* @copyright 2024 Mark Newnham
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace ADOdb\CachingPlugin\wincache;

final class ADOExtensionLibrary{

    /**
	* Writes a cached data set
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

            return wincache_ucache_set($recordsetKey,$contents,$ttl);
    }

    /**
	* Writes a wincache cached data set
	*
	* @param string $recordsetKey
	*
	* @return string
	*/
    public function get(string $recordsetKey) : string {

        $success = false;
        return wincache_ucache_get($recordsetKey,$success);

    }

	/**
	* Flushes a wincache cached data set
	*
	* @param string $recordsetKey
	*
	* @return string
	*/
    public function flush() : void {

        wincache_ucache_clear();

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
		wincache_ucache_delete($recordsetKey);

	}

	/**
	* Returns an array of info about the cache
	*
	* @return array
	*/
	final public function info() : array
	{

		$info = array(
			print_r(wincache_ucache_info(),true),
			print_r(wincache_ucache_meminfo(),true),
			print_r(wincache_fcache_meminfo(),true),
			print_r(wincache_fcache_fileinfo(),true)
			);
		return $info;
	}
}