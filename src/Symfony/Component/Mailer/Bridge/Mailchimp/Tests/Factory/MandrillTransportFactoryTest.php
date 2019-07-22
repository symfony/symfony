<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Tests\Factory;

use Symfony\Component\Mailer\Bridge\Mailchimp;
use Symfony\Component\Mailer\Bridge\Mailchimp\Factory\MandrillTransportFactory;
use Symfony\Component\Mailer\Tests\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class MandrillTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new MandrillTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('api', 'mandrill'),
            true,
        ];

        yield [
            new Dsn('http', 'mandrill'),
            true,
        ];

        yield [
            new Dsn('smtp', 'mandrill'),
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
            new Dsn('api', 'mandrill', self::USER),
            new Mailchimp\Http\Api\MandrillTransport(self::USER, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('http', 'mandrill', self::USER),
            new Mailchimp\Http\MandrillTransport(self::USER, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('smtp', 'mandrill', self::USER, self::PASSWORD),
            new Mailchimp\Smtp\MandrillTransport(self::USER, self::PASSWORD, $dispatcher, $logger),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [new Dsn('foo', 'mandrill', self::USER)];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('api', 'mandrill')];

        yield [new Dsn('smtp', 'mandrill', self::USER)];
    }
}
