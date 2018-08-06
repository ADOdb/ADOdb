<?php

class PDO_MySQLTest extends PHPUnit_Framework_TestCase
{
    /**
     * This test creates a table, loads values into it, uses ADOdb
     * Meta* methods to inspect the table, and finally drops the table again
     */
    public function testDB()
    {
        if (!class_exists('PDO')) {
            echo "Skipping PDO_MySQL tests" . PHP_EOL;
            return;
        }
        $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
        $credentials = $credentials['mysql'];

        $con = ADONewConnection('pdo');
        $this->assertInternalType('object', $con, 'Could not get driver object');
        $this->assertEquals(false, $con->IsConnected());

        $con->Connect('mysql:host=localhost;dbname=adodb_test', $credentials['user'], $credentials['password']);
        $this->assertEquals(true, $con->IsConnected(), 'Could not connect');

        $info = $con->ServerInfo();
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('version', $info);

        $this->assertEquals(true, is_numeric($con->Time()), 'Could not get time');
        $this->assertEquals('DATE_FORMAT(NOW(),\'%Y-%m-%d\')', $con->SQLDate('Y-m-d'));
        $this->assertEquals('DATE_FORMAT(foo,\'%Y-%m-%d\')', $con->SQLDate('Y-m-d', 'foo'));

        $this->assertInternalType('array', $con->Prepare('foo'));
        $this->assertInternalType('array', $con->PrepareSP('foo'));

        $this->assertEquals("'foo'", $con->qstr('foo'));
        $this->assertEquals("'foo'", $con->Quote('foo'));
        $byRef = 'foo';
        $con->q($byRef);
        $this->assertEquals("'foo'", $byRef);
        $this->assertEquals('?', $con->Param('foo'));

        $con->Execute("DROP TABLE IF EXISTS test");

        $create = $con->Prepare("CREATE TABLE test (id INT NOT NULL AUTO_INCREMENT, val INT, PRIMARY KEY(id)) ENGINE InnoDB");
        $con->Execute($create);
        $insert = $con->Prepare("INSERT INTO test (val) VALUES (?)");
        $con->Execute($insert, array(1));
        $this->assertEquals(1, $con->Insert_ID());
        $con->Execute('UPDATE test SET val=2 WHERE id=1');
        $this->assertEquals(1, $con->Affected_Rows());
        $this->assertEquals('', $con->ErrorMsg());
        $this->assertEquals('HY000', $con->ErrorNo());
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
        $this->assertEquals(0, $con->GenID());
        $this->assertEquals(0, $con->GenID());
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
        $this->assertEquals("CONCAT(a,b)", $con->Concat('a', 'b'));

        $this->assertEquals(false, $con->MetaDatabases());
        $this->assertEquals(array('test'), $con->MetaTables());
        $cols = $con->MetaColumns('test');
        $this->assertEquals(true, $cols['ID']->auto_increment);
        $this->assertEquals(true, $cols['ID']->primary_key);
        $this->assertEquals(true, $cols['ID']->not_null);
        $this->assertEquals(false, $cols['VAL']->auto_increment);
        $this->assertEquals(false, $cols['VAL']->primary_key);
        $this->assertEquals(false, $cols['VAL']->not_null);
        $this->assertEquals('int', $cols['ID']->type);
        $this->assertEquals('id', $cols['ID']->name);
        $this->assertEquals(false, $con->MetaIndexes('test'));
        $this->assertEquals(array('ID'=>'id', 'VAL'=>'val'), $con->MetaColumnNames('test'));

        $con->Execute("DROP TABLE IF EXISTS test");
        $this->assertEquals(true, $con->Close());
    }

