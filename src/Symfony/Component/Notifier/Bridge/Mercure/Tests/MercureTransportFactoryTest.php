<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mercure\Tests;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Notifier\Bridge\Mercure\MercureTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class MercureTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): MercureTransportFactory
    {
        $hub = $this->createMock(HubInterface::class);
        $hubRegistry = new HubRegistry($hub, ['hubId' => $hub]);

        return new MercureTransportFactory($hubRegistry);
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'mercure://hubId?topic=topic'];
        yield [false, 'somethingElse://hubId?topic=topic'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'mercure://hubId?topic=%2Ftopic%2F1',
            'mercure://hubId?topic=/topic/1',
        ];

        yield [
            'mercure://hubId?topic%5B0%5D=%2Ftopic%2F1&topic%5B1%5D=%2Ftopic%2F2',
            'mercure://hubId?topic[]=/topic/1&topic[]=/topic/2',
        ];

        yield [
            'mercure://hubId?topic=https%3A%2F%2Fsymfony.com%2Fnotifier',
            'mercure://hubId',
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://hubId?topic=topic'];
    }

    public function testNotFoundHubThrows()
    {
        $hub = $this->createMock(HubInterface::class);
        $hubRegistry = new HubRegistry($hub, ['hubId' => $hub, 'anotherHubId' => $hub]);
        $factory = new MercureTransportFactory($hubRegistry);

        $this->expectException(IncompleteDsnException::class);
        $this->expectExceptionMessage('Hub "wrongHubId" not found. Did you mean one of: "hubId", "anotherHubId"?');
        $factory->create(new Dsn('mercure://wrongHubId'));
    }
}
