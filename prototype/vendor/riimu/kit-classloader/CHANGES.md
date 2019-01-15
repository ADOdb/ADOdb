# Changelog #

## v4.4.0 (2017-07-16) ##

  * Increase minimum required PHP version 5.6
  * Ensure that unit tests also work in PHPUnit6
  * Improve the bundled autoloader script slightly
  * Update to latest coding standards
  * Add PHP 7.1 tests to travis build

## v4.3.1 (2015-08-22) ##

  * Addressed minor documentation and coding standards issues

## v4.3.0 (2015-01-08) ##

  * Introduced ClassFinder for handling class file searching operations.
  * Reduced overall code complexity.

## v4.2.0 (2015-01-01) ##

  * ClassLoader::findFile now does a better job of canonizing directory separators
  * ClassLoader::loadFile will now always return true or throw an exception,
    irregardless of verbose setting
  * Class loader will now look in the include_path last
  * Some documentation clarification and fixes
  * Overall changes in code to reduce the complexity

## v4.1.0 (2014-06-19) ##

  * add*Path methods accept a mixed list of paths with namespaces and paths
    without namespace
  * Added getBasePaths() and getPrefixPaths() methods to retrieve added paths
  * Clarified documentation on possibility of using arrays for multiple paths

## v4.0.1 (2014-05-28) ##

  * Code cleanup and documentation fixes
