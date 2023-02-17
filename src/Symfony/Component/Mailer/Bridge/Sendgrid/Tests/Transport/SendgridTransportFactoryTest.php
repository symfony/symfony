<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridApiTransport;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridSmtpTransport;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class SendgridTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new SendgridTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('sendgrid+api', 'default'),
            true,
        ];

        yield [
            new Dsn('sendgrid', 'default'),
            true,
        ];

        yield [
            new Dsn('sendgrid+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('sendgrid+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('sendgrid+smtp', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('sendgrid+api', 'default', self::USER),
            new SendgridApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('sendgrid+api', 'example.com', self::USER, '', 8080),
            (new SendgridApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('sendgrid', 'default', self::USER),
            new SendgridSmtpTransport(self::USER, null, $logger),
        ];

        yield [
            new Dsn('sendgrid+smtp', 'default', self::USER),
            new SendgridSmtpTransport(self::USER, null, $logger),
        ];

        yield [
            new Dsn('sendgrid+smtps', 'default', self::USER),
            new SendgridSmtpTransport(self::USER, null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('sendgrid+foo', 'sendgrid', self::USER),
            'The "sendgrid+foo" scheme is not supported; supported schemes for mailer "sendgrid" are: "sendgrid", "sendgrid+api", "sendgrid+smtp", "sendgrid+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('sendgrid+api', 'default')];
    }
}
