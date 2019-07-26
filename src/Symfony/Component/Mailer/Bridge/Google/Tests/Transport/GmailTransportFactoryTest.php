<?php

namespace Symfony\Component\Mailer\Bridge\Google\Tests\Transport;

use Symfony\Component\Mailer\Bridge\Google\Transport\GmailSmtpTransport;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class GmailTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new GmailTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('smtp', 'gmail'),
            true,
        ];

        yield [
            new Dsn('smtp', 'example.com'),
            false,
        ];
    }

    public function createProvider(): iterable
    {
        yield [
            new Dsn('smtp', 'gmail', self::USER, self::PASSWORD),
            new GmailSmtpTransport(self::USER, self::PASSWORD, $this->getDispatcher(), $this->getLogger()),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('foo', 'gmail', self::USER, self::PASSWORD),
            'The "foo" scheme is not supported for mailer "gmail". Supported schemes are: "smtp".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('smtp', 'gmail', self::USER)];

        yield [new Dsn('smtp', 'gmail', null, self::PASSWORD)];
    }
}
