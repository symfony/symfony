<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Google\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Google\Task\JobFactory;
use Symfony\Component\Scheduler\Bridge\Google\Transport\GoogleTransport;
use Symfony\Component\Scheduler\Bridge\Google\Transport\GoogleTransportFactory;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GoogleTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $factory = new GoogleTransportFactory(new JobFactory());

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('google://'));
        static::assertTrue($factory->support('gcp://'));
    }

    public function testFactoryReturnTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        static::assertInstanceOf(
            GoogleTransport::class,
            (new GoogleTransportFactory(new JobFactory()))->createTransport(Dsn::fromString('google://test@europe-west1/?bearer=test&auth_key=test'), [], $serializer)
        );
        static::assertInstanceOf(
            GoogleTransport::class,
            (new GoogleTransportFactory(new JobFactory()))->createTransport(Dsn::fromString('gcp://test@europe-west1/?bearer=test&auth_key=test'), [], $serializer)
        );
    }
}
