<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailjet\Tests\Transport;

use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetSmtpTransport;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class MailjetTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new MailjetTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('mailjet', 'default'),
            true,
        ];

        yield [
            new Dsn('mailjet+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('mailjet+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('mailjet+smtp', 'example.com'),
            true,
        ];
    }

    public function createProvider(): iterable
    {
        $dispatcher = $this->getDispatcher();
        $logger = $this->getLogger();

        yield [
            new Dsn('mailjet', 'default', self::USER, self::PASSWORD),
            new MailjetSmtpTransport(self::USER, self::PASSWORD, $dispatcher, $logger),
        ];

        yield [
            new Dsn('mailjet+smtp', 'default', self::USER, self::PASSWORD),
            new MailjetSmtpTransport(self::USER, self::PASSWORD, $dispatcher, $logger),
        ];

        yield [
            new Dsn('mailjet+smtps', 'default', self::USER, self::PASSWORD),
            new MailjetSmtpTransport(self::USER, self::PASSWORD, $dispatcher, $logger),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('mailjet+foo', 'mailjet', self::USER, self::PASSWORD),
            'The "mailjet+foo" scheme is not supported; supported schemes for mailer "mailjet" are: "mailjet", "mailjet+smtp", "mailjet+smtps".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('mailjet+smtp', 'default')];
    }
}
