<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\SendPulse\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\SendPulse\Transport\SendPulseApiTransport;
use Symfony\Component\Mailer\Bridge\SendPulse\Transport\SendPulseSmtpTransport;
use Symfony\Component\Mailer\Bridge\SendPulse\Transport\SendPulseTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class SendPulseTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function testDefaultFactoryRegistrationDoesNotMakeHttpRequests(): void
    {
        $client = new MockHttpClient([]);
        $transport = \Symfony\Component\Mailer\Transport::fromDsn('sendpulse+api://client:secret@default', null, $client);

        $this->assertInstanceOf(SendPulseApiTransport::class, $transport);
        $this->assertSame(0, $client->getRequestsCount());
    }

    public function getFactory(): TransportFactoryInterface
    {
        return new SendPulseTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('sendpulse', 'default'),
            true,
        ];

        yield [
            new Dsn('sendpulse+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('sendpulse+smtp', 'example.com'),
            true,
        ];

        yield [
            new Dsn('sendpulse+api', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('sendpulse', 'default', self::USER, self::PASSWORD),
            new SendPulseSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('sendpulse+smtp', 'default', self::USER, self::PASSWORD),
            new SendPulseSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('sendpulse+smtp', 'default', self::USER, self::PASSWORD, 465),
            new SendPulseSmtpTransport(self::USER, self::PASSWORD, null, new NullLogger()),
        ];

        yield [
            new Dsn('sendpulse+api', 'default', self::USER, self::PASSWORD),
            new SendPulseApiTransport(self::USER, self::PASSWORD, new MockHttpClient(), null, new NullLogger()),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('sendpulse+foo', 'default', self::USER, self::PASSWORD),
            'The "sendpulse+foo" scheme is not supported; supported schemes for mailer "sendpulse" are: "sendpulse", "sendpulse+smtp", "sendpulse+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('sendpulse+smtp', 'default', self::USER)];

        yield [new Dsn('sendpulse+smtp', 'default', null, self::PASSWORD)];

        yield [new Dsn('sendpulse+api', 'default')];
        yield [new Dsn('sendpulse+api', 'default', self::USER)];
    }
}
