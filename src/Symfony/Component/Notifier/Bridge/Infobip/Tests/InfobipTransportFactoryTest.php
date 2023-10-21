<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Infobip\Tests;

use Symfony\Component\Notifier\Bridge\Infobip\InfobipTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class InfobipTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): InfobipTransportFactory
    {
        return new InfobipTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'infobip://host.test?from=0611223344',
            'infobip://authtoken@host.test?from=0611223344',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'infobip://authtoken@default?from=0611223344'];
        yield [false, 'somethingElse://authtoken@default?from=0611223344'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['infobip://authtoken@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://authtoken@default?from=FROM'];
        yield ['somethingElse://authtoken@default']; // missing "from" option
    }
}
