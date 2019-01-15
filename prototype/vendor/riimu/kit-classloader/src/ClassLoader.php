<?php

namespace Riimu\Kit\ClassLoader;

/**
 * Class loader that supports both PSR-0 and PSR-4 autoloading standards.
 *
 * The purpose autoloading classes is to load the class files only as they are
 * needed. This reduces the overall page overhead, as every file does not need
 * to be requested on every request. It also makes managing class loading much
 * simpler.
 *
 * The standard practice in autoloading is to place classes in files that are
 * named according to the class names and placed in a directory hierarchy
 * according to their namespace. ClassLoader supports two such standard
 * autoloading practices: PSR-0 and PSR-4.
 *
 * Class paths can be provided as base paths, which are appended with the full
 * class name (as per PSR-0), or as prefix paths that can replace part of the
 * namespace with a specific directory (as per PSR-4). Depending on which kind
 * of paths are added, the underscores may or may not be treated as namespace
 * separators.
 *
 * @see http://www.php-fig.org/psr/psr-0/
 * @see http://www.php-fig.org/psr/psr-4/
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2014-2017 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class ClassLoader
{
    /** @var array List of PSR-4 compatible paths by namespace */
    private $prefixPaths;

    /** @var array List of PSR-0 compatible paths by namespace */
    private $basePaths;

    /** @var bool Whether to look for classes in include_path or not */
    private $useIncludePath;

    /** @var callable The autoload method used to load classes */
    private $loader;

    /** @var \Riimu\Kit\ClassLoader\ClassFinder Finder used to find class files */
    private $finder;

    /** @var bool Whether loadClass should return values and throw exceptions or not */
    protected $verbose;

    /**
     * Creates a new ClassLoader instance.
     */
    public function __construct()
    {
        $this->prefixPaths = [];
        $this->basePaths = [];
        $this->useIncludePath = false;
        $this->verbose = true;
        $this->loader = [$this, 'loadClass'];
        $this->finder = new ClassFinder();
    }

    /**
     * Registers this instance as a class autoloader.
     * @return bool True if the registration was successful, false if not
     */
    public function register()
    {
        return spl_autoload_register($this->loader);
    }

    /**
     * Unregisters this instance as a class autoloader.
     * @return bool True if the unregistration was successful, false if not
     */
    public function unregister()
    {
        return spl_autoload_unregister($this->loader);
    }

    /**
     * Tells if this instance is currently registered as a class autoloader.
     * @return bool True if registered, false if not
     */
    public function isRegistered()
    {
        return in_array($this->loader, spl_autoload_functions(), true);
    }

    /**
     * Tells whether to use include_path as part of base paths.
     *
     * When enabled, the directory paths in include_path are treated as base
     * paths where to look for classes. This option defaults to false for PSR-4
     * compliance.
     *
     * @param bool $enabled True to use include_path, false to not use
     * @return ClassLoader Returns self for call chaining
     */
    public function useIncludePath($enabled = true)
    {
        $this->useIncludePath = (bool) $enabled;

        return $this;
    }

    /**
     * Sets whether to return values and throw exceptions from loadClass.
     *
     * PSR-4 requires that autoloaders do not return values and do not throw
     * exceptions from the autoloader. By default, the verbose mode is set to
     * false for PSR-4 compliance.
     *
     * @param bool $enabled True to return values and exceptions, false to not
     * @return ClassLoader Returns self for call chaining
     */
    public function setVerbose($enabled)
    {
        $this->verbose = (bool) $enabled;

        return $this;
    }

    /**
     * Sets list of dot included file extensions to use for finding files.
     *
     * If no list of extensions is provided, the extension array defaults to
     * just '.php'.
     *
     * @param string[] $extensions Array of dot included file extensions to use
     * @return ClassLoader Returns self for call chaining
     */
    public function setFileExtensions(array $extensions)
    {
        $this->finder->setFileExtensions($extensions);

        return $this;
    }

    /**
     * Adds a PSR-0 compliant base path for searching classes.
     *
     * In PSR-0, the class namespace structure directly reflects the location
     * in the directory tree. A base path indicates the base directory where to
     * search for classes. For example, if the class 'Foo\Bar', is defined in
     * '/usr/lib/Foo/Bar.php', you would simply need to add the directory
     * '/usr/lib' by calling:
     *
     * <code>addBasePath('/usr/lib')</code>
     *
     * Additionally, you may specify that the base path applies only to a
     * specific namespace. You can do this by adding the namespace as the second
     * parameter. For example, if you would like the path in the previous
     * example to only apply to the namespace 'Foo', you could do so by calling:
     *
     * <code>addBasePath('/usr/lib/', 'Foo')</code>
     *
     * Note that as per PSR-0, the underscores in the class name are treated
     * as namespace separators. Therefore 'Foo_Bar_Baz', would need to reside
     * in 'Foo/Bar/Baz.php'. Regardless of whether the namespace is indicated
     * by namespace separators or underscores, the namespace parameter must be
     * defined using namespace separators, e.g 'Foo\Bar'.
     *
     * In addition to providing a single path as a string, you may also provide
     * an array of paths. It is also possible to provide an associative array
     * where the keys indicate the namespaces. Each value in the associative
     * array may also be a string or an array of paths.
     *
     * @param string|array $path Single path, array of paths or an associative array
     * @param string|null $namespace Limit the path only to specific namespace
     * @return ClassLoader Returns self for call chaining
     */
    public function addBasePath($path, $namespace = null)
    {
        $this->addPath($this->basePaths, $path, $namespace);

        return $this;
    }

    /**
     * Returns all known base paths.
     *
     * The paths will be returned as an associative array. The key indicates
     * the namespace and the values are arrays that contain all paths that
     * apply to that specific namespace. Paths that apply to all namespaces can
     * be found inside the key '' (i.e. empty string). Note that the array does
     * not include the paths in include_path even if the use of include_path is
     * enabled.
     *
     * @return array All known base paths
     */
    public function getBasePaths()
    {
        return $this->basePaths;
    }

    /**
     * Adds a PSR-4 compliant prefix path for searching classes.
     *
     * In PSR-4, it is possible to replace part of namespace with specific
     * path in the directory tree instead of requiring the entire namespace
     * structure to be present in the directory tree. For example, if the class
     * 'Vendor\Library\Class' is located in '/usr/lib/Library/src/Class.php',
     * You would need to add the path '/usr/lib/Library/src' to the namespace
     * 'Vendor\Library' by calling:
     *
     * <code>addPrefixPath('/usr/lib/Library/src', 'Vendor\Library')</code>
     *
     * If the method is called without providing a namespace, then the paths
     * work similarly to paths added via addBasePath(), except that the
     * underscores in the file name are not treated as namespace separators.
     *
     * Similarly to addBasePath(), the paths may be provided as an array or you
     * can just provide a single associative array as the parameter.
     *
     * @param string|array $path Single path or array of paths
     * @param string|null $namespace The namespace prefix the given path replaces
     * @return ClassLoader Returns self for call chaining
     */
    public function addPrefixPath($path, $namespace = null)
    {
        $this->addPath($this->prefixPaths, $path, $namespace);

        return $this;
    }

    /**
     * Returns all known prefix paths.
     *
     * The paths will be returned as an associative array. The key indicates
     * the namespace and the values are arrays that contain all paths that
     * apply to that specific namespace. Paths that apply to all namespaces can
     * be found inside the key '' (i.e. empty string).
     *
     * @return array All known prefix paths
     */
    public function getPrefixPaths()
    {
        return $this->prefixPaths;
    }

    /**
     * Adds the paths to the list of paths according to the provided parameters.
     * @param array $list List of paths to modify
     * @param string|array $path Single path or array of paths
     * @param string|null $namespace The namespace definition
     */
    private function addPath(& $list, $path, $namespace)
    {
        if ($namespace !== null) {
            $paths = [$namespace => $path];
        } else {
            $paths = is_array($path) ? $path : ['' => $path];
        }

        foreach ($paths as $ns => $directories) {
            $this->addNamespacePaths($list, ltrim($ns, '0..9'), $directories);
        }
    }

    /**
     * Canonizes the namespace and adds the paths to that specific namespace.
     * @param array $list List of paths to modify
     * @param string $namespace Namespace for the paths
     * @param string[] $paths List of paths for the namespace
     */
    private function addNamespacePaths(& $list, $namespace, $paths)
    {
        $namespace = $namespace === '' ? '' : trim($namespace, '\\') . '\\';

        if (!isset($list[$namespace])) {
            $list[$namespace] = [];
        }

        if (is_array($paths)) {
            $list[$namespace] = array_merge($list[$namespace], $paths);
        } else {
            $list[$namespace][] = $paths;
        }
    }

    /**
     * Attempts to load the class using known class paths.
     *
     * The classes will be searched using the prefix paths, base paths and the
     * include_path (if enabled) in that order. Other than that, the autoloader
     * makes no guarantees about the order of the searched paths.
     *
     * If verbose mode is enabled, then the method will return true if the class
     * loading was successful and false if not. Additionally the method will
     * throw an exception if the class already exists or if the class was not
     * defined in the file that was included.
     *
     * @param string $class Full name of the class to load
     * @return bool|null True if the class was loaded, false if not
     * @throws \RuntimeException If the class was not defined in the included file
     * @throws \InvalidArgumentException If the class already exists
     */
    public function loadClass($class)
    {
        if ($this->verbose) {
            return $this->load($class);
        }

        try {
            $this->load($class);
        } catch (\Exception $exception) {
            // Ignore exceptions as per PSR-4
        }
    }

    /**
     * Actually loads the class without any regard to verbose setting.
     * @param string $class Full name of the class to load
     * @return bool True if the class was loaded, false if not
     * @throws \InvalidArgumentException If the class already exists
     */
    private function load($class)
    {
        if ($this->isLoaded($class)) {
            throw new \InvalidArgumentException(sprintf(
                "Error loading class '%s', the class already exists",
                $class
            ));
        }

        if ($file = $this->findFile($class)) {
            return $this->loadFile($file, $class);
        }

        return false;
    }

    /**
     * Attempts to find a file for the given class using known paths.
     * @param string $class Full name of the class
     * @return string|false Path to the class file or false if not found
     */
    public function findFile($class)
    {
        return $this->finder->findFile($class, $this->prefixPaths, $this->basePaths, $this->useIncludePath);
    }

    /**
     * Includes the file and makes sure the class exists.
     * @param string $file Full path to the file
     * @param string $class Full name of the class
     * @return bool Always returns true
     * @throws \RuntimeException If the class was not defined in the included file
     */
    protected function loadFile($file, $class)
    {
        include $file;

        if (!$this->isLoaded($class)) {
            throw new \RuntimeException(vsprintf(
                "Error loading class '%s', the class was not defined in the file '%s'",
                [$class, $file]
            ));
        }

        return true;
    }

    /**
     * Tells if a class, interface or trait exists with given name.
     * @param string $class Full name of the class
     * @return bool True if it exists, false if not
     */
    private function isLoaded($class)
    {
        return class_exists($class, false) ||
            interface_exists($class, false) ||
            trait_exists($class, false);
    }
}
