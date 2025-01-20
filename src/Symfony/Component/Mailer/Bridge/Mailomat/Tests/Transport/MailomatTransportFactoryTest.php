<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailomat\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Mailomat\Transport\MailomatApiTransport;
use Symfony\Component\Mailer\Bridge\Mailomat\Transport\MailomatSmtpTransport;
use Symfony\Component\Mailer\Bridge\Mailomat\Transport\MailomatTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class MailomatTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new MailomatTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('mailomat+api', 'default'),
            true,
        ];

        yield [
            new Dsn('mailomat', 'default'),
            true,
        ];

        yield [
            new Dsn('mailomat+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('mailomat+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('mailomat+smtp', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('mailomat+api', 'default', self::USER),
            new MailomatApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('mailomat+api', 'example.com', self::USER, '', 8080),
            (new MailomatApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('mailomat', 'default', self::USER, self::PASSWORD),
            new MailomatSmtpTransport(self::USER, self::PASSWORD, null, $logger),
        ];

        yield [
            new Dsn('mailomat+smtp', 'default', self::USER, self::PASSWORD),
            new MailomatSmtpTransport(self::USER, self::PASSWORD, null, $logger),
        ];

        yield [
            new Dsn('mailomat+smtps', 'default', self::USER, self::PASSWORD),
            new MailomatSmtpTransport(self::USER, self::PASSWORD, null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('mailomat+foo', 'default', self::USER),
            'The "mailomat+foo" scheme is not supported; supported schemes for mailer "mailomat" are: "mailomat", "mailomat+api", "mailomat+smtp", "mailomat+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('mailomat+api', 'default')];
    }
}
