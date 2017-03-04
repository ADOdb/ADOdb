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

	global $classDocBlock;
	
	$fileData = file_get_contents($p);
	$fileData = preg_replace('/\n(class)+/s',"{$classDocBlock}class ",$fileData);
	file_put_contents($p,$fileData);
}

$classDocBlock = file_get_contents('/dev/github/standards/class-docblock.txt');

doDir('/dev/github/sddata');

?>