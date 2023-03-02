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

use Symfony\Component\Notifier\Bridge\Telnyx\TelnyxTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class TelnyxTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): TelnyxTransportFactory
    {
        return new TelnyxTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'telnyx://host.test?from=+0611223344',
            'telnyx://api_key@host.test?from=%2B0611223344',
        ];

        yield [
            'telnyx://host.test?from=+0611223344&messaging_profile_id=messaging_profile_id',
            'telnyx://api_key@host.test?from=%2B0611223344&messaging_profile_id=messaging_profile_id',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'telnyx://api_key@default?from=%2B0611223344'];
        yield [false, 'somethingElse://api_key@default?from=0611223344'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['telnyx://api_key@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://api_key@default?from=+0611223344'];
        yield ['somethingElse://api_key@default']; // missing "from" option
    }
}
