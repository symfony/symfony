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

use Symfony\Component\Messenger\Transport\Semaphore\Connection;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreTransport;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use PHPUnit\Framework\TestCase;

class SemaphoreTransportFactoryTest extends TestCase
{
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
