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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Scheduler\Bridge\Kubernetes\Exception\InvalidOperationException;
use Symfony\Component\Scheduler\Bridge\Kubernetes\Transport\Connection;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConnectionTest extends TestCase
{
    public function testJobCannotBeCreated(): void
    {
    }

    public function testJobCanBeCreated(): void
    {
    }

    public function testJobsCannotBeListed(): void
    {
    }

    public function testJobsCanBeListed(): void
    {
    }

    public function testJobCannotBePaused(): void
    {
    }

    public function testJobCanBePaused(): void
    {
    }

    public function testJobCannotBeResumed(): void
    {
    }

    public function testJobCanBeResumed(): void
    {
    }

    public function testJobCannotBeUpdated(): void
    {
    }

    public function testJobCanBeUpdated(): void
    {
    }

    public function testJobsCannotBeDeleted(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'code' => 404,
                'message' => 'Resource not found',
            ]), ['http_code' => 404]),
        ]);

        $connection = new Connection(Dsn::fromString('k8s://user:password@localhost?namespace=test&scheme=http'), $serializer, $httpClient);

        static::expectException(InvalidOperationException::class);
        $connection->empty();
    }

    public function testJobsCanBeDeleted(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'code' => 200,
                'message' => 'OK',
            ]), ['http_code' => 200]),
        ]);

        $connection = new Connection(Dsn::fromString('k8s://user:password@localhost?namespace=test&scheme=http'), $serializer, $httpClient);

        $connection->empty();
        static::assertSame(1, $httpClient->getRequestsCount());
    }

    public function testSpecificJobCannotBeDeletedWithUndefinedResource(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'code' => 404,
                'message' => 'Resource not found',
            ]), ['http_code' => 404]),
        ]);

        $connection = new Connection(Dsn::fromString('k8s://user:password@localhost?namespace=test&scheme=http'), $serializer, $httpClient);

        static::expectException(InvalidOperationException::class);
        $connection->delete('foo');
    }

    public function testSpecificJobCanBeDeletedWithValidResource(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'code' => 200,
                'message' => 'OK',
            ]), ['http_code' => 200]),
        ]);

        $connection = new Connection(Dsn::fromString('k8s://user:password@localhost?namespace=test&scheme=http'), $serializer, $httpClient);

        $connection->delete('foo');
        static::assertSame(1, $httpClient->getRequestsCount());
    }

    public function testSpecificJobCanBeAcceptedForDeletion(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'code' => 202,
                'message' => 'Accepted',
            ]), ['http_code' => 202]),
        ]);

        $connection = new Connection(Dsn::fromString('k8s://user:password@localhost?namespace=test&scheme=http'), $serializer, $httpClient);

        $connection->delete('foo');
        static::assertSame(1, $httpClient->getRequestsCount());
    }
}
