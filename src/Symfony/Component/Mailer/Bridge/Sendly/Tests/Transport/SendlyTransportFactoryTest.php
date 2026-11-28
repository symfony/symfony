<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendly\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\Sendly\Transport\SendlyApiTransport;
use Symfony\Component\Mailer\Bridge\Sendly\Transport\SendlyTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class SendlyTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new SendlyTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('sendly', 'default'),
            true,
        ];

        yield [
            new Dsn('sendly+api', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('sendly', 'default', self::USER),
            new SendlyApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('sendly+api', 'default', self::USER),
            new SendlyApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('sendly+api', 'example.com', self::USER),
            (new SendlyApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com'),
        ];

        yield [
            new Dsn('sendly+api', 'example.com', self::USER, null, 8080),
            (new SendlyApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('sendly+foo', 'default'),
            'The "sendly+foo" scheme is not supported; supported schemes for mailer "sendly" are: "sendly", "sendly+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('sendly+api', 'default')];
    }
}
