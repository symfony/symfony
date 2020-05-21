<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Dkron\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Dkron\Transport\DkronTransportFactory;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DkronTransportFactoryTest extends TestCase
{
    public function testWrongTransportCannotBeSupported(): void
    {
        static::assertFalse((new DkronTransportFactory())->support('test://'));
    }

    public function testValidTransportCanBeSupported(): void
    {
        static::assertTrue((new DkronTransportFactory())->support('dkron://'));
    }

    public function testTransportIsReturned(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DkronTransportFactory();

        static::assertInstanceOf(TransportInterface::class, $factory->createTransport(Dsn::fromString('dkron://root@127.0.0.1:8080'), [], $serializer));
    }
}
