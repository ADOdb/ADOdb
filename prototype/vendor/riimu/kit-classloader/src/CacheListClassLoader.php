<?php

namespace Riimu\Kit\ClassLoader;

/**
 * Provides a simple method of caching the list of class file locations.
 *
 * CacheListClassLoader provides a simple way to implement your own caching
 * handlers for the ClassLoader. The base idea of this cache is to call a
 * provided cache save handler when a new class location is found with the
 * whole class location cache. The saved cache location should be provided
 * in the constructor when the class loader is constructed.
 *
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2014-2017 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class CacheListClassLoader extends ClassLoader
{
    /** @var string[] List of class file locations */
    private $cache;

    /** @var callable|null Callback used for storing the cache */
    private $cacheHandler;

    /**
     * Creates a new CacheListClassLoader instance.
     *
     * The parameter should contain the paths provided to your cache save
     * handler. If no cache exists yet, an empty array should be provided
     * instead.
     *
     * @param string[] $cache The cached paths stored by your cache handler
     */
    public function __construct(array $cache)
    {
        parent::__construct();

        $this->cache = $cache;
        $this->cacheHandler = null;
    }

    /**
     * Sets the callback used to store the cache.
     *
     * Whenever a new file location for class is found, the cache handler is
     * called with an associative array containing the paths for different
     * classes. The cache handler should store the array and provide it in the
     * constructor in following requests.
     *
     * @param callable $callback Callback for storing cache
     * @return CacheListClassLoader Returns self for call chaining
     */
    public function setCacheHandler(callable $callback)
    {
        $this->cacheHandler = $callback;

        return $this;
    }

    /**
     * Loads the class by first checking if the file path is cached.
     * @param string $class Full name of the class
     * @return bool|null True if the class was loaded, false if not
     */
    public function loadClass($class)
    {
        $result = $this->loadCachedClass($class);

        if ($result === false) {
            $result = parent::loadClass($class);
        }

        if ($this->verbose) {
            return $result !== false;
        }
    }

    /**
     * Attempts loading class from the known class cache.
     * @param string $class Full name of the class
     * @return bool True if the class was loaded, false if not
     */
    private function loadCachedClass($class)
    {
        $result = false;

        if (isset($this->cache[$class])) {
            $result = include $this->cache[$class];

            if ($result === false) {
                unset($this->cache[$class]);
                $this->saveCache();
            }
        }

        return $result !== false;
    }

    /**
     * Loads the class from the given file and stores the path into cache.
     * @param string $file Full path to the file
     * @param string $class Full name of the class
     * @return bool Always returns true
     * @throws \RuntimeException If the class was not defined in the included file
     */
    protected function loadFile($file, $class)
    {
        parent::loadFile($file, $class);
        $this->cache[$class] = $file;
        $this->saveCache();

        return true;
    }

    /**
     * Saves the cache by calling the cache handler with it.
     */
    private function saveCache()
    {
        if ($this->cacheHandler !== null) {
            call_user_func($this->cacheHandler, $this->cache);
        }
    }
}
