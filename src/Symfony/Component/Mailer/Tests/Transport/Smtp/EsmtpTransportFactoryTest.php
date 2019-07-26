<?php

namespace Symfony\Component\Mailer\Tests\Transport\Smtp;

use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class EsmtpTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new EsmtpTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('smtp', 'example.com'),
            true,
        ];

        yield [
            new Dsn('api', 'example.com'),
            false,
        ];
    }

    public function createProvider(): iterable
    {
        $eventDispatcher = $this->getDispatcher();
        $logger = $this->getLogger();

        $transport = new EsmtpTransport('example.com', 25, null, null, $eventDispatcher, $logger);

        yield [
            new Dsn('smtp', 'example.com'),
            $transport,
        ];

        $transport = new EsmtpTransport('example.com', 99, 'ssl', 'login', $eventDispatcher, $logger);
        $transport->setUsername(self::USER);
        $transport->setPassword(self::PASSWORD);

        yield [
            new Dsn('smtp', 'example.com', self::USER, self::PASSWORD, 99, ['encryption' => 'ssl', 'auth_mode' => 'login']),
            $transport,
        ];
    }
}
