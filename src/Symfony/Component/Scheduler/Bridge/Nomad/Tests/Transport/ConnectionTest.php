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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Scheduler\Bridge\Nomad\Transport\Connection;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConnectionTest extends TestCase
{
    public function testConnectionCannotStopJobWithInvalidToken(): void
    {
    }

    public function testConnectionCannotStopUndefinedJob(): void
    {
    }

    public function testConnectionCanStopJob(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('deserialize');

        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'EvalId' => 'd092fdc0-e1fd-2536-67d8-43af8ca798ac',
                'EvalCreateIndex' => 35,
                'JobModifyIndex' => 34,
            ]))
        ]);

        $connection = new Connection(Dsn::fromString('nomad://test@localhost:4646'), $client, $serializer);
        $connection->delete('foo');

        static::assertSame(1, $client->getRequestsCount());
    }
}
