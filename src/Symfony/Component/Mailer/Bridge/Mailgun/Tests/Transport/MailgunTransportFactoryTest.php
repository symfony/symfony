<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Tests\Transport;

use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunSmtpTransport;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Tests\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class MailgunTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new MailgunTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('api', 'mailgun'),
            true,
        ];

        yield [
            new Dsn('http', 'mailgun'),
            true,
        ];

        yield [
            new Dsn('smtp', 'mailgun'),
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
            new Dsn('api', 'mailgun', self::USER, self::PASSWORD),
            new MailgunApiTransport(self::USER, self::PASSWORD, null, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('api', 'mailgun', self::USER, self::PASSWORD, null, ['region' => 'eu']),
            new MailgunApiTransport(self::USER, self::PASSWORD, 'eu', $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('http', 'mailgun', self::USER, self::PASSWORD),
            new MailgunHttpTransport(self::USER, self::PASSWORD, null, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('smtp', 'mailgun', self::USER, self::PASSWORD),
            new MailgunSmtpTransport(self::USER, self::PASSWORD, null, $dispatcher, $logger),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('foo', 'mailgun', self::USER, self::PASSWORD),
            'The "foo" scheme is not supported for mailer "mailgun". Supported schemes are: "api", "http", "smtp".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('api', 'mailgun', self::USER)];

        yield [new Dsn('api', 'mailgun', null, self::PASSWORD)];
    }
}
