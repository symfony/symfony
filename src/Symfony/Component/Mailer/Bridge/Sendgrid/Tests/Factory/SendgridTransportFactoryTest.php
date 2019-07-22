<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Tests\Factory;

use Symfony\Component\Mailer\Bridge\Sendgrid;
use Symfony\Component\Mailer\Bridge\Sendgrid\Factory\SendgridTransportFactory;
use Symfony\Component\Mailer\Tests\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class SendgridTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new SendgridTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('api', 'sendgrid'),
            true,
        ];

        yield [
            new Dsn('smtp', 'sendgrid'),
            true,
        ];

        yield [
            new Dsn('smtp', 'example.com'),
            false,
        ];
    }

    public function createProvider(): iterable
    {
        $dispatcher = $this->getDispatcher();
        $logger = $this->getLogger();

        yield [
            new Dsn('api', 'sendgrid', self::USER),
            new Sendgrid\Http\Api\SendgridTransport(self::USER, $this->getClient(), $dispatcher, $logger),
        ];

        yield [
            new Dsn('smtp', 'sendgrid', self::USER),
            new Sendgrid\Smtp\SendgridTransport(self::USER, $dispatcher, $logger),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [new Dsn('foo', 'sendgrid', self::USER)];
    }
}
