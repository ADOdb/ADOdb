<?php 
class ADOdbTest extends PHPUnit_Framework_TestCase{
  public function setUp(){
  }

  public function tearDown(){
  }

  public function test01connect(){
    $db = NewADOConnection($GLOBALS['db_type']);
    $db->Connect($GLOBALS['db_host'], $GLOBALS['db_user'], $GLOBALS['db_pass'], $GLOBALS['db_name']);
  }
}
?>
