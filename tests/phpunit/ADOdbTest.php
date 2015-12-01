<?php 
class ADOdbTest extends PHPUnit_Framework_TestCase{
  public function setUp(){
  }

  public function tearDown(){
  }

  public function test01connect(){
    $db = NewADOConnection($db_type);
    $db->Connect($db_host, $db_user, $db_pass, $db_name);
  }
}
?>
