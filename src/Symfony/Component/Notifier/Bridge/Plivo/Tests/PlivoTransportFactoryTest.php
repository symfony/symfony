<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Plivo\Tests;

use Symfony\Component\Notifier\Bridge\Plivo\PlivoTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class PlivoTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): PlivoTransportFactory
    {
        return new PlivoTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield ['plivo://host.test?from=0611223344', 'plivo://authId:authToken@host.test?from=0611223344'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing auth token' => ['plivo://authId@default?from=FROM'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['plivo://authId:authToken@default'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'plivo://authId:authToken@default?from=0611223344'];
        yield [false, 'somethingElse://authId:authToken@default?from=0611223344'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://authId:authToken@default?from=0611223344'];
        yield ['somethingElse://authId:authToken@default']; // missing "from" option
    }
}
