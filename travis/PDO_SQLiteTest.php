<?php

class PDO_SQLiteTest extends PHPUnit_Framework_TestCase
{
    /**
     * This test creates a table, loads values into it, uses ADOdb
     * Meta* methods to inspect the table, and finally drops the table again
     */
    public function testDB()
    {
        if (!class_exists('PDO')) {
            echo "Skipping PDO_SQLite tests" . PHP_EOL;
            return;
        }
        $db_file = tempnam(sys_get_temp_dir(), 'sql') . '.db';

        $con = ADONewConnection('pdo');
        $this->assertInternalType('object', $con, 'Could not get driver object');
        $this->assertEquals(false, $con->IsConnected());

        $con->Connect('sqlite:' . $db_file, '', '', '');
        $this->assertEquals(true, $con->IsConnected(), 'Could not connect');

        $info = $con->ServerInfo();
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('version', $info);

        $this->assertEquals(true, is_numeric($con->Time()), 'Could not get time');
        /**
          The pdo_sqlite class does not implement SQLDate. The base
          pdo class calls SQLDate on its $_driver memember. Since all the PDO subtypes
          inherit from the main PDO class, any subclass that doesn't override 
          SQLDate either crashes because the $_driver member is null in
          the subclass instance or recurses infinitely
        $this->assertEquals('DATE_FORMAT(NOW(),\'%Y-%m-%d\')', $con->SQLDate('Y-m-d'));
        $this->assertEquals('DATE_FORMAT(foo,\'%Y-%m-%d\')', $con->SQLDate('Y-m-d', 'foo'));
        */

        $this->assertInternalType('array', $con->Prepare('SELECT 1'));
        $this->assertInternalType('array', $con->PrepareSP('SELECT 1'));

        $this->assertEquals("'foo'", $con->qstr('foo'));
        $this->assertEquals("'foo'", $con->Quote('foo'));
        $byRef = 'foo';
        $con->q($byRef);
        $this->assertEquals("'foo'", $byRef);
        $this->assertEquals('?', $con->Param('foo'));

        $con->Execute("DROP TABLE IF EXISTS test");

        $create = $con->Prepare("CREATE TABLE test (id INT PRIMARY KEY, val INT)");
        $con->Execute($create);
        $insert = $con->Prepare("INSERT INTO test (val) VALUES (?)");
        $con->Execute($insert, array(1));
        $this->assertEquals(1, $con->Insert_ID());
        $con->Execute('UPDATE test SET val=2 WHERE id=1');
        // unimplemented in sqlite?
        //$this->assertEquals(1, $con->Affected_Rows());
        $this->assertEquals('', $con->ErrorMsg());
        $this->assertEquals(0, $con->ErrorNo());
        $this->assertEquals(array('id'), $con->MetaPrimaryKeys('test'));

        $con->BeginTrans();
        $con->Execute("INSERT INTO test (val) VALUES (3)");
        $con->RollbackTrans();
        $rs = $con->Execute("SELECT id FROM test");
        $this->assertEquals(1, $rs->NumRows());

        $con->BeginTrans();
        $con->Execute("INSERT INTO test (val) VALUES (3)");
        $con->CommitTrans();
        $rs = $con->Execute("SELECT id FROM test");
        $this->assertEquals(2, $rs->NumRows());

        $this->assertNotEquals(false, $con->CreateSequence());
        $this->assertEquals(1, $con->GenID());
        $this->assertEquals(2, $con->GenID());
        $this->assertNotEquals(false, $con->DropSequence());

        $this->assertEquals("1", $con->GetOne('SELECT 1 AS id'));
        $this->assertEquals("1", $con->CacheGetOne(5, 'SELECT 1 AS id'));
        $this->assertEquals(array(0=>1), $con->GetCol('SELECT 1 AS id'));
        $this->assertEquals(array(0=>1), $con->CacheGetCol(5, 'SELECT 1 AS id'));
        $this->assertEquals(array(0=>array(0=>1,'id'=>1)), $con->GetArray('SELECT 1 AS id'));
        $this->assertEquals(array(0=>array(0=>1,'id'=>1)), $con->CacheGetArray('SELECT 1 AS id'));
        $this->assertEquals(array(0=>1,'id'=>1), $con->GetRow('SELECT 1 AS id'));
        $this->assertEquals(array(0=>1,'id'=>1), $con->CacheGetRow(5, 'SELECT 1 AS id'));

        $this->assertEquals(" CASE WHEN id is null THEN 0 ELSE id END ", $con->IfNull('id', 0));
        $this->assertEquals("a||b", $con->Concat('a', 'b'));

        $this->assertEquals(false, $con->MetaDatabases());
        $this->assertEquals(array('test'), $con->MetaTables());
        $cols = $con->MetaColumns('test');
        $this->assertEquals(true, $cols['ID']->primary_key);
        $this->assertEquals(0, $cols['ID']->not_null);
        $this->assertEquals(0, $cols['VAL']->primary_key);
        $this->assertEquals(0, $cols['VAL']->not_null);
        $this->assertEquals('INT', $cols['ID']->type);
        $this->assertEquals('id', $cols['ID']->name);
        $this->assertEquals(false, $con->MetaIndexes('test'));
        $this->assertEquals(array('ID'=>'id', 'VAL'=>'val'), $con->MetaColumnNames('test'));

        $con->Execute("DROP TABLE IF EXISTS test");
        $this->assertEquals(true, $con->Close());
        unlink($db_file);
    }
}

