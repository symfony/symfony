<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sendberry\Tests;

use Symfony\Component\Notifier\Bridge\Sendberry\SendberryTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class SendberryTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): SendberryTransportFactory
    {
        return new SendberryTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'sendberry://host.test?from=+0611223344',
            'sendberry://user:password@host.test?auth_key=auth_key&from=%2B0611223344',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'sendberry://api_key@default?from=%2B0611223344'];
        yield [false, 'somethingElse://api_key@default?from=0611223344'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: auth_key' => ['sendberry://username:password@default?from=from'];
        yield 'missing option: from' => ['sendberry://username:password@default?auth_key=auth_key'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://api_key@default?from=+0611223344'];
        yield ['somethingElse://api_key@default']; // missing "from" option
    }
}
