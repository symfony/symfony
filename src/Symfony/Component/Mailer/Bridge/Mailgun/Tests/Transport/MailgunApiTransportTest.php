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
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailgunApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MailgunApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new MailgunApiTransport('ACCESS_KEY', 'DOMAIN'),
                'mailgun+api://api.mailgun.net?domain=DOMAIN',
            ],
            [
                new MailgunApiTransport('ACCESS_KEY', 'DOMAIN', 'us-east-1'),
                'mailgun+api://api.us-east-1.mailgun.net?domain=DOMAIN',
            ],
            [
                (new MailgunApiTransport('ACCESS_KEY', 'DOMAIN'))->setHost('example.com'),
                'mailgun+api://example.com?domain=DOMAIN',
            ],
            [
                (new MailgunApiTransport('ACCESS_KEY', 'DOMAIN'))->setHost('example.com')->setPort(99),
                'mailgun+api://example.com:99?domain=DOMAIN',
            ],
        ];
    }

    public function testCustomHeader()
    {
        $json = json_encode(['foo' => 'bar']);
        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Mailgun-Variables', $json);
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MailgunApiTransport('ACCESS_KEY', 'DOMAIN');
        $method = new \ReflectionMethod(MailgunApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('h:x-mailgun-variables', $payload);
        $this->assertEquals($json, $payload['h:x-mailgun-variables']);
    }
}
