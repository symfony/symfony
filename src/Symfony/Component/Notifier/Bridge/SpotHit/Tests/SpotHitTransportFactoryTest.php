<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SpotHit\Tests;

use Symfony\Component\Notifier\Bridge\SpotHit\SpotHitTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class SpotHitTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return SpotHitTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new SpotHitTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'spothit://spot-hit.fr',
            'spothit://api_token@default',
        ];
        yield [
            'spothit://spot-hit.fr?from=MyCompany',
            'spothit://api_token@default?from=MyCompany',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'spothit://api_token@default?from=MyCompany'];
        yield [false, 'somethingElse://api_token@default?from=MyCompany'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['foobar://api_token@default?from=MyCompany'];
        yield ['foobar://api_token@default'];
    }
}
