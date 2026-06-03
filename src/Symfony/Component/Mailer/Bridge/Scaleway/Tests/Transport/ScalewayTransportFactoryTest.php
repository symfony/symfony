<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Scaleway\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Scaleway\Transport\ScalewayApiTransport;
use Symfony\Component\Mailer\Bridge\Scaleway\Transport\ScalewaySmtpTransport;
use Symfony\Component\Mailer\Bridge\Scaleway\Transport\ScalewayTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class ScalewayTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new ScalewayTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('scaleway+api', 'default'),
            true,
        ];

        yield [
            new Dsn('scaleway', 'default'),
            true,
        ];

        yield [
            new Dsn('scaleway+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('scaleway+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('scaleway+smtp', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('scaleway+api', 'default', self::USER, self::PASSWORD, null, ['region' => 'fr-par']),
            new ScalewayApiTransport(self::USER, self::PASSWORD, 'fr-par', new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('scaleway', 'default', self::USER, self::PASSWORD),
            new ScalewayApiTransport(self::USER, self::PASSWORD, null, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('scaleway+smtp', 'default', self::USER, self::PASSWORD),
            new ScalewaySmtpTransport(self::USER, self::PASSWORD, null, $logger),
        ];

        yield [
            new Dsn('scaleway+smtps', 'default', self::USER, self::PASSWORD),
            new ScalewaySmtpTransport(self::USER, self::PASSWORD, null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('scaleway+foo', 'default', self::USER, self::PASSWORD),
            'The "scaleway+foo" scheme is not supported; supported schemes for mailer "scaleway" are: "scaleway", "scaleway+api", "scaleway+smtp", "scaleway+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('scaleway+api', 'default', self::USER)];

        yield [new Dsn('scaleway+api', 'default', null, self::PASSWORD)];
    }
}
