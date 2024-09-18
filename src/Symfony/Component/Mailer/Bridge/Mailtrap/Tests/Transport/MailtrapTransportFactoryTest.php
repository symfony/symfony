<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailtrap\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapApiTransport;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapSmtpTransport;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class MailtrapTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new MailtrapTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('mailtrap+api', 'default'),
            true,
        ];

        yield [
            new Dsn('mailtrap', 'default'),
            true,
        ];

        yield [
            new Dsn('mailtrap+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('mailtrap+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('mailtrap+smtp', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('mailtrap+api', 'default', self::USER),
            new MailtrapApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('mailtrap+api', 'example.com', self::USER, '', 8080),
            (new MailtrapApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('mailtrap', 'default', self::USER),
            new MailtrapSmtpTransport(self::USER, null, $logger),
        ];

        yield [
            new Dsn('mailtrap+smtp', 'default', self::USER),
            new MailtrapSmtpTransport(self::USER, null, $logger),
        ];

        yield [
            new Dsn('mailtrap+smtps', 'default', self::USER),
            new MailtrapSmtpTransport(self::USER, null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('mailtrap+foo', 'default', self::USER),
            'The "mailtrap+foo" scheme is not supported; supported schemes for mailer "mailtrap" are: "mailtrap", "mailtrap+api", "mailtrap+smtp", "mailtrap+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('mailtrap+api', 'default')];
    }
}
