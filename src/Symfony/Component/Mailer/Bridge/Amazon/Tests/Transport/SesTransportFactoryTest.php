<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Tests\Transport;

use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesApiTransport;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesHttpTransport;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesSmtpTransport;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class SesTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new SesTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('api', 'ses'),
            true,
        ];

        yield [
            new Dsn('http', 'ses'),
            true,
        ];

        yield [
            new Dsn('smtp', 'ses'),
            true,
        ];

        yield [
            new Dsn('smtp', 'example.com'),
            false,
        ];
    }

    public function createProvider(): iterable
    {
        $client = $this->getClient();
        $dispatcher = $this->getDispatcher();
        $logger = $this->getLogger();

        yield [
            new Dsn('api', 'ses', self::USER, self::PASSWORD),
            new SesApiTransport(self::USER, self::PASSWORD, null, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('api', 'ses', self::USER, self::PASSWORD, null, ['region' => 'eu-west-1']),
            new SesApiTransport(self::USER, self::PASSWORD, 'eu-west-1', $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('http', 'ses', self::USER, self::PASSWORD),
            new SesHttpTransport(self::USER, self::PASSWORD, null, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('http', 'ses', self::USER, self::PASSWORD, null, ['region' => 'eu-west-1']),
            new SesHttpTransport(self::USER, self::PASSWORD, 'eu-west-1', $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('smtp', 'ses', self::USER, self::PASSWORD),
            new SesSmtpTransport(self::USER, self::PASSWORD, null, $dispatcher, $logger),
        ];

        yield [
            new Dsn('smtp', 'ses', self::USER, self::PASSWORD, null, ['region' => 'eu-west-1']),
            new SesSmtpTransport(self::USER, self::PASSWORD, 'eu-west-1', $dispatcher, $logger),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('foo', 'ses', self::USER, self::PASSWORD),
            'The "foo" scheme is not supported for mailer "ses". Supported schemes are: "api", "http", "smtp".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('smtp', 'ses', self::USER)];

        yield [new Dsn('smtp', 'ses', null, self::PASSWORD)];
    }
}
