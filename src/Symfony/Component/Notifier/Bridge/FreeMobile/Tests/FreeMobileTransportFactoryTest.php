<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FreeMobile\Tests;

use Symfony\Component\Notifier\Bridge\FreeMobile\FreeMobileTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class FreeMobileTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): FreeMobileTransportFactory
    {
        return new FreeMobileTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'freemobile://host.test?phone=0611223344',
            'freemobile://login:pass@host.test?phone=0611223344',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'freemobile://login:pass@default?phone=0611223344'];
        yield [false, 'somethingElse://login:pass@default?phone=0611223344'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: phone' => ['freemobile://login:pass@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://login:pass@default?phone=0611223344'];
        yield ['somethingElse://login:pass@default']; // missing "phone" option
    }
}
