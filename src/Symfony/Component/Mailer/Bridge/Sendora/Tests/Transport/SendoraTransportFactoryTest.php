<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendora\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Sendora\Transport\SendoraApiTransport;
use Symfony\Component\Mailer\Bridge\Sendora\Transport\SendoraSmtpTransport;
use Symfony\Component\Mailer\Bridge\Sendora\Transport\SendoraTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class SendoraTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    private const STREAM = '3d1a2b4c-5e6f-4a7b-8c9d-0e1f2a3b4c5d';

    public function getFactory(): TransportFactoryInterface
    {
        return new SendoraTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('sendora', 'default'),
            true,
        ];

        yield [
            new Dsn('sendora+api', 'default'),
            true,
        ];

        yield [
            new Dsn('sendora+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('sendora+smtps', 'default'),
            false,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('sendora', 'default', self::USER),
            new SendoraApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('sendora+api', 'default', self::USER),
            new SendoraApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('sendora+api', 'example.com', self::USER, null, 8080),
            (new SendoraApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('sendora+api', 'default', self::USER, null, null, ['stream' => self::STREAM]),
            (new SendoraApiTransport(self::USER, new MockHttpClient(), null, $logger))->setStream(self::STREAM),
        ];

        yield [
            new Dsn('sendora+smtp', 'default', self::USER),
            new SendoraSmtpTransport(self::USER, null, null, null, $logger),
        ];

        yield 'port 587 with STARTTLS' => [
            new Dsn('sendora+smtp', 'default', self::USER, null, 587),
            new SendoraSmtpTransport(self::USER, null, 587, null, $logger),
        ];

        yield [
            new Dsn('sendora+smtp', 'example.com', self::USER),
            new SendoraSmtpTransport(self::USER, 'example.com', null, null, $logger),
        ];

        yield [
            new Dsn('sendora+smtp', 'default', self::USER, null, null, ['stream' => self::STREAM]),
            (new SendoraSmtpTransport(self::USER, null, null, null, $logger))->setStream(self::STREAM),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('sendora+foo', 'default', self::USER),
            'The "sendora+foo" scheme is not supported; supported schemes for mailer "sendora" are: "sendora", "sendora+api", "sendora+smtp".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('sendora', 'default')];
        yield [new Dsn('sendora+api', 'default')];
        yield [new Dsn('sendora+smtp', 'default')];
    }

    public function testSmtpTransportRequiresTls()
    {
        $factory = $this->getFactory();

        $this->assertTrue($factory->create(new Dsn('sendora+smtp', 'default', self::USER))->isTlsRequired());
        $this->assertTrue($factory->create(new Dsn('sendora+smtp', 'default', self::USER, null, 587))->isTlsRequired());
        $this->assertFalse($factory->create(new Dsn('sendora+smtp', 'default', self::USER, null, 587, ['require_tls' => 'false']))->isTlsRequired());
    }
}
