<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\AhaSend\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\AhaSend\Transport\AhaSendApiTransport;
use Symfony\Component\Mailer\Bridge\AhaSend\Transport\AhaSendSmtpTransport;
use Symfony\Component\Mailer\Bridge\AhaSend\Transport\AhaSendTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class AhaSendTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new AhaSendTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('ahasend+api', 'default'),
            true,
        ];

        yield [
            new Dsn('ahasend', 'default'),
            true,
        ];

        yield [
            new Dsn('ahasend+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('ahasend+smtp', 'example.com'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('ahasend+api', 'default', self::USER),
            new AhaSendApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('ahasend+api', 'example.com', self::USER, '', 8080),
            (new AhaSendApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('ahasend+api', 'example.com', self::USER, '', 8080, ['message_stream' => 'broadcasts']),
            (new AhaSendApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('ahasend', 'default', self::USER, self::PASSWORD),
            new AhaSendSmtpTransport(self::USER, self::PASSWORD, null, $logger),
        ];

        yield [
            new Dsn('ahasend+smtp', 'default', self::USER, self::PASSWORD),
            new AhaSendSmtpTransport(self::USER, self::PASSWORD, null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('ahasend+foo', 'default', self::USER),
            'The "ahasend+foo" scheme is not supported; supported schemes for mailer "ahasend" are: "ahasend", "ahasend+api", "ahasend+smtp".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('ahasend+api', 'default')];
        yield [new Dsn('ahasend+smtp', 'default', self::USER)];
        yield [new Dsn('ahasend', 'default', self::USER)];
    }
}
