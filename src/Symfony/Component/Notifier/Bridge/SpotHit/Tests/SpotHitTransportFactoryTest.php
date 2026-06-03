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
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;

final class SpotHitTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): SpotHitTransportFactory
    {
        return new SpotHitTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'spothit://spot-hit.fr',
            'spothit://api_token@default',
        ];
        yield [
            'spothit://spot-hit.fr?from=MyCompany',
            'spothit://api_token@default?from=MyCompany',
        ];
        yield [
            'spothit://spot-hit.fr?from=MyCompany&smslong=1',
            'spothit://api_token@default?from=MyCompany&smslong=1',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'spothit://api_token@default?from=MyCompany'];
        yield [true, 'spothit://api_token@default?from=MyCompany&smslong=1'];
        yield [true, 'spothit://api_token@default?from=MyCompany&smslongnbr=1'];
        yield [false, 'somethingElse://api_token@default?from=MyCompany'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['foobar://api_token@default?from=MyCompany'];
        yield ['foobar://api_token@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['spothit://default?from=MyCompany'];
    }
}
