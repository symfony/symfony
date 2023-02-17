<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\OhMySmtp\Tests\Transport;

use Symfony\Component\Mailer\Bridge\OhMySmtp\Transport\OhMySmtpApiTransport;
use Symfony\Component\Mailer\Bridge\OhMySmtp\Transport\OhMySmtpSmtpTransport;
use Symfony\Component\Mailer\Bridge\OhMySmtp\Transport\OhMySmtpTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

final class OhMySmtpTransportFactoryTest extends TransportFactoryTestCase
{
    public static function getFactory(): TransportFactoryInterface
    {
        return new OhMySmtpTransportFactory(self::getDispatcher(), self::getClient(), self::getLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('ohmysmtp+api', 'default'),
            true,
        ];

        yield [
            new Dsn('ohmysmtp', 'default'),
            true,
        ];

        yield [
            new Dsn('ohmysmtp+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('ohmysmtp+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('ohmysmtp+smtp', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $dispatcher = self::getDispatcher();
        $logger = self::getLogger();

        yield [
            new Dsn('ohmysmtp+api', 'default', self::USER),
            new OhMySmtpApiTransport(self::USER, self::getClient(), $dispatcher, $logger),
        ];

        yield [
            new Dsn('ohmysmtp+api', 'example.com', self::USER, '', 8080),
            (new OhMySmtpApiTransport(self::USER, self::getClient(), $dispatcher, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('ohmysmtp', 'default', self::USER),
            new OhMySmtpSmtpTransport(self::USER, $dispatcher, $logger),
        ];

        yield [
            new Dsn('ohmysmtp+smtp', 'default', self::USER),
            new OhMySmtpSmtpTransport(self::USER, $dispatcher, $logger),
        ];

        yield [
            new Dsn('ohmysmtp+smtps', 'default', self::USER),
            new OhMySmtpSmtpTransport(self::USER, $dispatcher, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('ohmysmtp+foo', 'default', self::USER),
            'The "ohmysmtp+foo" scheme is not supported; supported schemes for mailer "ohmysmtp" are: "ohmysmtp", "ohmysmtp+api", "ohmysmtp+smtp", "ohmysmtp+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('ohmysmtp+api', 'default')];
    }
}
