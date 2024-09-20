<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OrangeSms\Tests;

use Symfony\Component\Notifier\Bridge\OrangeSms\OrangeSmsTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class OrangeSmsTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): OrangeSmsTransportFactory
    {
        return new OrangeSmsTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'orange-sms://host.test?from=FROM&sender_name=SENDER_NAME',
            'orange-sms://CLIENT_ID:CLIENT_SECRET@host.test?from=FROM&sender_name=SENDER_NAME',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'orange-sms://CLIENT_ID:CLIENT_SECRET@default?from=FROM&sender_name=SENDER_NAME'];
        yield [false, 'somethingElse://CLIENT_ID:CLIENT_SECRET@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing credentials' => ['orange-sms://default?from=FROM&sender_name=SENDER_NAME'];
        yield 'missing CLIENT_ID' => ['orange-sms://:CLIENT_SECRET@default?from=FROM&sender_name=SENDER_NAME'];
        yield 'missing CLIENT_SECRET' => ['orange-sms://CLIENT_ID:@default?from=FROM&sender_name=SENDER_NAME'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['orange-sms://CLIENT_ID:CLIENT_SECRET@default?sender_name=SENDER_NAME'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://CLIENT_ID:CLIENT_SECRET@default?from=FROM&sender_name=SENDER_NAME'];
        yield ['somethingElse://CLIENT_ID:CLIENT_SECRET@host?sender_name=SENDER_NAME']; // missing "from" option
    }
}
