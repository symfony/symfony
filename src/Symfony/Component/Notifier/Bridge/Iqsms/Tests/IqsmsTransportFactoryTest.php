<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Iqsms\Tests;

use Symfony\Component\Notifier\Bridge\Iqsms\IqsmsTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class IqsmsTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): IqsmsTransportFactory
    {
        return new IqsmsTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'iqsms://host.test?from=FROM',
            'iqsms://login:password@host.test?from=FROM',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'iqsms://login:password@default?from=FROM'];
        yield [false, 'somethingElse://login:password@default?from=FROM'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing login' => ['iqsms://:password@host.test?from=FROM'];
        yield 'missing password' => ['iqsms://login:@host.test?from=FROM'];
        yield 'missing credentials' => ['iqsms://@host.test?from=FROM'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['iqsms://login:password@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://login:password@default?from=FROM'];
        yield ['somethingElse://login:password@default']; // missing "from" option
    }
}
