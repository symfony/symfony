<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Transport\LocalTransport;
use Symfony\Component\Scheduler\Transport\LocalTransportFactory;
use Symfony\Component\Scheduler\Transport\NullTransport;
use Symfony\Component\Scheduler\Transport\NullTransportFactory;
use Symfony\Component\Scheduler\Transport\TransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TransportFactoryTest extends TestCase
{
    public function testTransportCanBeCreated(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $localTransport = new LocalTransportFactory();
        $nullTransport = new NullTransportFactory();
        $factory = new TransportFactory([$localTransport, $nullTransport]);

        $transport = $factory->createTransport('local://root?execution_mode=first_in_first_out', [], $serializer);
        static::assertInstanceOf(LocalTransport::class, $transport);

        $transport = $factory->createTransport('null://test', [], $serializer);
        static::assertInstanceOf(NullTransport::class, $transport);
    }
}
