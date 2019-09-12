<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Tests\Transport;

use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillApiTransport;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillHttpTransport;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillSmtpTransport;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
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
            new Dsn('mandrill', 'default'),
            true,
        ];

        yield [
            new Dsn('mandrill+api', 'default'),
            true,
        ];

        yield [
            new Dsn('mandrill+https', 'default'),
            true,
        ];

        yield [
            new Dsn('mandrill+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('mandrill+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('mandrill+smtp', 'example.com'),
            true,
        ];
    }

    public function createProvider(): iterable
    {
        $client = $this->getClient();
        $dispatcher = $this->getDispatcher();
        $logger = $this->getLogger();

        yield [
            new Dsn('mandrill+api', 'default', self::USER),
            new MandrillApiTransport(self::USER, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('mandrill+api', 'example.com', self::USER, '', 8080),
            (new MandrillApiTransport(self::USER, $client, $dispatcher, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('mandrill', 'default', self::USER),
            new MandrillHttpTransport(self::USER, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('mandrill+https', 'default', self::USER),
            new MandrillHttpTransport(self::USER, $client, $dispatcher, $logger),
        ];

        yield [
            new Dsn('mandrill+https', 'example.com', self::USER, '', 8080),
            (new MandrillHttpTransport(self::USER, $client, $dispatcher, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('mandrill+smtp', 'default', self::USER, self::PASSWORD),
            new MandrillSmtpTransport(self::USER, self::PASSWORD, $dispatcher, $logger),
        ];

        yield [
            new Dsn('mandrill+smtps', 'default', self::USER, self::PASSWORD),
            new MandrillSmtpTransport(self::USER, self::PASSWORD, $dispatcher, $logger),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('mandrill+foo', 'default', self::USER),
            'The "mandrill+foo" scheme is not supported; supported schemes for mailer "mandrill" are: "mandrill", "mandrill+api", "mandrill+https", "mandrill+smtp", "mandrill+smtps".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('mandrill+api', 'default')];

        yield [new Dsn('mandrill+smtp', 'default', self::USER)];
    }
}
