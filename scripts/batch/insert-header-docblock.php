<?php
/**
  * Program to insert header docblocks into programs
  *
  * @author Mark Newnham
  * @date 09/07/2015
  */
$defaultFileComment = 
'* This is the long description placeholder for the generic file docblock
* Please see the ADOdb website for how to maintain adodb custom tags
';
function destroyOriginalHeaderComment($t){
	
	$commentMatches = array();

	preg_match_all('/\/\*(.*?)\*\//s',$t,$commentMatches);

	$original = $commentMatches[0][0];
    $r = str_replace($original,'',$t);
    return $r;
}

function extractHeaderComment($t,$fileName){
	$commentMatches = array();
	preg_match_all('/\/\*(.*?)\*\//s',$t,$commentMatches);
	
	/*
	* Any header text is commentMatches[0];
	*/
	if (!isset($commentMatches[1]) || !isset($commentMatches[1][0]))
	{
		print "\n No header found in file $fileName";
		return false;
	}
	$headerText = $commentMatches[1][0];
	
	/*
	* Find something after 'precendence'
	*/
	$cm = explode('precedence',$headerText);
	
	if (!isset($cm[1]))
	{
		print "\n No extra date found in file $fileName";
		return false;
	}
	$headerText = $cm[1];
	/*
	* Go to work on whats left
	*/
	$ht = preg_split("/\n+/",$headerText);
	$ht = array_map('trim',$ht);

	$rewrite = '';
	foreach($ht as $h)
	{
		if ($h == '' || $h == '.')
			continue;
		$rewrite .= "* $h\n";
	}
	$rewrite = trim($rewrite,"\n");
	return $rewrite;
	
}

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
		
	$fdb = $fileDocBlock;
	if (preg_match('/\/(drivers|datadict|perf)\//',$p))
		$fdb = $driverDocBlock;
	
	$basis = file_get_contents($p);
	/*
	 * Look at the header and replace the generic header 
	 * with any JL text
	 */
	
	/*
	 We are going to insert the text as the main comment so clean this text up a bit
	 */
	$dbText = extractHeaderComment($basis, $p);
	
	if ($dbText){
		$fdb = str_replace('!##!',$dbText,$fdb);
		/*
		 * Now remve the original docblock
		 */
		$basis = destroyOriginalHeaderComment($basis);
		
	}
	$result = preg_replace('/<\?php/',"<?php\n$fdb",$basis);
	
	//$result = implode("\n",$s);
	
	file_put_contents($p,$result);
}

$fileDocBlock   = file_get_contents('file-docblock.txt');
$driverDocBlock = file_get_contents('driver-docblock.txt');

doDir('/dev/github/sddata');

?>