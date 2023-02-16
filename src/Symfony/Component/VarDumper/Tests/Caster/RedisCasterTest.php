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
 *
 * @requires extension redis
 *
 * @group integration
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
        $redisHost = explode(':', getenv('REDIS_HOST')) + [1 => 6379];
        $redis = new \Redis();
        try {
            $redis->connect(...$redisHost);
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }

        $xCast = <<<EODUMP
Redis {%A
  isConnected: true
  host: "{$redisHost[0]}"
  port: {$redisHost[1]}
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
