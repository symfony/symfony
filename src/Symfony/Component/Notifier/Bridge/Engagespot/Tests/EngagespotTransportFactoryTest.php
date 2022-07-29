<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Engagespot\Tests;

use Symfony\Component\Notifier\Bridge\Engagespot\EngagespotTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Daniel GORGAN <https://github.com/danut007ro>
 */
final class EngagespotTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): EngagespotTransportFactory
    {
        return new EngagespotTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'engagespot://api.engagespot.co/v3/notifications',
            'engagespot://apiKey:apiSecret@default',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'engagespot://apiKey:apiSecret@default'];
        yield [false, 'somethingElse://username:password@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://username:password@default'];
    }
}
