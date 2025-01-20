<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @requires extension sqlite3
 */
class SqliteCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testSqlite3Result()
    {
        $db = new \SQLite3(':memory:');
        $db->exec('CREATE TABLE foo (id INTEGER PRIMARY KEY, bar TEXT)');
        $db->exec('INSERT INTO foo (bar) VALUES ("baz")');
        $stmt = $db->prepare('SELECT id, bar FROM foo');
        $result = $stmt->execute();

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
SQLite3Result {
  columnNames: array:2 [
    0 => "id"
    1 => "bar"
  ]
}
EODUMP, $result);
    }
}
