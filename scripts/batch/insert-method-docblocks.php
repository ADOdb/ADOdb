<?php
/**
  * Program to insert class method function docblocks into programs
  *
  * @author Mark Newnham
  * @date 11/10/2015
  */

$defaultMethodDocBlock = 
"    * This is the long description placeholder for the class method docblock
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
	global $defaultMethodDocBlock;
	
	$result = file_get_contents($p);
	$s = preg_split("/\n+/",$result);
	$t = '';
	$accruedComment = array();
	$program = '';
	foreach($s as $lineNo=>$data){
		
		if (preg_match('/^    \/\//',$data))
		{
			$accruedComment[] = $data;
			continue;
		}
		if (!preg_match('/^ {4}(static |private |public |protected |)function/',$data))
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

		if (!preg_match('/^ {4}(static |private |public |protected |)function/',$data))
		{
			$a.= $data . "\n";
			continue;
		}
		
		$fdb = $functionDocBlock;
		
		/*
		* Try to determine the parameters
		*/
		preg_match_all('/\((.*?)\)/',$data,$methodParameters);
		if (!isset($methodParameters[0]))
			$fdb = str_replace('@##@','',$fdb);
		elseif (!isset($methodParameters[0][1]))
			$fdb = str_replace('@##@','',$fdb);
		elseif ($methodParameters[0][1] == '')
			$fdb = str_replace('@##@','',$fdb);
		else
		{
			$mpText = '';
			$mpGroup = explode(',',$methodParameters[0][1]);
			foreach ($mpGroup as $mpg)
			{
				$mpText .= '    * @param FIXME ';
				$mpgs = explode('=',$mpg);
				$mpText .= $mpgs[0];
				if (count($mpgs) > 1)
					$mpText .= 'optional, default ' . $mpgs[1];
				$mpText .= "\n";
			}
			$fdb = str_replace('@##@',$mpText,$fdb);
			$mpText = '';
		}
						
		if (count($accruedComment) > 0)
		{
			$ac = '';
			foreach($accruedComment as $a)
			{
				$a = preg_replace('/^    \/\//','',$a);
				$ac .= '    * ' . trim($a) . "\n";
			}
			$ac = trim($ac,"\n");
			$fdb = str_replace('!##!',$ac,$fdb);
			$accruedComment = array();
				
		}
		else
		{
			$fdb = str_replace('!##!',$defaultMethodDocBlock,$fdb);
		}
		$program.= $fdb;
		$program.= $data . "\n";
		
	}
	
	file_put_contents($p,$program);
}

$functionDocBlock = file_get_contents('method-docblock.txt');

doDir('/dev/github/sddata');

?>