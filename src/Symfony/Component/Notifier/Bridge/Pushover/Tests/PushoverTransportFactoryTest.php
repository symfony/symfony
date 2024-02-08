<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pushover\Tests;

use Symfony\Component\Notifier\Bridge\Pushover\PushoverTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class PushoverTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): PushoverTransportFactory
    {
        return new PushoverTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield ['pushover://api.pushover.net', 'pushover://userKey:appToken@api.pushover.net'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'pushover://userKey@appToken'];
        yield [false, 'somethingElse://userKey@appToken'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://userKey@appToken'];
    }
}
