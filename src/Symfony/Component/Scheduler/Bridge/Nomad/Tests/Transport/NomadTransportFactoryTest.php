<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Nomad\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Nomad\Transport\NomadTransportFactory;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NomadTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupport(): void
    {
        static::assertFalse((new NomadTransportFactory())->support('test://'));
        static::assertTrue((new NomadTransportFactory())->support('nomad://'));
    }

    public function testFactoryCanReturnTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new NomadTransportFactory();
        $transport = $factory->createTransport(Dsn::fromString('nomad://test@localhost:4646'), [], $serializer);

        static::assertInstanceOf(TransportInterface::class, $transport);
    }
}
