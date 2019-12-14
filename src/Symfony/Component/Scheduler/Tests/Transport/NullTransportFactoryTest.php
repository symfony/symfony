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
use Symfony\Component\Scheduler\Transport\NullTransport;
use Symfony\Component\Scheduler\Transport\NullTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $factory = new NullTransportFactory();

        static::assertFalse($factory->support('test://', []));
        static::assertTrue($factory->support('null://', []));
    }

    public function testFactoryReturnTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $factory = new NullTransportFactory();
        static::assertInstanceOf(NullTransport::class, $factory->createTransport(Dsn::fromString('null://test'), [], $serializer));
    }
}
