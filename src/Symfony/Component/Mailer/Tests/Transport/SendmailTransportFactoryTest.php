<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\SendmailTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class SendmailTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new SendmailTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('smtp', 'sendmail'),
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
            new Dsn('smtp', 'sendmail'),
            new SendmailTransport(null, $this->getDispatcher(), $this->getLogger()),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('http', 'sendmail'),
            'The "http" scheme is not supported for mailer "sendmail". Supported schemes are: "smtp".',
        ];
    }
}
