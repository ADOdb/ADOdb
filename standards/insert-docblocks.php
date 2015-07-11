<?php
/**
  * Program to insert docblocks into programs
  *
  * @author Mark Newnham
  * @date 09/07/2015
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
	
	$result = preg_replace('/<\?php/',"<?php$fdb",$basis);
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