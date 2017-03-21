<?php

class Postgres9Test extends PHPUnit_Framework_TestCase
{
    public function testDB()
    {
        if (!function_exists('pg_connect')) {
            echo "Skipping Postgres9 tests" . PHP_EOL;
            return;
        }
        $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
        $credentials = $credentials['postgres'];

        $con = ADONewConnection('postgres9');
        $this->assertInternalType('object', $con, 'Could not get driver object');
        $this->assertEquals(false, $con->IsConnected());

        $con->Connect('localhost', $credentials['user'], $credentials['password'], 'adodb_test');
        $this->assertEquals(true, $con->IsConnected(), 'Could not connect');

        $info = $con->ServerInfo();
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('version', $info);

        $this->assertEquals(true, is_numeric($con->Time()), 'Could not get time');
        $this->assertEquals('TO_CHAR(CURRENT_TIMESTAMP,\'YYYY-MM-DD\')', $con->SQLDate('Y-m-d'));
        $this->assertEquals('TO_CHAR(foo,\'YYYY-MM-DD\')', $con->SQLDate('Y-m-d', 'foo'));

        $this->assertEquals('foo', $con->Prepare('foo'));
        $this->assertEquals("'foo'", $con->Quote('foo'));
        $byRef = 'foo';
        $con->q($byRef);
        $this->assertEquals("'foo'", $byRef);
        $this->assertEquals('foo', $con->PrepareSP('foo'));

        $this->assertEquals("'foo'", $con->qstr('foo'));
        $this->assertEquals('$1', $con->Param('foo'));

        $con->Execute("DROP TABLE IF EXISTS test");

        $create = $con->Prepare("CREATE TABLE test (id SERIAL, val INT, PRIMARY KEY(id))");
        $con->Execute($create);
        $insert = $con->Prepare("INSERT INTO test (val) VALUES (?)");
        $con->Execute($insert, array(1));
        $this->assertEquals(1, $con->Insert_ID());
        $con->Execute('UPDATE test SET val=2 WHERE id=1');
        $this->assertEquals(1, $con->Affected_Rows());
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

        $this->assertEquals(" coalesce(id, 0) ", $con->IfNull('id', 0));
        $this->assertEquals("a||b", $con->Concat('a', 'b'));

        $this->assertEquals(true, in_array('adodb_test', $con->MetaDatabases()));
        $this->assertEquals(array('test'), $con->MetaTables());
        $cols = $con->MetaColumns('test');
        $this->assertEquals(true, $cols['ID']->primary_key);
        $this->assertEquals(true, $cols['ID']->not_null);
        $this->assertEquals(false, $cols['VAL']->not_null);
        $this->assertEquals('SERIAL', $cols['ID']->type);
        $this->assertEquals('id', $cols['ID']->name);
        $this->assertEquals(array(), $con->MetaIndexes('test'));
        $this->assertEquals(array('ID'=>'id', 'VAL'=>'val'), $con->MetaColumnNames('test'));

        $con->Execute("DROP TABLE IF EXISTS test");
        $this->assertEquals(true, $con->Close());
    }
}

