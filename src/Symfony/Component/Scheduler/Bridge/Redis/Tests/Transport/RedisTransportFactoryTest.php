<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Redis\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Scheduler\Bridge\Redis\Transport\RedisTransport;
use Symfony\Component\Scheduler\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 */
final class RedisTransportFactoryTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testTransportCanSupport(): void
    {
        $factory = new RedisTransportFactory();

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('redis://'));
    }

    public function testFactoryReturnTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $factory = new RedisTransportFactory();

        static::assertInstanceOf(RedisTransport::class, $factory->createTransport(Dsn::fromString('redis://localhost/tasks'), [], $serializer));
    }
}
