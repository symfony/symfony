<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Resend\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Resend\Transport\ResendApiTransport;
use Symfony\Component\Mailer\Bridge\Resend\Transport\ResendSmtpTransport;
use Symfony\Component\Mailer\Bridge\Resend\Transport\ResendTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class ResendTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new ResendTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('resend', 'default'),
            true,
        ];

        yield [
            new Dsn('resend+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('resend+smtp', 'example.com'),
            true,
        ];

        yield [
            new Dsn('resend+api', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('resend', 'default', self::USER, self::PASSWORD),
            new ResendSmtpTransport(self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('resend+smtp', 'default', self::USER, self::PASSWORD),
            new ResendSmtpTransport(self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('resend+smtp', 'default', self::USER, self::PASSWORD, 465),
            new ResendSmtpTransport(self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('resend+api', 'default', self::USER),
            new ResendApiTransport(self::USER, new MockHttpClient(), null, new NullLogger()),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('resend+foo', 'default', self::USER, self::PASSWORD),
            'The "resend+foo" scheme is not supported; supported schemes for mailer "resend" are: "resend", "resend+smtp", "resend+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('resend+smtp', 'default', self::USER)];

        yield [new Dsn('resend+api', 'default')];
    }
}
