<?php
/**
  * Program to insert function docblocks into programs
  *
  * @author Mark Newnham
  * @date 09/07/2015
  */

$defaultFunctionDocBlock = 
"* This is the long description placeholder for the function docblock
* Please see the ADOdb website for how to maintain adodb custom tags";  
  
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

	global $functionDocBlock;
	global $defaultFunctionDocBlock;
	
	$result = file_get_contents($p);
	$s = preg_split("/\n+/",$result);
	$t = '';
	$accruedComment = array();
	$program = '';
	foreach($s as $lineNo=>$data){
		
		if (preg_match('/^\/\//',$data))
		{
			$accruedComment[] = $data;
			continue;
		}
		elseif (!preg_match('/^function/',$data))
		{
			if (count($accruedComment) > 0)
			{
				$a = implode("\n",$accruedComment);
				$program.= $a . "\n";
			}
		    $accruedComment = array();
			$program .= $data . "\n";
			continue;
		}

		if (!preg_match('/^function/',$data))
		{
			$a.= $data . "\n";
			continue;
		}
		
		$fdb = $functionDocBlock;
		
		if (count($accruedComment) > 0)
		{
			$ac = '';
			foreach($accruedComment as $a)
			{
				$a = preg_replace('/^\/\//','',$a);
				$ac .= '* ' . trim($a) . "\n";
			}
			$ac = trim($ac,"\n");
			$fdb = str_replace('!##!',$ac,$fdb);
			$accruedComment = array();
				
		}
		else
		{
			$fdb = str_replace('!##!',$defaultFunctionDocBlock,$fdb);
		}
		$program.= $fdb;
		$program.= $data . "\n";
		
	}
	
	file_put_contents($p,$program);
}

$functionDocBlock = file_get_contents('function-docblock.txt');

doDir('/dev/github/sddata');

?>