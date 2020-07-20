<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Tests\Transport;

use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkSmtpTransport;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class PostmarkTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new PostmarkTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('postmark+api', 'default'),
            true,
        ];

        yield [
            new Dsn('postmark', 'default'),
            true,
        ];

        yield [
            new Dsn('postmark+smtp', 'default'),
            true,
        ];

        yield [
            new Dsn('postmark+smtps', 'default'),
            true,
        ];

        yield [
            new Dsn('postmark+smtp', 'example.com'),
            true,
        ];
    }

    public function createProvider(): iterable
    {
        $dispatcher = $this->getDispatcher();
        $logger = $this->getLogger();

        yield [
            new Dsn('postmark+api', 'default', self::USER),
            new PostmarkApiTransport(self::USER, $this->getClient(), $dispatcher, $logger),
        ];

        yield [
            new Dsn('postmark+api', 'example.com', self::USER, '', 8080),
            (new PostmarkApiTransport(self::USER, $this->getClient(), $dispatcher, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('postmark', 'default', self::USER),
            new PostmarkSmtpTransport(self::USER, $dispatcher, $logger),
        ];

        yield [
            new Dsn('postmark+smtp', 'default', self::USER),
            new PostmarkSmtpTransport(self::USER, $dispatcher, $logger),
        ];

        yield [
            new Dsn('postmark+smtps', 'default', self::USER),
            new PostmarkSmtpTransport(self::USER, $dispatcher, $logger),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('postmark+foo', 'default', self::USER),
            'The "postmark+foo" scheme is not supported; supported schemes for mailer "postmark" are: "postmark", "postmark+api", "postmark+smtp", "postmark+smtps".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('postmark+api', 'default')];
    }
}
