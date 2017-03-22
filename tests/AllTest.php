<?php

class AllTest extends PHPUnit_Framework_TestCase
{
    public function testAll()
    {
        foreach (array('mysqli', 'pdo_mysql', 'postgres9', 'pdo_pgsql', 'sqlite3', 'pdo_sqlite') as $driver) {
            $con = $this->getConnection($driver);
            if ($con === false) {
                echo "No {$driver} connection; skipping those tests" . PHP_EOL;
                continue;
            }

            // generic tests go here

            // get and getcache methods without an underlying table
            $this->assertEquals("1", $con->GetOne('SELECT 1 AS id'));
            $this->assertEquals("1", $con->CacheGetOne(5, 'SELECT 1 AS id'));
            $this->assertEquals(array(0=>1), $con->GetCol('SELECT 1 AS id'));
            $this->assertEquals(array(0=>1), $con->CacheGetCol(5, 'SELECT 1 AS id'));
            $this->assertEquals(array(0=>array(0=>1,'id'=>1)), $con->GetArray('SELECT 1 AS id'));
            $this->assertEquals(array(0=>array(0=>1,'id'=>1)), $con->CacheGetArray('SELECT 1 AS id'));
            $this->assertEquals(array(0=>1,'id'=>1), $con->GetRow('SELECT 1 AS id'));
            $this->assertEquals(array(0=>1,'id'=>1), $con->CacheGetRow(5, 'SELECT 1 AS id'));

            $info = $con->ServerInfo();
            $this->assertArrayHasKey('description', $info);
            $this->assertArrayHasKey('version', $info);

            $this->assertEquals(true, is_numeric($con->Time()), 'Could not get time');

            // sequence methods
            if ($driver != 'pdo_mysql') {
                $this->assertNotEquals(false, $con->CreateSequence());
                $this->assertEquals(1, $con->GenID(), $driver);
                $this->assertEquals(2, $con->GenID());
                $this->assertNotEquals(false, $con->DropSequence());
            }
        }
    }

    private function getConnection($type)
    {
        switch ($type) {
            case 'mysqli':
                if (!function_exists('mysqli_connect')) {
                    return false;
                }
                $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
                $credentials = $credentials['mysql'];
                $con = ADONewConnection('mysqli');
                $con->Connect('localhost', $credentials['user'], $credentials['password'], 'adodb_test');
                return $con->IsConnected() ? $con : false;

            case 'pdo_mysql':
                if (!class_exists('pdo')) {
                    return false;
                }
                $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
                $credentials = $credentials['mysql'];
                $con = ADONewConnection('pdo');
                $con->Connect('mysql:host=localhost;dbname=adodb_test', $credentials['user'], $credentials['password']);
                return $con->IsConnected() ? $con : false;

            case 'postgres9':
                if (!function_exists('pg_connect')) {
                    return false;
                }
                $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
                $credentials = $credentials['postgres'];
                $con = ADONewConnection('postgres9');
                $con->Connect('localhost', $credentials['user'], $credentials['password'], 'adodb_test');
                return $con->IsConnected() ? $con : false;

            case 'pdo_pgsql':
                if (!class_exists('pdo')) {
                    return false;
                }
                $credentials = json_decode(file_get_contents(__DIR__ . '/credentials.json'), true);
                $credentials = $credentials['postgres'];
                $con = ADONewConnection('pdo');
                $con->Connect('pgsql:host=localhost;dbname=adodb_test', $credentials['user'], $credentials['password']);
                return $con->IsConnected() ? $con : false;

            case 'sqlite3':
                if (!class_exists('SQLite3')) {
                    return false;
                }
                $db_file = tempnam(sys_get_temp_dir(), 'sql') . '.db';
                $con = ADONewConnection('sqlite3');
                $con->Connect($db_file, '', '', '');
                return $con->IsConnected() ? $con : false;

            case 'pdo_sqlite':
                if (!class_exists('pdo')) {
                    return false;
                }
                $db_file = tempnam(sys_get_temp_dir(), 'sql') . '.db';
                $con = ADONewConnection('pdo');
                $con->Connect('sqlite:' . $db_file, '', '', '');
                return $con->IsConnected() ? $con : false;
        }

        return false;
    }
}

