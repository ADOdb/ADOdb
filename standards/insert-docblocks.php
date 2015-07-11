<?php
/** 
* This is the short description placeholder for the generic file docblock 
* 
* This is the long description placeholder for the generic file docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @category   FIXME
* @package    ADODB 
* @author     John Lim 
* @copyright  2014-      The ADODB project 
* @copyright  2000-2014 John Lim 
* @license    BSD License    (Primary) 
* @license    Lesser GPL License    (Secondary) 
* @version    5.21.0 
* 
* @adodb-filecheck-status: FIXME
* @adodb-codesniffer-status: FIXME
* @adodb-documentor-status: FIXME
* 
*/ 
/**
  * Program to insert docblocks into programs
  *
  * @author Mark Newnham
  * @date 09/07/2015
  */
  

/** 
* This is the short description placeholder for the function docblock 
*  
* This is the long description placeholder for the function docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @version 5.21.0 
* @param   FIXME 
* @return  FIXME 
* 
* @adodb-visibility  FIXME
* @adodb-function-status FIXME
* @adodb-api FIXME 
*/
function doDir($d) {
	$dlist = scandir($d);
	foreach($dlist as $p){
		if ($p == '.' || $p == '..')
			continue;
		if (is_dir("$d/$p")){
			doDir("$d/$p");
			continue;
		}
		if (substr($p,-4) <> '.php')
			continue;
		print "$d/$p\n";
		doFixProg("$d/$p");
	}
}

/** 
* This is the short description placeholder for the function docblock 
*  
* This is the long description placeholder for the function docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @version 5.21.0 
* @param   FIXME 
* @return  FIXME 
* 
* @adodb-visibility  FIXME
* @adodb-function-status FIXME
* @adodb-api FIXME 
*/
function doFixProg($p) {
	
	global $fileDocBlock;
	global $driverDocBlock;
	global $functionDocBlock;
	global $functionDocBlock4;
	global $classDocBlock;
	
	$fdb = $fileDocBlock;
	if (preg_match('/\/(drivers|datadict|perf)\//',$p))
		$fdb = $driverDocBlock;
	$basis = file_get_contents($p);
	$rstring = str_replace('-basis','',$p);
	
	$result = preg_replace('/<\?php/',"<?php
/** 
* This is the short description placeholder for the generic file docblock 
* 
* This is the long description placeholder for the generic file docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @category   FIXME
* @package    ADODB 
* @author     John Lim 
* @copyright  2014-      The ADODB project 
* @copyright  2000-2014 John Lim 
* @license    BSD License    (Primary) 
* @license    Lesser GPL License    (Secondary) 
* @version    5.21.0 
* 
* @adodb-filecheck-status: FIXME
* @adodb-codesniffer-status: FIXME
* @adodb-documentor-status: FIXME
* 
*/ 
$fdb",$basis);
	$s = preg_split("/[\n\r]+/",$result);
	$t = '';
	foreach($s as $i=>$k){
		
		$k = preg_replace('/^class /',"{$classDocBlock}class ",$k);
		$k = preg_replace('/^function /',"{$functionDocBlock}function ",$k);
	    $k = preg_replace('/^(\t| {4})function /',"{$functionDocBlock4}    function ",$k);
	    $k = preg_replace('/^(\t| {4})static function /',"{$functionDocBlock4}    static function ",$k);
	    $k = preg_replace('/^(\t| {4})function /',"{$functionDocBlock4}    function ",$k);
	    $k = preg_replace('/^(\t| {4})public function /',"{$functionDocBlock4}    public function ",$k);
		
		$s[$i] = $k;
	}
	
	$result = implode("\n",$s);
	
	file_put_contents($rstring,$result);
}
$fileDocBlock = file_get_contents('file-docblock.txt');
$driverDocBlock = file_get_contents('driver-docblock.txt');
$functionDocBlock = file_get_contents('function-docblock.txt');
$functionDocBlock4 = file_get_contents('function-docblock4.txt');
$classDocBlock = file_get_contents('class-docblock.txt');
doDir('/temp/ADOdb-basis');
?>