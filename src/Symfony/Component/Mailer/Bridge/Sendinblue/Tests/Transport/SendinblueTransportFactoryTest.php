<?php

namespace Symfony\Component\Mailer\Bridge\Sendinblue\Tests\Transport;

use Symfony\Component\Mailer\Bridge\Sendinblue\Transport\SendinblueApiTransport;
use Symfony\Component\Mailer\Bridge\Sendinblue\Transport\SendinblueSmtpTransport;
use Symfony\Component\Mailer\Bridge\Sendinblue\Transport\SendinblueTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class SendinblueTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new SendinblueTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('sendinblue', 'default'),
            true,
        ];

        yield [
            new Dsn('sendinblue+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('sendinblue+smtp', 'example.com'),
            true,
        ];

        yield [
            new Dsn('sendinblue+api', 'default'),
            true,
        ];
    }

    public function createProvider(): iterable
    {
        yield [
            new Dsn('sendinblue', 'default', self::USER, self::PASSWORD),
            new SendinblueSmtpTransport(self::USER, self::PASSWORD, $this->getDispatcher(), $this->getLogger()),
        ];

        yield [
            new Dsn('sendinblue+smtp', 'default', self::USER, self::PASSWORD),
            new SendinblueSmtpTransport(self::USER, self::PASSWORD, $this->getDispatcher(), $this->getLogger()),
        ];

        yield [
            new Dsn('sendinblue+smtp', 'default', self::USER, self::PASSWORD, 465),
            new SendinblueSmtpTransport(self::USER, self::PASSWORD, $this->getDispatcher(), $this->getLogger()),
        ];

        yield [
            new Dsn('sendinblue+api', 'default', self::USER),
            new SendinblueApiTransport(self::USER, $this->getClient(), $this->getDispatcher(), $this->getLogger()),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('sendinblue+foo', 'default', self::USER, self::PASSWORD),
            'The "sendinblue+foo" scheme is not supported; supported schemes for mailer "sendinblue" are: "sendinblue", "sendinblue+smtp", "sendinblue+api".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('sendinblue+smtp', 'default', self::USER)];

        yield [new Dsn('sendinblue+smtp', 'default', null, self::PASSWORD)];

        yield [new Dsn('sendinblue+api', 'default')];
    }
}
