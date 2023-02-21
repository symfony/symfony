<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailPace\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\MailPace\Transport\MailPaceApiTransport;
use Symfony\Component\Mailer\Bridge\MailPace\Transport\MailPaceSmtpTransport;
use Symfony\Component\Mailer\Bridge\MailPace\Transport\MailPaceTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

final class MailPaceTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new MailPaceTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('mailpace+api', 'default'),
            true,
        ];

        yield [
            new Dsn('mailpace', 'default'),
            true,
        ];

        yield [
            new Dsn('mailpace+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('mailpace+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('mailpace+smtp', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('mailpace+api', 'default', self::USER),
            new MailPaceApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('mailpace+api', 'example.com', self::USER, '', 8080),
            (new MailPaceApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('mailpace', 'default', self::USER),
            new MailPaceSmtpTransport(self::USER, null, $logger),
        ];

        yield [
            new Dsn('mailpace+smtp', 'default', self::USER),
            new MailPaceSmtpTransport(self::USER, null, $logger),
        ];

        yield [
            new Dsn('mailpace+smtps', 'default', self::USER),
            new MailPaceSmtpTransport(self::USER, null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('mailpace+foo', 'default', self::USER),
            'The "mailpace+foo" scheme is not supported; supported schemes for mailer "mailpace" are: "mailpace", "mailpace+api", "mailpace+smtp", "mailpace+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('mailpace+api', 'default')];
    }
}
