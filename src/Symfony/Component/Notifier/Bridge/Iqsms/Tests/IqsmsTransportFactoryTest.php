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
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class IqsmsTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return IqsmsTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new IqsmsTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'iqsms://host.test?from=FROM',
            'iqsms://login:password@host.test?from=FROM',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'iqsms://login:password@default?from=FROM'];
        yield [false, 'somethingElse://login:password@default?from=FROM'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing login' => ['iqsms://:password@host.test?from=FROM'];
        yield 'missing password' => ['iqsms://login:@host.test?from=FROM'];
        yield 'missing credentials' => ['iqsms://@host.test?from=FROM'];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['iqsms://login:password@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://login:password@default?from=FROM'];
        yield ['somethingElse://login:password@default']; // missing "from" option
    }
}
