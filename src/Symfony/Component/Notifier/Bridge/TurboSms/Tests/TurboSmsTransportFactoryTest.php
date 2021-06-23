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
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class TurboSmsTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return TurboSmsTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new TurboSmsTransportFactory();
    }

    public function createProvider(): iterable
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

    public function supportsProvider(): iterable
    {
        yield [true, 'turbosms://authToken@default?from=acme'];
        yield [false, 'somethingElse://authToken@default?from=acme'];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['turbosms://authToken@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://authToken@default?from=acme'];
        yield ['somethingElse://authToken@default'];
    }
}
