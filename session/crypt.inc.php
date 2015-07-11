<?php
/** 
* This is the short description placeholder for the generic file docblock 
* 
* This is the long description placeholder for the generic file docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @author     John Lim 
* @copyright  2014-      The ADODB project 
* @copyright  2000-2014 John Lim 
* @license    BSD License    (Primary) 
* @license    Lesser GPL License    (Secondary) 
* @version    5.21.0 
* @package    ADODB 
* @category   FIXME 
* 
* @adodb-filecheck-status: FIXME
* @adodb-codesniffer-status: FIXME
* @adodb-documentor-status: FIXME
* 
*/ 
//	 Session Encryption by Ari Kuorikoski <ari.kuorikoski@finebyte.com>

/** 
* This is the short description placeholder for the class docblock 
*  
* This is the long description placeholder for the class docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @version 5.21.0 
* 
* @adodb-class-status FIXME
*/
class MD5Crypt{
		function keyED($txt,$encrypt_key)
		{
				$encrypt_key = md5($encrypt_key);
				$ctr=0;
				$tmp = "";
				for ($i=0;$i<strlen($txt);$i++){
						if ($ctr==strlen($encrypt_key)) $ctr=0;
						$tmp.= substr($txt,$i,1) ^ substr($encrypt_key,$ctr,1);
						$ctr++;
				}
				return $tmp;
		}
		function Encrypt($txt,$key)
		{
				srand((double)microtime()*1000000);
				$encrypt_key = md5(rand(0,32000));
				$ctr=0;
				$tmp = "";
				for ($i=0;$i<strlen($txt);$i++)
				{
				if ($ctr==strlen($encrypt_key)) $ctr=0;
				$tmp.= substr($encrypt_key,$ctr,1) .
				(substr($txt,$i,1) ^ substr($encrypt_key,$ctr,1));
				$ctr++;
				}
				return base64_encode($this->keyED($tmp,$key));
		}
		function Decrypt($txt,$key)
		{
				$txt = $this->keyED(base64_decode($txt),$key);
				$tmp = "";
				for ($i=0;$i<strlen($txt);$i++){
						$md5 = substr($txt,$i,1);
						$i++;
						$tmp.= (substr($txt,$i,1) ^ $md5);
				}
				return $tmp;
		}
		function RandPass()
		{
				$randomPassword = "";
				srand((double)microtime()*1000000);
				for($i=0;$i<8;$i++)
				{
						$randnumber = rand(48,120);
						while (($randnumber >= 58 && $randnumber <= 64) || ($randnumber >= 91 && $randnumber <= 96))
						{
								$randnumber = rand(48,120);
						}
						$randomPassword .= chr($randnumber);
				}
				return $randomPassword;
		}
}

/** 
* This is the short description placeholder for the class docblock 
*  
* This is the long description placeholder for the class docblock 
* Please see the ADOdb website for how to maintain adodb custom tags
* 
* @version 5.21.0 
* 
* @adodb-class-status FIXME
*/
class SHA1Crypt{
		function keyED($txt,$encrypt_key)
		{
				$encrypt_key = sha1($encrypt_key);
				$ctr=0;
				$tmp = "";
				for ($i=0;$i<strlen($txt);$i++){
						if ($ctr==strlen($encrypt_key)) $ctr=0;
						$tmp.= substr($txt,$i,1) ^ substr($encrypt_key,$ctr,1);
						$ctr++;
				}
				return $tmp;
		}
		function Encrypt($txt,$key)
		{
				srand((double)microtime()*1000000);
				$encrypt_key = sha1(rand(0,32000));
				$ctr=0;
				$tmp = "";
				for ($i=0;$i<strlen($txt);$i++)
				{
				if ($ctr==strlen($encrypt_key)) $ctr=0;
				$tmp.= substr($encrypt_key,$ctr,1) .
				(substr($txt,$i,1) ^ substr($encrypt_key,$ctr,1));
				$ctr++;
				}
				return base64_encode($this->keyED($tmp,$key));
		}
		function Decrypt($txt,$key)
		{
				$txt = $this->keyED(base64_decode($txt),$key);
				$tmp = "";
				for ($i=0;$i<strlen($txt);$i++){
						$sha1 = substr($txt,$i,1);
						$i++;
						$tmp.= (substr($txt,$i,1) ^ $sha1);
				}
				return $tmp;
		}
		function RandPass()
		{
				$randomPassword = "";
				srand((double)microtime()*1000000);
				for($i=0;$i<8;$i++)
				{
						$randnumber = rand(48,120);
						while (($randnumber >= 58 && $randnumber <= 64) || ($randnumber >= 91 && $randnumber <= 96))
						{
								$randnumber = rand(48,120);
						}
						$randomPassword .= chr($randnumber);
				}
				return $randomPassword;
		}
}
