<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\ZohoCpaas\Tests\Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Bridge\ZohoCpaas\Transport\ZohoCpaasApiTransport;
use Symfony\Component\Mailer\Bridge\ZohoCpaas\Transport\ZohoCpaasSmtpTransport;
use Symfony\Component\Mailer\Bridge\ZohoCpaas\Transport\ZohoCpaasTransportFactory;
use Symfony\Component\Mailer\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Mailer\Test\IncompleteDsnTestTrait;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class ZohoCpaasTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function getFactory(): TransportFactoryInterface
    {
        return new ZohoCpaasTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('zohocpaas', 'default'),
            true,
        ];

        yield [
            new Dsn('zohocpaas+api', 'default'),
            true,
        ];

        yield [
            new Dsn('zohocpaas+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('zohocpaas+smtps', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('zohocpaas', 'default', self::USER),
            new ZohoCpaasApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('zohocpaas+api', 'default', self::USER),
            new ZohoCpaasApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('zohocpaas+api', 'cpaas.zoho.eu', self::USER),
            (new ZohoCpaasApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('cpaas.zoho.eu'),
        ];

        yield [
            new Dsn('zohocpaas+api', 'example.com', self::USER, null, 8080),
            (new ZohoCpaasApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('zohocpaas+smtp', 'default', self::USER, self::PASSWORD),
            new ZohoCpaasSmtpTransport(self::USER, self::PASSWORD, null, null, null, null, $logger),
        ];

        yield [
            new Dsn('zohocpaas+smtps', 'default', self::USER, self::PASSWORD),
            new ZohoCpaasSmtpTransport(self::USER, self::PASSWORD, null, null, true, null, $logger),
        ];

        yield [
            new Dsn('zohocpaas+smtp', 'smtp.zeptomail.eu', self::USER, self::PASSWORD),
            new ZohoCpaasSmtpTransport(self::USER, self::PASSWORD, 'smtp.zeptomail.eu', null, null, null, $logger),
        ];

        yield 'a port of your own' => [
            new Dsn('zohocpaas+smtp', 'smtp.zeptomail.eu', self::USER, self::PASSWORD, 2525),
            new ZohoCpaasSmtpTransport(self::USER, self::PASSWORD, 'smtp.zeptomail.eu', 2525, null, null, $logger),
        ];

        yield 'port 465 on the plain scheme is TLS from the start' => [
            new Dsn('zohocpaas+smtp', 'smtp.zeptomail.eu', self::USER, self::PASSWORD, 465),
            new ZohoCpaasSmtpTransport(self::USER, self::PASSWORD, 'smtp.zeptomail.eu', 465, null, null, $logger),
        ];

        yield 'a port of your own with TLS from the start' => [
            new Dsn('zohocpaas+smtps', 'smtp.zeptomail.eu', self::USER, self::PASSWORD, 2465),
            new ZohoCpaasSmtpTransport(self::USER, self::PASSWORD, 'smtp.zeptomail.eu', 2465, true, null, $logger),
        ];

        yield [
            new Dsn('zohocpaas+smtps', 'smtp.zeptomail.eu', self::USER, self::PASSWORD),
            new ZohoCpaasSmtpTransport(self::USER, self::PASSWORD, 'smtp.zeptomail.eu', null, true, null, $logger),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('zohocpaas+foo', 'default', self::USER),
            'The "zohocpaas+foo" scheme is not supported; supported schemes for mailer "zohocpaas" are: "zohocpaas", "zohocpaas+api", "zohocpaas+smtp", "zohocpaas+smtps".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('zohocpaas+api', 'default')];
        yield [new Dsn('zohocpaas+smtp', 'default', self::USER)];
        yield [new Dsn('zohocpaas+smtps', 'default', self::USER)];
        yield [new Dsn('zohocpaas+smtp', 'default')];
    }
}
