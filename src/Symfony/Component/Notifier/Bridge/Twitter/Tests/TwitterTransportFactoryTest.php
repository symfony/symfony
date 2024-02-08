<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twitter\Tests;

use Symfony\Component\Notifier\Bridge\Twitter\TwitterTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

class TwitterTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): TwitterTransportFactory
    {
        return new TwitterTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield ['twitter://host.test', 'twitter://A:B:C:D@host.test'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'twitter://default'];
        yield [false, 'somethingElse://default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['twitter://A:B@default', 'Invalid "twitter://default" notifier DSN: Access Token is missing'];
    }
}
