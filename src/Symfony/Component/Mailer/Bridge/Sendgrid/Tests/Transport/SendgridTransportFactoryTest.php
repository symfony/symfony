<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Tests\Transport;

use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridApiTransport;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridSmtpTransport;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
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
            new Dsn('sendgrid+api', 'default'),
            true,
        ];

        yield [
            new Dsn('sendgrid', 'default'),
            true,
        ];

        yield [
            new Dsn('sendgrid+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('sendgrid+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('sendgrid+smtp', 'example.com'),
            true,
        ];
    }

    public function createProvider(): iterable
    {
        $dispatcher = $this->getDispatcher();
        $logger = $this->getLogger();

        yield [
            new Dsn('sendgrid+api', 'default', self::USER),
            new SendgridApiTransport(self::USER, $this->getClient(), $dispatcher, $logger),
        ];

        yield [
            new Dsn('sendgrid+api', 'example.com', self::USER, '', 8080),
            (new SendgridApiTransport(self::USER, $this->getClient(), $dispatcher, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('sendgrid', 'default', self::USER),
            new SendgridSmtpTransport(self::USER, $dispatcher, $logger),
        ];

        yield [
            new Dsn('sendgrid+smtp', 'default', self::USER),
            new SendgridSmtpTransport(self::USER, $dispatcher, $logger),
        ];

        yield [
            new Dsn('sendgrid+smtps', 'default', self::USER),
            new SendgridSmtpTransport(self::USER, $dispatcher, $logger),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('sendgrid+foo', 'sendgrid', self::USER),
            'The "sendgrid+foo" scheme is not supported; supported schemes for mailer "sendgrid" are: "sendgrid", "sendgrid+api", "sendgrid+smtp", "sendgrid+smtps".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('sendgrid+api', 'default')];
    }
}
