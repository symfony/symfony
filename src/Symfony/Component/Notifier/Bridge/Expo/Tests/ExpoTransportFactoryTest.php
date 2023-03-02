<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Expo\Tests;

use Symfony\Component\Notifier\Bridge\Expo\ExpoTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Imad ZAIRIG <https://github.com/zairigimad>
 */
final class ExpoTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): ExpoTransportFactory
    {
        return new ExpoTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'expo://exp.host/--/api/v2/push/send',
            'expo://default',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'expo://default?accessToken=test'];
        yield [false, 'somethingElse://username:password@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://username:password@default'];
    }
}
