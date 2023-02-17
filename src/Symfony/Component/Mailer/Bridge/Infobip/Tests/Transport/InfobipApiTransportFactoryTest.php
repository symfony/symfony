<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Infobip\Tests\Transport;

use Symfony\Component\Mailer\Bridge\Infobip\Transport\InfobipApiTransport;
use Symfony\Component\Mailer\Bridge\Infobip\Transport\InfobipSmtpTransport;
use Symfony\Component\Mailer\Bridge\Infobip\Transport\InfobipTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class InfobipApiTransportFactoryTest extends TransportFactoryTestCase
{
    public static function getFactory(): TransportFactoryInterface
    {
        return new InfobipTransportFactory(self::getDispatcher(), self::getClient(), self::getLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('infobip+api', 'default'),
            true,
        ];

        yield [
            new Dsn('infobip', 'default'),
            true,
        ];

        yield [
            new Dsn('infobip+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('infobip+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('infobip+smtp', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $dispatcher = self::getDispatcher();
        $logger = self::getLogger();

        yield [
            new Dsn('infobip+api', 'example.com', self::PASSWORD),
            (new InfobipApiTransport(self::PASSWORD, self::getClient(), $dispatcher, $logger))->setHost('example.com'),
        ];

        yield [
            new Dsn('infobip', 'default', self::PASSWORD),
            new InfobipSmtpTransport(self::PASSWORD, $dispatcher, $logger),
        ];

        yield [
            new Dsn('infobip+smtp', 'default', self::PASSWORD),
            new InfobipSmtpTransport(self::PASSWORD, $dispatcher, $logger),
        ];

        yield [
            new Dsn('infobip+smtps', 'default', self::PASSWORD),
            new InfobipSmtpTransport(self::PASSWORD, $dispatcher, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('infobip+foo', 'infobip', self::USER, self::PASSWORD),
            'The "infobip+foo" scheme is not supported; supported schemes for mailer "infobip" are: "infobip", "infobip+api", "infobip+smtp", "infobip+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('infobip+smtp', 'default')];
        yield [new Dsn('infobip+api', 'default')];
        yield [new Dsn('infobip+api', 'default', self::PASSWORD)];
    }
}
