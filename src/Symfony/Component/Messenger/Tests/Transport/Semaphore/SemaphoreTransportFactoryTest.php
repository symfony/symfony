<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Semaphore;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\Semaphore\Connection;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreTransport;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class SemaphoreTransportFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (false === \extension_loaded('sysvmsg')) {
            $this->markTestSkipped('Semaphore extension (sysvmsg) is required.');
        }
    }

    public function testSupportsOnlySemaphoreTransports()
    {
        $factory = new SemaphoreTransportFactory();

        $this->assertTrue($factory->supports('semaphore://localhost', []));
        $this->assertFalse($factory->supports('sqs://localhost', []));
        $this->assertFalse($factory->supports('invalid-dsn', []));
    }

    public function testItCreatesTheTransport()
    {
        $factory = new SemaphoreTransportFactory();
        $serializer = $this->createMock(SerializerInterface::class);

        $expectedTransport = new SemaphoreTransport(Connection::fromDsn('semaphore:///.env', ['foo' => 'bar']), $serializer);

        $this->assertEquals($expectedTransport, $factory->createTransport('semaphore:///.env', ['foo' => 'bar'], $serializer));
    }
}
