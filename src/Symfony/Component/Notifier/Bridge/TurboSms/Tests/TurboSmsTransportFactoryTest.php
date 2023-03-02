<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\TurboSms\Tests;

use Symfony\Component\Notifier\Bridge\TurboSms\TurboSmsTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class TurboSmsTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): TurboSmsTransportFactory
    {
        return new TurboSmsTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'turbosms://host.test?from=acme',
            'turbosms://accountSid:authToken@host.test?from=acme',
        ];

        yield [
            'turbosms://host.test?from=Symfony+Community',
            'turbosms://accountSid:authToken@host.test?from=Symfony Community',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'turbosms://authToken@default?from=acme'];
        yield [false, 'somethingElse://authToken@default?from=acme'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['turbosms://authToken@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://authToken@default?from=acme'];
        yield ['somethingElse://authToken@default'];
    }
}
