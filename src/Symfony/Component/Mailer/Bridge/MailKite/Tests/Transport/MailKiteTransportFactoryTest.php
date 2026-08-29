<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailKite\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\MailKite\Transport\MailKiteApiTransport;
use Symfony\Component\Mailer\Bridge\MailKite\Transport\MailKiteSmtpTransport;
use Symfony\Component\Mailer\Bridge\MailKite\Transport\MailKiteTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class MailKiteTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new MailKiteTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('mailkite', 'default'),
            true,
        ];

        yield [
            new Dsn('mailkite+api', 'default'),
            true,
        ];

        yield [
            new Dsn('mailkite+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('mailkite+smtps', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('mailkite+api', 'default', self::USER),
            new MailKiteApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('mailkite+api', 'example.com', self::USER),
            (new MailKiteApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com'),
        ];

        yield [
            new Dsn('mailkite+api', 'example.com', self::USER, null, 8080),
            (new MailKiteApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('mailkite', 'default', self::USER),
            new MailKiteSmtpTransport(self::USER, false, null, $logger),
        ];

        yield [
            new Dsn('mailkite+smtp', 'default', self::USER),
            new MailKiteSmtpTransport(self::USER, false, null, $logger),
        ];

        yield [
            new Dsn('mailkite+smtps', 'default', self::USER),
            new MailKiteSmtpTransport(self::USER, true, null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('mailkite+foo', 'default'),
            'The "mailkite+foo" scheme is not supported; supported schemes for mailer "mailkite" are: "mailkite", "mailkite+api", "mailkite+smtp", "mailkite+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('mailkite+api', 'default')];
    }
}
