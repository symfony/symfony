<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsc\Tests;

use Symfony\Component\Notifier\Bridge\Smsc\SmscTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class SmscTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): SmscTransportFactory
    {
        return new SmscTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'smsc://host.test?from=MyApp',
            'smsc://login:password@host.test?from=MyApp',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'smsc://login:password@default?from=MyApp'];
        yield [false, 'somethingElse://login:password@default?from=MyApp'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['smsc://login:password@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://login:password@default?from=MyApp'];
        yield ['somethingElse://login:password@default']; // missing "from" option
    }
}
