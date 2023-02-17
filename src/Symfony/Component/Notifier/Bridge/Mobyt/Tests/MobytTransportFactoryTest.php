<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mobyt\Tests;

use Symfony\Component\Notifier\Bridge\Mobyt\MobytTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MobytTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): MobytTransportFactory
    {
        return new MobytTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'mobyt://host.test?from=FROM&type_quality=LL',
            'mobyt://accountSid:authToken@host.test?from=FROM',
        ];

        yield [
            'mobyt://host.test?from=FROM&type_quality=N',
            'mobyt://accountSid:authToken@host.test?from=FROM&type_quality=N',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'mobyt://accountSid:authToken@host.test?from=FROM'];
        yield [false, 'somethingElse://accountSid:authToken@host.test?from=FROM'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['mobyt://host.test?from=FROM'];
        yield 'missing option: from' => ['mobyt://accountSid:authToken@host'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://accountSid:authToken@host.test?from=FROM'];
        yield ['somethingElse://accountSid:authToken@host.test']; // missing "from" option
    }
}
