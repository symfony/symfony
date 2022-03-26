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
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

/**
 * @author Daniel GORGAN <https://github.com/danut007ro>
 */
final class EngagespotTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return EngagespotTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new EngagespotTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'engagespot://api.engagespot.co/2/campaigns?campaign_name=TEST',
            'engagespot://apiKey@default?campaign_name=TEST',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'engagespot://apiKey@default'];
        yield [false, 'somethingElse://username:password@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://username:password@default'];
    }
}
