<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mailjet\Tests;

use Symfony\Component\Notifier\Bridge\Mailjet\MailjetTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class MailjetTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): MailjetTransportFactory
    {
        return new MailjetTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'mailjet://Mailjet@host.test',
            'mailjet://Mailjet:authtoken@host.test',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'mailjet://Mailjet:authtoken@default'];
        yield [false, 'somethingElse://Mailjet:authtoken@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing from' => ['mailjet://authtoken@default', 'Invalid "mailjet://authtoken@default" notifier DSN: Password is not set.'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://default']; // missing "from" and "token" option
        yield ['somethingElse://authtoken@default']; // missing "from" option
    }
}
