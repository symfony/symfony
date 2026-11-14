<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Groundmail\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Groundmail\Transport\GroundmailApiTransport;
use Symfony\Component\Mailer\Bridge\Groundmail\Transport\GroundmailSmtpTransport;
use Symfony\Component\Mailer\Bridge\Groundmail\Transport\GroundmailTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class GroundmailTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new GroundmailTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('groundmail', 'default'),
            true,
        ];

        yield [
            new Dsn('groundmail+api', 'default'),
            true,
        ];

        yield [
            new Dsn('groundmail+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('groundmail+smtps', 'default'),
            false,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('groundmail', 'default', self::USER),
            new GroundmailApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('groundmail+api', 'default', self::USER),
            new GroundmailApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('groundmail+api', 'example.com', self::USER, null, 8080),
            (new GroundmailApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('groundmail+smtp', 'default', self::USER, self::PASSWORD),
            new GroundmailSmtpTransport(self::USER, self::PASSWORD, null, null, $logger),
        ];

        yield 'port 2525' => [
            new Dsn('groundmail+smtp', 'default', self::USER, self::PASSWORD, 2525),
            new GroundmailSmtpTransport(self::USER, self::PASSWORD, 2525, null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('groundmail+foo', 'default', self::USER),
            'The "groundmail+foo" scheme is not supported; supported schemes for mailer "groundmail" are: "groundmail", "groundmail+api", "groundmail+smtp".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('groundmail', 'default')];
        yield [new Dsn('groundmail+api', 'default')];
        yield [new Dsn('groundmail+smtp', 'default', self::USER)];
        yield [new Dsn('groundmail+smtp', 'default')];
    }

    public function testSmtpTransportRequiresTls()
    {
        $factory = $this->getFactory();

        $this->assertTrue($factory->create(new Dsn('groundmail+smtp', 'default', self::USER, self::PASSWORD))->isTlsRequired());
        $this->assertFalse($factory->create(new Dsn('groundmail+smtp', 'default', self::USER, self::PASSWORD, null, ['require_tls' => 'false']))->isTlsRequired());
    }
}
