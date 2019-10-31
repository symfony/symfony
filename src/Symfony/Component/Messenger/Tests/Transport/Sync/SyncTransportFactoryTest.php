<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\AmqpExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\Sync\SyncTransportFactory;

class SyncTransportFactoryTest extends TestCase
{
    public function testCreateTransport()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);
        $factory = new SyncTransportFactory($bus);
        $transport = $factory->createTransport('sync://', [], $serializer);
        $this->assertInstanceOf(SyncTransport::class, $transport);
    }
}
