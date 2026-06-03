<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sweego\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Sweego\Transport\SweegoApiTransport;
use Symfony\Component\Mailer\Bridge\Sweego\Transport\SweegoSmtpTransport;
use Symfony\Component\Mailer\Bridge\Sweego\Transport\SweegoTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class SweegoTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new SweegoTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('sweego', 'default'),
            true,
        ];

        yield [
            new Dsn('sweego+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('sweego+smtp', 'example.com'),
            true,
        ];

        yield [
            new Dsn('sweego+api', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('sweego', 'default', self::USER, self::PASSWORD, 465),
            new SweegoSmtpTransport('default', 465, self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('sweego+smtp', 'default', self::USER, self::PASSWORD, 465),
            new SweegoSmtpTransport('default', 465, self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('sweego+smtp', 'default', self::USER, self::PASSWORD, 465),
            new SweegoSmtpTransport('default', 465, self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('sweego+api', 'default', self::USER),
            new SweegoApiTransport(self::USER, new MockHttpClient(), null, new NullLogger()),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('sweego+foo', 'default', self::USER, self::PASSWORD, 465),
            'The "sweego+foo" scheme is not supported; supported schemes for mailer "sweego" are: "sweego", "sweego+smtp", "sweego+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('sweego+smtp', 'default', self::USER)];

        yield [new Dsn('sweego+api', 'default')];
    }
}
