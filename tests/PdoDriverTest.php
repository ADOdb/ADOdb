<?php
/**
 * Tests cases for adodb-lib.inc.php
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @package ADOdb
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v2.1 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-2.1-or-later
 *
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
 */

use PHPUnit\Framework\TestCase;

require_once dirname(__FILE__) . '/../drivers/adodb-pdo.inc.php';

/**
 * Class PdoDriverTest.
 *
 * Test cases for drivers/adodb-pdo.inc.php
 */
class PdoDriverTest extends TestCase
{
    /**
     * Test for {@see ADODB_pdo#containsQuestionMarkPlaceholder)
     *
     * @dataProvider providerContainsQuestionMarkPlaceholder
     */
    public function testContainsQuestionMarkPlaceholder($result, $sql): void
    {
        $method = new ReflectionMethod('ADODB_pdo', 'containsQuestionMarkPlaceholder');
        $method->setAccessible(true);

        $pdoDriver = new ADODB_pdo();
        $this->assertSame($result, $method->invoke($pdoDriver, $sql));
    }

    /**
     * Data provider for {@see testContainsQuestionMarkPlaceholder()}
     *
     * @return array [result, SQL statement]
     */
    public function providerContainsQuestionMarkPlaceholder(): array
    {
        return [
            [true, 'SELECT * FROM employees WHERE emp_no = ?;'],
            [true, 'SELECT * FROM employees WHERE emp_no = ?'],
            [true, 'SELECT * FROM employees WHERE emp_no=?'],
            [true, 'SELECT * FROM employees WHERE emp_no>?'],
            [true, 'SELECT * FROM employees WHERE emp_no<?'],
            [true, 'SELECT * FROM employees WHERE emp_no>=?'],
            [true, 'SELECT * FROM employees WHERE emp_no<=?'],
            [true, 'SELECT * FROM employees WHERE emp_no<>?'],
            [true, 'SELECT * FROM employees WHERE emp_no!=?'],
            [true, 'SELECT * FROM employees WHERE emp_no IN (?)'],
            [true, 'SELECT * FROM employees WHERE emp_no=`?` OR emp_no=?'],
            [true, 'UPDATE employees SET emp_name=? WHERE emp_no=?'],

            [false, 'SELECT * FROM employees'],
            [false, 'SELECT * FROM employees WHERE emp_no=`?`'],
            [false, 'SELECT * FROM employees WHERE emp_no=??'],
            [false, 'SELECT * FROM employees WHERE emp_no=:emp_no'],
        ];
    }

    /**
     * Test for {@see ADODB_pdo#conformToBindParameterStyle)
     *
     * @dataProvider providerConformToBindParameterStyle
     */
    public function testConformToBindParameterStyle($expected, $inputarr, $bindParameterStyle, $sql): void
    {
        $method = new ReflectionMethod('ADODB_pdo', 'conformToBindParameterStyle');
        $method->setAccessible(true);

        $pdoDriver = new ADODB_pdo();
        $pdoDriver->bindParameterStyle = $bindParameterStyle;
        $this->assertSame($expected, $method->invoke($pdoDriver, $sql, $inputarr));
    }

    /**
     * Data provider for {@see testConformToBindParameterStyle()}
     *
     * @return array [expected, inputarr, bindParameterStyle, SQL statement]
     */
    public function providerConformToBindParameterStyle(): array
    {
        return [
            [
                [1, 2, 3],
                [1, 2, 3],
                ADODB_pdo::BIND_USE_QUESTION_MARKS,
                null
            ],
            [
                [1, 2, 3],
                ['a' => 1, 'b' => 2, 'c' => 3],
                ADODB_pdo::BIND_USE_QUESTION_MARKS,
                null
            ],
            [
                [1, 2, 3],
                [1, 2, 3],
                ADODB_pdo::BIND_USE_NAMED_PARAMETERS,
                null
            ],
            [
                ['a' => 1, 'b' => 2, 'c' => 3],
                ['a' => 1, 'b' => 2, 'c' => 3],
                ADODB_pdo::BIND_USE_NAMED_PARAMETERS,
                null
            ],
            [
                [1, 2, 3],
                [1, 2, 3],
                ADODB_pdo::BIND_USE_BOTH,
                'SELECT * FROM employees WHERE emp_no = ?'
            ],
            [
                [1, 2, 3],
                ['a' => 1, 'b' => 2, 'c' => 3],
                ADODB_pdo::BIND_USE_BOTH,
                'SELECT * FROM employees WHERE emp_no = ?'
            ],
            [
                [1, 2, 3],
                [1, 2, 3],
                ADODB_pdo::BIND_USE_BOTH,
                'SELECT * FROM employees WHERE emp_no = :id'
            ],
            [
                ['a' => 1, 'b' => 2, 'c' => 3],
                ['a' => 1, 'b' => 2, 'c' => 3],
                ADODB_pdo::BIND_USE_BOTH,
                'SELECT * FROM employees WHERE emp_no = :id'
            ],
            [
                [1, 2, 3],
                [1, 2, 3],
                9999,   // Incorrect values result in default behavior.
                null
            ],
            [
                [1, 2, 3],
                ['a' => 1, 'b' => 2, 'c' => 3],
                9999,   // Incorrect values result in default behavior.
                null
            ],
        ];
    }
}
