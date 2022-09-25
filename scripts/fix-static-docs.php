<?php
/**
 * Post-processing of the DokuWiki documentation export.
 *
 * @link https://adodb.org/dokuwiki/doku.php?id=admin:offline_docs_build
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @package ADOdb
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v2.1 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-2.1-or-later
 *
 * @copyright 2015 Damien Regad, Mark Newnham and the ADOdb community
 * @author Mark Newnham
 */

/**
 *
 */
$source_dir = 'documentation-base';
$target_dir = 'documentation';

/**
* Recurses a directory and deletes files inside
*
* Copied from php.net
*
* @param string $dir  The directory name
* $return bool
*/
function delTree($dir)
{
	$files = @scandir($dir);
	if ($files === false) {
		return false;
	}
	$files = array_diff($files, array('.', '..'));

	foreach ($files as $file) {
		$result = (is_dir("$dir/$file") ? delTree("$dir/$file") : unlink("$dir/$file"));
		if ($result === false) {
			return false;
		}
	}
	return rmdir($dir);
}

/**
* Initializes the listdiraux method with a starting directory point
*
* copied from php.net
*
* @param  string $dir Starting directory
* @return array|false FQ list of files
*/
function listdir($dir='.')
{
	if (!is_dir($dir)) {
		return false;
	}
	$files = array();
	listdiraux($dir, $files);

	return $files;
}

/**
* Recurses a directory structure and creates a list of files
*
* @param  string 	$dir	Starting directory
* @param  string[]  $files  By reference, the current file list
* @return void
*/
function listdiraux($dir, &$files)
{
	$handle = opendir($dir);
	while (($file = readdir($handle)) !== false) {
		if ($file == '.' || $file == '..') {
			continue;
		}

		// Exclude internal use namespaces
		if (preg_match('/^(?:admin|playground|wiki)/', $file)) {
			continue;
		}

		// This is only v5 documentation
		if (preg_match('/v6$/', $file)) {
			continue;
		}

		$filepath = $dir == '.' ? $file : $dir . '/' . $file;
		if (is_link($filepath)) {
			continue;
		}
		if (is_file($filepath)) {
			$files[] = $filepath;
		} else {
			if (is_dir($filepath)) {
				listdiraux($filepath, $files);
			}
		}
	}
	closedir($handle);
}

/*
* Clean up the documentation directory from prior use
*/
if (is_dir($target_dir)) {
	if(!deltree($target_dir)) {
		echo "ERROR: unable to remove target directory '$target_dir'\n";
		die(1);
	}
}
mkdir($target_dir);

$files = listdir($source_dir);
if (!$files) {
	echo "ERROR: Source directory '$source_dir' not found, not accessible or empty\n";
	die(1);
}
sort($files, SORT_LOCALE_STRING);

/*
* Loop through files in source directory, creating a mirror structure
* in target directory, and applying the post-process rules defined below
*/
foreach ($files as $f) {

	$r = str_replace($source_dir, $target_dir, $f);
	$dList = explode('/', $r);
	$titleList = $dList;
	/*
	* Get rid of the initial directory
	*/
	array_shift($titleList);

	$depth = count($dList) - 2;
	$dSlash = '';
	while (count($dList) > 1) {
		$dSlash .= array_shift($dList) . '/';
		if (!is_dir($dSlash)) {
			mkdir($dSlash);
		}
	}
	if (!is_file($f)) {
		continue;
	}
	if (substr($f, -4) <> 'html') {
		/*
		* An image or something else, copy unmodified
		*/
		copy($f, $r);
		continue;
	}

	$prepend = str_repeat('../', $depth);

	$doc = new DOMDocument();
	@$doc->loadHTMLFile($f);

	/*
	* Remove Page Tools Group
	*/
	$xpath = new DOMXPath($doc);

	/*
	* Remove Top Menu Tools Group, and add a link to the ADOdb site
	*/
	$nodes = $xpath->query("//div[@class='tools group']");
	foreach ($nodes as $node) {
		$pn = $node->parentNode;
		$pn->removeChild($node);
		$newChild = $doc->createElement('div');
		$newDiv = $pn->appendChild($newChild);
		$newDiv->setAttribute('style', 'text-align:right');
		$newChild = $doc->createElement('a', 'ADOdb Web Site');
		$newA = $newDiv->appendChild($newChild);
		$newA->setAttribute('href', 'https://adodb.org');
	}

	/*
	* Remove Trace
	*/
	$nodes = $xpath->query("//div[@class='breadcrumbs']");
	foreach ($nodes as $node) {
		$node->parentNode->removeChild($node);
	}

	/*
	* Remove Side Menu Tools Group
	*/
	$nodes = $xpath->query("//div[@id='dokuwiki__pagetools']");
	foreach ($nodes as $node) {
		$node->parentNode->removeChild($node);
	}

	/*
	* Fix main links
	*/
	$nodes = $xpath->query("//a[@class='wikilink1']");
	foreach ($nodes as $node) {
		$n = $node->getAttribute('title');
		$p = $prepend . str_replace(':', '/', $n) . '.html';
		$node->setAttribute('href', $p);
	}

	/*
	* Fix In Page links
	*/
	$nodes = $xpath->query("//a[@class='wikilink2']");
	foreach ($nodes as $node) {
		$n = $node->getAttribute('title');
		$p = $prepend . str_replace(':', '/', $n) . '.html';
		$node->setAttribute('href', $p);
	}

	/*
	* Make Graphic point to first page. This will break if the image size
	* ever changes.
	*/
	$corePage = $prepend . '/index.html';
	$nodes = $xpath->query("//img[@width='176']");
	foreach ($nodes as $node) {
		$node->parentNode->setAttribute('href', $corePage);
	}

	/*
	* Change title of page
	*/
	$nodes = $xpath->query("//title");
	foreach ($nodes as $node) {
		$docTitle = implode(':', $titleList);
		$docTitle = str_replace('.html', '', $docTitle);
		$pn = $node->parentNode;
		$pn->removeChild($node);
		$newChild = $doc->createElement('title', $docTitle);
		$pn->appendChild($newChild);
	}

	$doc->saveHTMLFile($r);

	echo $r, "\n";
}

/*
* Now remove the original index and replace it with the hardcopy documentation one
*/
unlink ($target_dir . '/index.html');
rename($target_dir . '/adodb_index.html',$target_dir . '/index.html');

/*
* We could add in an auto zip and upload here, but this is a good place to
* stop and check the output
*/
