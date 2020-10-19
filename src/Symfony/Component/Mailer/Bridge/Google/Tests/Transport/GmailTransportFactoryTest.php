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
            new Dsn('gmail', 'default'),
            true,
        ];

        yield [
            new Dsn('gmail+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('gmail+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('gmail+smtp', 'example.com'),
            true,
        ];
    }

    public function createProvider(): iterable
    {
        yield [
            new Dsn('gmail', 'default', self::USER, self::PASSWORD),
            new GmailSmtpTransport(self::USER, self::PASSWORD, $this->getDispatcher(), $this->getLogger()),
        ];

        yield [
            new Dsn('gmail+smtp', 'default', self::USER, self::PASSWORD),
            new GmailSmtpTransport(self::USER, self::PASSWORD, $this->getDispatcher(), $this->getLogger()),
        ];

        yield [
            new Dsn('gmail+smtps', 'default', self::USER, self::PASSWORD),
            new GmailSmtpTransport(self::USER, self::PASSWORD, $this->getDispatcher(), $this->getLogger()),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('gmail+foo', 'default', self::USER, self::PASSWORD),
            'The "gmail+foo" scheme is not supported; supported schemes for mailer "gmail" are: "gmail", "gmail+smtp", "gmail+smtps".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('gmail+smtp', 'default', self::USER)];

        yield [new Dsn('gmail+smtp', 'default', null, self::PASSWORD)];
    }
}
