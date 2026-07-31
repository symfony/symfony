<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\TurboSmtp\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\TurboSmtp\Transport\TurboSmtpApiTransport;
use Symfony\Component\Mailer\Bridge\TurboSmtp\Transport\TurboSmtpSmtpTransport;
use Symfony\Component\Mailer\Bridge\TurboSmtp\Transport\TurboSmtpTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class TurboSmtpTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new TurboSmtpTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('turbosmtp+api', 'default'),
            true,
        ];

        yield [
            new Dsn('turbosmtp', 'default'),
            true,
        ];

        yield [
            new Dsn('turbosmtp+smtp', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('turbosmtp+api', 'default', self::USER, self::PASSWORD),
            new TurboSmtpApiTransport(self::USER, self::PASSWORD, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('turbosmtp+api', 'api.eu.turbo-smtp.com', self::USER, self::PASSWORD),
            (new TurboSmtpApiTransport(self::USER, self::PASSWORD, new MockHttpClient(), null, $logger))->setHost('api.eu.turbo-smtp.com'),
        ];

        yield [
            new Dsn('turbosmtp+api', 'example.com', self::USER, self::PASSWORD, 8080),
            (new TurboSmtpApiTransport(self::USER, self::PASSWORD, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('turbosmtp+smtp', 'default', self::USER, self::PASSWORD),
            new TurboSmtpSmtpTransport('pro.turbo-smtp.com', self::USER, self::PASSWORD, null, $logger),
        ];

        yield [
            new Dsn('turbosmtp+smtp', 'pro.eu.turbo-smtp.com', self::USER, self::PASSWORD),
            new TurboSmtpSmtpTransport('pro.eu.turbo-smtp.com', self::USER, self::PASSWORD, null, $logger),
        ];

        yield [
            new Dsn('turbosmtp', 'default', self::USER, self::PASSWORD),
            new TurboSmtpSmtpTransport('pro.turbo-smtp.com', self::USER, self::PASSWORD, null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('turbosmtp+foo', 'default', self::USER, self::PASSWORD),
            'The "turbosmtp+foo" scheme is not supported; supported schemes for mailer "turbosmtp" are: "turbosmtp", "turbosmtp+api", "turbosmtp+smtp".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('turbosmtp+api', 'default')];
        yield [new Dsn('turbosmtp+api', 'default', self::USER)];
    }
}
