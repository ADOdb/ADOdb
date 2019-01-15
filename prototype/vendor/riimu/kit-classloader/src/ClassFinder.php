<?php

namespace Riimu\Kit\ClassLoader;

/**
 * Provides method for searching class files in the file system.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2015-2017 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class ClassFinder
{
    /** @var string[] List of file extensions used to find files */
    private $fileExtensions;

    /**
     * Creates a new PathFinder instance.
     */
    public function __construct()
    {
        $this->fileExtensions = ['.php'];
    }

    /**
     * Sets list of dot included file extensions to use for finding files.
     *
     * If no list of extensions is provided, the extension array defaults to
     * just '.php'.
     *
     * @param string[] $extensions Array of dot included file extensions to use
     */
    public function setFileExtensions(array $extensions)
    {
        $this->fileExtensions = $extensions;
    }

    /**
     * Attempts to find a file for the given class from given paths.
     *
     * Both lists of paths must be given as arrays with keys indicating the
     * namespace. Empty string can be used for the paths that apply to all
     * Classes. Each value must be an array of paths.
     *
     * @param string $class Full name of the class
     * @param array $prefixPaths List of paths used for PSR-4 file search
     * @param array $basePaths List of paths used for PSR-0 file search
     * @param bool $useIncludePath Whether to use paths in include_path for PSR-0 search or not
     * @return string|false Path to the class file or false if not found
     */
    public function findFile($class, array $prefixPaths, array $basePaths = [], $useIncludePath = false)
    {
        if ($file = $this->searchNamespaces($prefixPaths, $class, true)) {
            return $file;
        }

        $class = preg_replace('/_(?=[^\\\\]*$)/', '\\', $class);

        if ($file = $this->searchNamespaces($basePaths, $class, false)) {
            return $file;
        } elseif ($useIncludePath) {
            return $this->searchDirectories(explode(PATH_SEPARATOR, get_include_path()), $class);
        }

        return false;
    }

    /**
     * Searches for the class file from the namespaces that apply to the class.
     * @param array $paths All the namespace specific paths
     * @param string $class Canonized full class name
     * @param bool $truncate True to remove the namespace from the path
     * @return string|false Path to the class file or false if not found
     */
    private function searchNamespaces($paths, $class, $truncate)
    {
        foreach ($paths as $namespace => $directories) {
            $canonized = $this->canonizeClass($namespace, $class, $truncate);

            if ($canonized && $file = $this->searchDirectories($directories, $canonized)) {
                return $file;
            }
        }

        return false;
    }

    /**
     * Matches the class against the namespace and canonizes the name as needed.
     * @param string $namespace Namespace to match against
     * @param string $class Full name of the class
     * @param bool $truncate Whether to remove the namespace from the class
     * @return string|false Canonized class name or false if it does not match the namespace
     */
    private function canonizeClass($namespace, $class, $truncate)
    {
        $class = ltrim($class, '\\');
        $namespace = (string) $namespace;

        $namespace = $namespace === '' ? '' : trim($namespace, '\\') . '\\';

        if (strncmp($class, $namespace, strlen($namespace)) !== 0) {
            return false;
        }

        return $truncate ? substr($class, strlen($namespace)) : $class;
    }

    /**
     * Searches for the class file in the list of directories.
     * @param string[] $directories List of directory paths where to look for the class
     * @param string $class Part of the class name that translates to the file name
     * @return string|false Path to the class file or false if not found
     */
    private function searchDirectories(array $directories, $class)
    {
        foreach ($directories as $directory) {
            $directory = trim($directory);
            $path = preg_replace('/[\\/\\\\]+/', DIRECTORY_SEPARATOR, $directory . '/' . $class);

            if ($directory && $file = $this->searchExtensions($path)) {
                return $file;
            }
        }

        return false;
    }

    /**
     * Searches for the class file using known file extensions.
     * @param string $path Path to the class file without the file extension
     * @return string|false Path to the class file or false if not found
     */
    private function searchExtensions($path)
    {
        foreach ($this->fileExtensions as $ext) {
            if (file_exists($path . $ext)) {
                return $path . $ext;
            }
        }

        return false;
    }
}
