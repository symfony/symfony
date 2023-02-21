<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Google\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailSmtpTransport;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class GmailTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new GmailTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('gmail', 'default'),
            true,
        ];

        yield [
            new Dsn('gmail+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('gmail+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('gmail+smtp', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('gmail', 'default', self::USER, self::PASSWORD),
            new GmailSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('gmail+smtp', 'default', self::USER, self::PASSWORD),
            new GmailSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('gmail+smtps', 'default', self::USER, self::PASSWORD),
            new GmailSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('gmail+foo', 'default', self::USER, self::PASSWORD),
            'The "gmail+foo" scheme is not supported; supported schemes for mailer "gmail" are: "gmail", "gmail+smtp", "gmail+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('gmail+smtp', 'default', self::USER)];

        yield [new Dsn('gmail+smtp', 'default', null, self::PASSWORD)];
    }
}
