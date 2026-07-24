<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Traits;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Traits\RedisTrait;

#[RequiresPhpExtension('redis')]
class RedisTraitTcpKeepaliveTest extends TestCase
{
    // RedisArray connects lazily, so the tcp_keepalive setOption() runs without a server.
    public function testCreateConnectionForRedisArrayWithTcpKeepalive()
    {
        $mock = new class {
            use RedisTrait;
        };

        $connection = $mock::createConnection('redis:?host[127.0.0.1:6379]&host[127.0.0.1:6380]', ['tcp_keepalive' => 60]);

        self::assertInstanceOf(\RedisArray::class, $connection);
    }
}
