<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailKite\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\MailKite\Transport\MailKiteSmtpTransport;

class MailKiteSmtpTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(MailKiteSmtpTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): iterable
    {
        yield [
            new MailKiteSmtpTransport('KEY'),
            'smtp://smtp.mailkite.dev:587',
        ];

        yield [
            new MailKiteSmtpTransport('KEY', true),
            'smtps://smtp.mailkite.dev',
        ];
    }

    public function testCredentials()
    {
        $transport = new MailKiteSmtpTransport('KEY');

        $this->assertSame('mailkite', $transport->getUsername());
        $this->assertSame('KEY', $transport->getPassword());
    }

    public function testSubmissionPortIsNotWrappedInImplicitTls()
    {
        $this->assertFalse((new MailKiteSmtpTransport('KEY'))->getStream()->isTLS());
        $this->assertTrue((new MailKiteSmtpTransport('KEY', true))->getStream()->isTLS());
    }
}
