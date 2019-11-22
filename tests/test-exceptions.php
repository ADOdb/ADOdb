<?php

require_once('../adodb-exceptions.inc.php');

use ADODB\Exception;

function mockCallFunction_adodb_throw()
{
    $connection = new class extends ADOConnection
    {
        public function __construct()
        {
            $this->host = 'host';
        }
    };
    
    adodb_throw('dbms', 'fn', 0, 'errmsg', 'p1', 'p2', $connection);
}

try {
    mockCallFunction_adodb_throw();
} catch (ADODB_EXCEPTION $exception) {
    echo 'The right exception was caught.';
} catch (\Exception $exception) {
    echo 'There was a problem.' . $exception->getMessage;
}

try {
    mockCallFunction_adodb_throw();
} catch (Exception $exception) {
    echo 'The right exception was caught.';
} catch (\Exception $exception) {
    echo 'There was a problem.' . $exception->getMessage;
}
