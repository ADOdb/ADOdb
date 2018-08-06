<?php

/**
 * @class MySQLiTest
 *
 * PHPUnit tests specific to the mysqli driver
 */
class MySQLiTest extends PHPUnit_Framework_TestCase
{
    /**
     * These tests verify methods that generate SQL snippet strings
     * return the expected results
     */
    public function testStateless()
    {
        if (!function_exists('mysqli_connect')) {
            echo "Skipping MySQLi tests" . PHP_EOL;
            return;
        }

        $con = ADONewConnection('mysqli');
        $this->assertInternalType('object', $con, 'Could not get driver object');
        $this->assertEquals(false, $con->IsConnected());

        $this->assertEquals('DATE_FORMAT(NOW(),\'%Y-%m-%d\')', $con->SQLDate('Y-m-d'));
        $this->assertEquals('DATE_FORMAT(foo,\'%Y-%m-%d\')', $con->SQLDate('Y-m-d', 'foo'));

        $this->assertEquals("'foo'", $con->qstr('foo'));
        $this->assertEquals("'foo'", $con->Quote('foo'));
        $byRef = 'foo';
        $con->q($byRef);
        $this->assertEquals("'foo'", $byRef);
        $this->assertEquals('?', $con->Param('foo'));

        $this->assertEquals(" IFNULL(id, 0) ", $con->IfNull('id', 0));
        $this->assertEquals("CONCAT(a,b)", $con->Concat('a', 'b'));

    }

    /**
     * This test creates a table, loads values into it, uses ADOdb
     * Meta* methods to inspect the table, and finally drops the table again
     */
    public function testStateful()
    {
        if (!function_exists('mysqli_connect')) {
            echo "Skipping MySQLi tests" . PHP_EOL;
            return;
        }
        $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
        $credentials = $credentials['mysql'];

        $con = ADONewConnection('mysqli');
        $con->Connect('localhost', $credentials['user'], $credentials['password'], 'adodb_test');
        $this->assertEquals(true, $con->IsConnected(), 'Could not connect');

        $info = $con->ServerInfo();
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('version', $info);

        $this->assertEquals(true, is_numeric($con->Time()), 'Could not get time');

        $this->assertEquals('foo', $con->Prepare('foo'));
        $this->assertEquals('foo', $con->PrepareSP('foo'));

        $con->Execute("DROP TABLE IF EXISTS test");

        $create = $con->Prepare("CREATE TABLE test (id INT NOT NULL AUTO_INCREMENT, val INT, PRIMARY KEY(id)) ENGINE InnoDB");
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

        $this->assertEquals(true, in_array('adodb_test', $con->MetaDatabases()));
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
        $this->assertEquals(array(), $con->MetaIndexes('test'));
        $this->assertEquals(array('ID'=>'id', 'VAL'=>'val'), $con->MetaColumnNames('test'));

        $con->Execute("DROP TABLE IF EXISTS test");
        $this->assertEquals(null, $con->Close());
    }

    /**
     * This is the existing ADOdb test "test-active-record.php"
     * translated to use PHPUnit assertions
     */
    public function testActiveRecord()
    {
        if (!function_exists('mysqli_connect')) {
            echo "Skipping MySQLi tests" . PHP_EOL;
            return;
        }
        if (!class_exists('Person')) {
            include(__DIR__ . '/ActiveRecordClasses.php');
        }
        $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
        $credentials = $credentials['mysql'];
        $db = ADONewConnection('mysqli');
        $db->Connect('localhost', $credentials['user'], $credentials['password'], 'adodb_test');
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

    /**
     * This is the existing ADOdb test "test-xmlschema.php"
     * translated to use PHPUnit assertions
     */
    public function testXmlSchema()
    {
        if (!function_exists('mysqli_connect')) {
            echo "Skipping MySQLi tests" . PHP_EOL;
            return;
        }
        $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
        $credentials = $credentials['mysql'];
        $db = ADONewConnection('mysqli');
        $db->Connect('localhost', $credentials['user'], $credentials['password'], 'adodb_test');
        $schema = new adoSchema($db);
        $sql = $schema->ParseSchema(__DIR__ . '/xmlschema.xml');
        $this->assertEquals(2, $schema->ExecuteSchema($sql));
        $this->assertEquals(null, $schema->Destroy());
        $db->Execute('DROP TABLE mytable');
    }

    /**
     * This is the existing ADOdb test "test-datadict.php"
     * translated to use PHPUnit assertions
     */
    public function testDataDict()
    {
        if (!function_exists('mysqli_connect')) {
            echo "Skipping MySQLi tests" . PHP_EOL;
            return;
        }
        $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
        $credentials = $credentials['mysql'];
        $db = ADONewConnection('mysqli');
        $dict = NewDataDictionary($db);

        $opts = array('REPLACE','mysql' => 'ENGINE=INNODB', 'oci8' => 'TABLESPACE USERS');
        $flds = "
        ID            I           AUTO KEY,
        FIRSTNAME     VARCHAR(30) DEFAULT 'Joan' INDEX idx_name,
        LASTNAME      VARCHAR(28) DEFAULT 'Chen' key INDEX idx_name INDEX idx_lastname,
        averylonglongfieldname X(1024) DEFAULT 'test',
        price         N(7.2)  DEFAULT '0.00',
        MYDATE        D      DEFDATE INDEX idx_date,
        BIGFELLOW     X      NOTNULL,
        TS_SECS            T      DEFTIMESTAMP,
        TS_SUBSEC   TS DEFTIMESTAMP
        ";

        $this->assertEquals(array('CREATE DATABASE adodb_test'), $dict->CreateDatabase('adodb_test'));
        $dict->SetSchema('adodb_test');
        $create = $dict->CreateTableSQL('testtable', $flds, $opts);
        // getting whitespace aligned to compate the actual create table
        // is a pain in the neck
        $this->assertEquals(5, count($create));
        $this->assertEquals(
            array("ALTER TABLE adodb_test.testtable ADD  FULLTEXT INDEX idx  (price, firstname, lastname)"),
            $dict->CreateIndexSQL('idx','testtable','price,firstname,lastname',array('BITMAP','FULLTEXT','CLUSTERED','HASH'))
        );
        $addflds = array(array('height', 'F'),array('weight','F'));
        $this->assertEquals(
            array("ALTER TABLE adodb_test.testtable ADD height DOUBLE", "ALTER TABLE adodb_test.testtable ADD weight DOUBLE"),
            $dict->AddColumnSQL('testtable', $addflds)
        );
        $addflds = array(array('height', 'F','NOTNULL'),array('weight','F','NOTNULL'));
        $this->assertEquals(
            array("ALTER TABLE adodb_test.testtable MODIFY COLUMN height DOUBLE NOT NULL", 
                  "ALTER TABLE adodb_test.testtable MODIFY COLUMN weight DOUBLE NOT NULL"),
            $dict->AlterColumnSQL('testtable', $addflds)
        );
    }
}

