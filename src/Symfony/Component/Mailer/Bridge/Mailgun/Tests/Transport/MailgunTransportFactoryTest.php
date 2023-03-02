<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunSmtpTransport;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class MailgunTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new MailgunTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('mailgun+api', 'default'),
            true,
        ];

        yield [
            new Dsn('mailgun', 'default'),
            true,
        ];

        yield [
            new Dsn('mailgun+https', 'default'),
            true,
        ];

        yield [
            new Dsn('mailgun+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('mailgun+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('mailgun+smtp', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $client = new MockHttpClient();
        $logger = new NullLogger();

        yield [
            new Dsn('mailgun+api', 'default', self::USER, self::PASSWORD),
            new MailgunApiTransport(self::USER, self::PASSWORD, null, $client, null, $logger),
        ];

        yield [
            new Dsn('mailgun+api', 'default', self::USER, self::PASSWORD, null, ['region' => 'eu']),
            new MailgunApiTransport(self::USER, self::PASSWORD, 'eu', $client, null, $logger),
        ];

        yield [
            new Dsn('mailgun+api', 'example.com', self::USER, self::PASSWORD, 8080),
            (new MailgunApiTransport(self::USER, self::PASSWORD, null, $client, null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('mailgun', 'default', self::USER, self::PASSWORD),
            new MailgunHttpTransport(self::USER, self::PASSWORD, null, $client, null, $logger),
        ];

        yield [
            new Dsn('mailgun+https', 'default', self::USER, self::PASSWORD),
            new MailgunHttpTransport(self::USER, self::PASSWORD, null, $client, null, $logger),
        ];

        yield [
            new Dsn('mailgun+https', 'example.com', self::USER, self::PASSWORD, 8080),
            (new MailgunHttpTransport(self::USER, self::PASSWORD, null, $client, null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('mailgun+smtp', 'default', self::USER, self::PASSWORD),
            new MailgunSmtpTransport(self::USER, self::PASSWORD, null, null, $logger),
        ];

        yield [
            new Dsn('mailgun+smtps', 'default', self::USER, self::PASSWORD),
            new MailgunSmtpTransport(self::USER, self::PASSWORD, null, null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('mailgun+foo', 'default', self::USER, self::PASSWORD),
            'The "mailgun+foo" scheme is not supported; supported schemes for mailer "mailgun" are: "mailgun", "mailgun+api", "mailgun+https", "mailgun+smtp", "mailgun+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('mailgun+api', 'default', self::USER)];

        yield [new Dsn('mailgun+api', 'default', null, self::PASSWORD)];
    }
}
