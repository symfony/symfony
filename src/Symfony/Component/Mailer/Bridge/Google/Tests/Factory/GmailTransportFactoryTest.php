<?php

namespace Symfony\Component\Mailer\Bridge\Google\Tests\Factory;

use Symfony\Component\Mailer\Bridge\Google\Factory\GmailTransportFactory;
use Symfony\Component\Mailer\Bridge\Google\Smtp\GmailTransport;
use Symfony\Component\Mailer\Tests\TransportFactoryTestCase;
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
            new GmailTransport(self::USER, self::PASSWORD, $this->getDispatcher(), $this->getLogger()),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [new Dsn('http', 'gmail', self::USER, self::PASSWORD)];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('smtp', 'gmail', self::USER)];

        yield [new Dsn('smtp', 'gmail', null, self::PASSWORD)];
    }
}
