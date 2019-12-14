<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Kubernetes\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Kubernetes\Transport\KubernetesTransport;
use Symfony\Component\Scheduler\Bridge\Kubernetes\Transport\KubernetesTransportFactory;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class KubernetesTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $factory = new KubernetesTransportFactory();

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('kubernetes://'));
        static::assertTrue($factory->support('k8s://'));
    }

    public function testFactoryReturnTransport(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        static::assertInstanceOf(
            KubernetesTransport::class,
            (new KubernetesTransportFactory())->createTransport(Dsn::fromString('kubernetes://user:password@localhost'), [], $serializer)
        );
        static::assertInstanceOf(
            KubernetesTransport::class,
            (new KubernetesTransportFactory())->createTransport(Dsn::fromString('k8s://user:password@localhost'), [], $serializer)
        );
    }
}
