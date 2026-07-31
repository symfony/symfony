<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\TurboSmtp\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\TurboSmtp\Transport\TurboSmtpSmtpTransport;

class TurboSmtpSmtpTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(TurboSmtpSmtpTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): iterable
    {
        yield [
            new TurboSmtpSmtpTransport('pro.turbo-smtp.com', 'KEY', 'SECRET'),
            'smtp://pro.turbo-smtp.com:587',
        ];

        yield [
            new TurboSmtpSmtpTransport('pro.eu.turbo-smtp.com', 'KEY', 'SECRET'),
            'smtp://pro.eu.turbo-smtp.com:587',
        ];
    }

    public function testCredentials()
    {
        $transport = new TurboSmtpSmtpTransport('pro.turbo-smtp.com', 'KEY', 'SECRET');

        $this->assertSame('KEY', $transport->getUsername());
        $this->assertSame('SECRET', $transport->getPassword());
    }

    public function testSubmissionPortIsNotWrappedInImplicitTls()
    {
        $transport = new TurboSmtpSmtpTransport('pro.turbo-smtp.com', 'KEY', 'SECRET');

        $this->assertFalse($transport->getStream()->isTLS());
    }
}
