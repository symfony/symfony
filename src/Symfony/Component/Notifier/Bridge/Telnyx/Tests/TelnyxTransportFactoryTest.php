<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telnyx\Tests;

use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransportFactory;
use Symfony\Component\Notifier\Bridge\Telnyx\TelnyxTransportFactory;
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

/**
 * @author Mihail Krasilnikov <mihail.krasilnikov.j@gmail.com>
 */
final class TelnyxTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return SmsapiTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new TelnyxTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'telnyx://default.host?from=37162626262',
            'telnyx://apikey@default.host?from=37162626262',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'telnyx://apikey@default.host?from=37162626262'];
        yield [false, 'another://apikey@default.host?from=37162626262'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing api key' => ['telnyx://default.host?from=37162626262'];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['telnyx://apikey@default.host'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['another://apikey@default.host?from=37162626262'];
        yield ['another://apikey@default.host']; // missing "from" option
    }
}
