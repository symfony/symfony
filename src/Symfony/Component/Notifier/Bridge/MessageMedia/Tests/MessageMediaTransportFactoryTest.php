<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageMedia\Tests;

use Symfony\Component\Notifier\Bridge\MessageMedia\MessageMediaTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class MessageMediaTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): MessageMediaTransportFactory
    {
        return new MessageMediaTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'messagemedia://host.test',
            'messagemedia://apiKey:apiSecret@host.test',
        ];

        yield [
            'messagemedia://host.test?from=TEST',
            'messagemedia://apiKey:apiSecret@host.test?from=TEST',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'messagemedia://apiKey:apiSecret@default'];
        yield [false, 'somethingElse://apiKey:apiSecret@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey:apiSecret@default'];
    }
}
