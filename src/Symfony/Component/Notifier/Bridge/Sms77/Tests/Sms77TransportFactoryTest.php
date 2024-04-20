<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sms77\Tests;

use Symfony\Component\Notifier\Bridge\Sms77\Sms77TransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class Sms77TransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): Sms77TransportFactory
    {
        return new Sms77TransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'sms77://host.test',
            'sms77://apiKey@host.test',
        ];

        yield [
            'sms77://host.test?from=TEST',
            'sms77://apiKey@host.test?from=TEST',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing api key' => ['sms77://host?from=TEST'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'sms77://apiKey@default?from=TEST'];
        yield [false, 'somethingElse://apiKey@default?from=TEST'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey@default?from=FROM'];
    }
}
