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
            new Dsn('ses+api', 'default'),
            true,
        ];

        yield [
            new Dsn('ses+https', 'default'),
            true,
        ];

        yield [
            new Dsn('ses', 'default'),
            true,
        ];

        yield [
            new Dsn('ses+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('ses+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('ses+smtp', 'example.com'),
            true,
        ];
    }

    public function createProvider(): iterable
    {
        $client = $this->getClient();
        $dispatcher = $this->getDispatcher();
        $logger = $this->getLogger();

        yield [
            new Dsn('ses+api', 'default', self::USER, self::PASSWORD),
            new SesApiTransport(self::USER, self::PASSWORD, null, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('ses+api', 'default', self::USER, self::PASSWORD, null, ['region' => 'eu-west-1']),
            new SesApiTransport(self::USER, self::PASSWORD, 'eu-west-1', $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('ses+api', 'example.com', self::USER, self::PASSWORD, 8080),
            (new SesApiTransport(self::USER, self::PASSWORD, null, $client, $dispatcher, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('ses+https', 'default', self::USER, self::PASSWORD),
            new SesHttpTransport(self::USER, self::PASSWORD, null, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('ses', 'default', self::USER, self::PASSWORD),
            new SesHttpTransport(self::USER, self::PASSWORD, null, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('ses+https', 'example.com', self::USER, self::PASSWORD, 8080),
            (new SesHttpTransport(self::USER, self::PASSWORD, null, $client, $dispatcher, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('ses+https', 'default', self::USER, self::PASSWORD, null, ['region' => 'eu-west-1']),
            new SesHttpTransport(self::USER, self::PASSWORD, 'eu-west-1', $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('ses+smtp', 'default', self::USER, self::PASSWORD),
            new SesSmtpTransport(self::USER, self::PASSWORD, null, $dispatcher, $logger),
        ];

        yield [
            new Dsn('ses+smtp', 'default', self::USER, self::PASSWORD, null, ['region' => 'eu-west-1']),
            new SesSmtpTransport(self::USER, self::PASSWORD, 'eu-west-1', $dispatcher, $logger),
        ];

        yield [
            new Dsn('ses+smtps', 'default', self::USER, self::PASSWORD, null, ['region' => 'eu-west-1']),
            new SesSmtpTransport(self::USER, self::PASSWORD, 'eu-west-1', $dispatcher, $logger),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('ses+foo', 'default', self::USER, self::PASSWORD),
            'The "ses+foo" scheme is not supported; supported schemes for mailer "ses" are: "ses", "ses+api", "ses+https", "ses+smtp", "ses+smtps".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('ses+smtp', 'default', self::USER)];

        yield [new Dsn('ses+smtp', 'default', null, self::PASSWORD)];
    }
}
