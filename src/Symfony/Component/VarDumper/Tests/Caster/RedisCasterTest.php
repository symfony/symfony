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
 * @author Nicolas Grekas <p@tchwork.com>
 * @requires extension redis
 */
class RedisCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testNotConnected()
    {
        $redis = new \Redis();

        $xCast = <<<'EODUMP'
Redis {
  isConnected: false
}
EODUMP;

        $this->assertDumpMatchesFormat($xCast, $redis);
    }

    public function testConnected()
    {
        $redis = new \Redis();
        if (!@$redis->connect('127.0.0.1')) {
            $e = error_get_last();
            self::markTestSkipped($e['message']);
        }

        $xCast = <<<'EODUMP'
Redis {%A
  isConnected: true
  host: "127.0.0.1"
  port: 6379
  auth: null
  mode: ATOMIC
  dbNum: 0
  timeout: 0.0
  lastError: null
  persistentId: null
  options: {
    TCP_KEEPALIVE: 0
    READ_TIMEOUT: 0.0
    COMPRESSION: NONE
    SERIALIZER: NONE
    PREFIX: null
    SCAN: NORETRY
  }
}
EODUMP;

        $this->assertDumpMatchesFormat($xCast, $redis);
    }
}
