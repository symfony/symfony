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
use Symfony\Component\Scheduler\Transport\FileTransport;
use Symfony\Component\Scheduler\Transport\FileTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FileTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $factory = new FileTransportFactory();

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('file://'));
        static::assertTrue($factory->support('fs://'));
    }

    public function testFactoryReturnTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $factory = new FileTransportFactory();

        static::assertInstanceOf(
            FileTransport::class,
            $factory->createTransport(Dsn::fromString('file://root?execution_mode=fifo'), [], $serializer)
        );
        static::assertInstanceOf(
            FileTransport::class,
            $factory->createTransport(Dsn::fromString('fs://root?execution_mode=fifo'), [], $serializer)
        );
    }
}