    /**
     * This is the existing ADOdb test "test-active-record.php"
     * translated to use PHPUnit assertions
     */
    public function testActiveRecord()
    {
        if (!class_exists('Person')) {
            include(__DIR__ . '/ActiveRecordClasses.php');
        }
        $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
        $credentials = $credentials['mysql'];
        $db = ADONewConnection('pdo');
        $db->Connect('mysql:host=localhost;dbname=adodb_test', $credentials['user'], $credentials['password']);
        ADOdb_Active_Record::SetDatabaseAdapter($db);

        $db->Execute("CREATE TABLE `persons` (
                        `id` int(10) unsigned NOT NULL auto_increment,
                        `name_first` varchar(100) NOT NULL default '',
                        `name_last` varchar(100) NOT NULL default '',
                        `favorite_color` varchar(100) NOT NULL default '',
                        PRIMARY KEY  (`id`)
                    ) ENGINE=MyISAM;
                   ");

        $db->Execute("CREATE TABLE `children` (
                        `id` int(10) unsigned NOT NULL auto_increment,
                        `person_id` int(10) unsigned NOT NULL,
                        `name_first` varchar(100) NOT NULL default '',
                        `name_last` varchar(100) NOT NULL default '',
                        `favorite_pet` varchar(100) NOT NULL default '',
                        PRIMARY KEY  (`id`)
                    ) ENGINE=MyISAM;
                   ");

        $person = new Person('persons');
        ADOdb_Active_Record::$_quoteNames = '111';
        $this->assertEquals(array('id', 'name_first', 'name_last', 'favorite_color'), $person->getAttributeNames());

        $person = new Person('persons');
        $person->name_first = 'Andi';
        $person->name_last  = 'Gutmans';
        $this->assertEquals(false, $person->save()); // this save() will fail on INSERT as favorite_color is a must fill...

        $person = new Person('persons');
        $person->name_first     = 'Andi';
        $person->name_last      = 'Gutmans';
        $person->favorite_color = 'blue';
        $this->assertEquals(true, $person->save()); // this save will perform an INSERT successfully

        $person->favorite_color = 'red';
        $this->assertEquals(1, $person->save()); // this save() will perform an UPDATE

        $person = new Person('persons');
        $person->name_first     = 'John';
        $person->name_last      = 'Lim';
        $person->favorite_color = 'lavender';
        $this->assertEquals(true, $person->save()); // this save will perform an INSERT successfully

        $person2 = new Person('persons');
        $person2->Load('id=2');
        $activeArr = $db->GetActiveRecordsClass($class = "Person",$table = "persons","id=".$db->Param(0),array(2));
        $person2 = $activeArr[0];
        $this->assertEquals('John', $person2->name_first);
        $this->assertEquals('Person', get_class($person2));

        $db->Execute("insert into children (person_id,name_first,name_last) values (2,'Jill','Lim')");
        $db->Execute("insert into children (person_id,name_first,name_last) values (2,'Joan','Lim')");
        $db->Execute("insert into children (person_id,name_first,name_last) values (2,'JAMIE','Lim')");

        $newperson2 = new Person();
        $person2->HasMany('children','person_id');
        $person2->Load('id=2');
        $person2->name_last='green';
        $c = $person2->children;
        $person2->save();
        $this->assertInternalType('array', $c);
        $this->assertEquals(3, sizeof($c));
        $this->assertEquals('Jill', $c[0]->name_first);
        $this->assertEquals('Joan', $c[1]->name_first);
        $this->assertEquals('JAMIE', $c[2]->name_first);

        $ch = new Child('children',array('id'));
        $ch->BelongsTo('person','person_id','id');
        $ch->Load('id=1');
        $this->assertEquals('Jill', $ch->name_first);

        $p = $ch->person;
        $this->assertEquals('John', $p->name_first);

        $p->hasMany('children','person_id');
        $p->LoadRelations('children', "	Name_first like 'J%' order by id",1,2);
        $this->assertEquals(2, sizeof($p->children));
        $this->assertEquals('JAMIE', $p->children[1]->name_first);

        $db->Execute('DROP TABLE persons');
        $db->Execute('DROP TABLE children');
    }
}

