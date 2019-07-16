<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Tests\Factory;

use Symfony\Component\Mailer\Bridge\Amazon;
use Symfony\Component\Mailer\Bridge\Amazon\Factory\SesTransportFactory;
use Symfony\Component\Mailer\Tests\TransportFactoryTestCase;
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
            new Amazon\Http\Api\SesTransport(self::USER, self::PASSWORD, null, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('api', 'ses', self::USER, self::PASSWORD, null, ['region' => 'eu-west-1']),
            new Amazon\Http\Api\SesTransport(self::USER, self::PASSWORD, 'eu-west-1', $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('http', 'ses', self::USER, self::PASSWORD),
            new Amazon\Http\SesTransport(self::USER, self::PASSWORD, null, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('http', 'ses', self::USER, self::PASSWORD, null, ['region' => 'eu-west-1']),
            new Amazon\Http\SesTransport(self::USER, self::PASSWORD, 'eu-west-1', $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('smtp', 'ses', self::USER, self::PASSWORD),
            new Amazon\Smtp\SesTransport(self::USER, self::PASSWORD, null, $dispatcher, $logger),
        ];

        yield [
            new Dsn('smtp', 'ses', self::USER, self::PASSWORD, null, ['region' => 'eu-west-1']),
            new Amazon\Smtp\SesTransport(self::USER, self::PASSWORD, 'eu-west-1', $dispatcher, $logger),
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
