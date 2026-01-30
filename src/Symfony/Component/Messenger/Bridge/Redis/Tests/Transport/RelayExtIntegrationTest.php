<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Redis\Tests\Transport;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Relay\Relay;

#[RequiresPhpExtension('relay')]
#[Group('time-sensitive')]
#[Group('integration')]
class RelayExtIntegrationTest extends RedisExtIntegrationTest
{
    protected function createRedisClient(): \Redis|Relay
    {
        return new Relay();
    }

    public function testConnectionSendAndGetDelayed(): void
    {
        self::markTestSkipped('This test doesn\'t work with relay.');
    }

    public function testConnectionSendDelayedMessagesWithSameContent(): void
    {
        self::markTestSkipped('This test doesn\'t work with relay.');
    }

    public function testLazy(): void
    {
        self::markTestSkipped('This test doesn\'t work with relay.');
    }

    public function testDbIndex(): void
    {
        self::markTestSkipped('This test doesn\'t work with relay.');
    }

    public function testGetNonBlocking(): void
    {
        self::markTestSkipped('This test doesn\'t work with relay.');
    }

    public function testGetAfterReject(): void
    {
        self::markTestSkipped('This test doesn\'t work with relay.');
    }

    public function testJsonError(): void
    {
        self::markTestSkipped('This test doesn\'t work with relay.');
    }
}
