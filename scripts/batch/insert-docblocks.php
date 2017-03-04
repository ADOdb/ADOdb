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
		
		if ($p <> "adodb-active-record.inc.php")
			continue;
		if (substr($p,-4) <> '.php')
			continue;

		print "$d/$p\n";
		doFixProg("$d/$p");
	}
}
function doFixProg($p) {

	global $defaultFileComment;
	global $fileDocBlock;
	global $driverDocBlock;
	global $functionDocBlock;
	global $functionDocBlock4;
	global $classDocBlock;
	
	$s = preg_split("/[\r\n]+/",$result);
	$t = '';
	foreach($s as $i=>$k){
		$k = preg_replace('/^class /',"{$classDocBlock}class ",$k);
		
		if (preg_match('/^(\t| {4}|)function/',$k)){
			
		}
		$preK = $k;
		
		if (preg_match('/function /',$k)){
			$paramString = '';
			/*
			 * Find the paramaters
			 */
			$breaks = preg_split('/[\(\),]+/',$k);
			array_shift($breaks);
			array_pop($breaks);
			foreach($breaks as $b){
				$tag = '';
				$byReference = false;
				$defaultValue='';
				
				$b = str_replace(' ','',$b);
				
				$exp = explode('=',$b);
				
				if (preg_match('/&/',$exp[0])){
					$byReference = ' Passed by reference. ';
				}
				
				$exp[0] = str_replace('&','',$exp[0]);
				
				$tag = '##* @param FIXME ' . $exp[0] . $byReference;
				
				if (count($exp) > 1)
					$tag .= ' (optional)default ' . $exp[1];
				
				$tag .= "\n";
				$paramString .= $tag;
				
				
			}
			//$paramString = trim($paramString);
			//print $paramString;
		}
		if (preg_match('/^(public |)function /',$k)){
			$paramString = str_replace('##','',$paramString);
			$fdb = str_replace('##',$paramString,$functionDocBlock);
			$k = preg_replace('/^function /',"{$fdb}function ",$k);
		}
		
		if (preg_match('/^(\t| {4})(public |)function /',$k)){
			
			print "In 4 blocks with data $k....\n";
			$paramString = str_replace('##','    ',$paramString);
			$fdb = str_replace('##',$paramString,$functionDocBlock4);
			print $fdb;
			$k = $fdb . $k;
			//$k = preg_replace('/^(\t| {4})function /',"{$fdb}function ",$k);
		}
		if (preg_match('/^(\t| {4})static function /',$k)){
			print "In 4 blocks....\n";
			$paramString = str_replace('##','    ',$paramString);
			$fdb = str_replace('##',$paramString,$functionDocBlock4);
			print $fdb;
			$k = preg_replace('/^(\t| {4})static function /',"{$fdb}function ",$k);
		}
	   // $k = preg_replace('/^(\t| {4})public function /',"{$functionDocBlock4}    public function ",$k);
		
		$s[$i] = $k;
	}
	print_r($s);
	$result = implode("\n",$s);
	
	file_put_contents($rstring,$result);
}

$fileDocBlock = file_get_contents('file-docblock.txt');
$driverDocBlock = file_get_contents('driver-docblock.txt');
$functionDocBlock = file_get_contents('function-docblock.txt');
$functionDocBlock4 = file_get_contents('function-docblock4.txt');
$classDocBlock = file_get_contents('class-docblock.txt');

doDir('/dev/github/sddata');

?>