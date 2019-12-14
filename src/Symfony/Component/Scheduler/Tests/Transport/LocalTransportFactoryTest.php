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
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\LocalTransport;
use Symfony\Component\Scheduler\Transport\LocalTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LocalTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $factory = new LocalTransportFactory();

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('local://'));
    }

    public function testFactoryReturnTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $factory = new LocalTransportFactory();

        static::assertInstanceOf(
            LocalTransport::class,
            $factory->createTransport(Dsn::fromString('local://root?execution_mode=fifo'), [], $serializer)
        );
    }
}
