<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Brevo\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoSmtpTransport;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class BrevoTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new BrevoTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('brevo', 'default'),
            true,
        ];

        yield [
            new Dsn('brevo+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('brevo+smtp', 'example.com'),
            true,
        ];

        yield [
            new Dsn('brevo+api', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('brevo', 'default', self::USER, self::PASSWORD),
            new BrevoSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('brevo+smtp', 'default', self::USER, self::PASSWORD),
            new BrevoSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('brevo+smtp', 'default', self::USER, self::PASSWORD, 465),
            new BrevoSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('brevo+api', 'default', self::USER),
            new BrevoApiTransport(self::USER, new MockHttpClient(), null, new NullLogger()),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('brevo+foo', 'default', self::USER, self::PASSWORD),
            'The "brevo+foo" scheme is not supported; supported schemes for mailer "brevo" are: "brevo", "brevo+smtp", "brevo+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('brevo+smtp', 'default', self::USER)];

        yield [new Dsn('brevo+smtp', 'default', null, self::PASSWORD)];

        yield [new Dsn('brevo+api', 'default')];
    }
}
