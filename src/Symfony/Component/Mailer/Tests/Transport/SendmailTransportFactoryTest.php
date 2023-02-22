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

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\SendmailTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class SendmailTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new SendmailTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('sendmail+smtp', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('sendmail+smtp', 'default'),
            new SendmailTransport(null, null, new NullLogger()),
        ];

        yield [
            new Dsn('sendmail+smtp', 'default', null, null, null, ['command' => '/usr/sbin/sendmail -oi -t']),
            new SendmailTransport('/usr/sbin/sendmail -oi -t', null, new NullLogger()),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('sendmail+http', 'default'),
            'The "sendmail+http" scheme is not supported; supported schemes for mailer "sendmail" are: "sendmail", "sendmail+smtp".',
        ];
    }
}
