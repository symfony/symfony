<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport;

class MailgunHttpTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MailgunHttpTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new MailgunHttpTransport('ACCESS_KEY', 'DOMAIN'),
                'mailgun+https://api.mailgun.net?domain=DOMAIN',
            ],
            [
                new MailgunHttpTransport('ACCESS_KEY', 'DOMAIN', 'us-east-1'),
                'mailgun+https://api.us-east-1.mailgun.net?domain=DOMAIN',
            ],
            [
                (new MailgunHttpTransport('ACCESS_KEY', 'DOMAIN'))->setHost('example.com'),
                'mailgun+https://example.com?domain=DOMAIN',
            ],
            [
                (new MailgunHttpTransport('ACCESS_KEY', 'DOMAIN'))->setHost('example.com')->setPort(99),
                'mailgun+https://example.com:99?domain=DOMAIN',
            ],
        ];
    }
}
