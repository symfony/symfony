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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MandrillApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MandrillApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new MandrillApiTransport('KEY'),
                'mandrill+api://mandrillapp.com',
            ],
            [
                (new MandrillApiTransport('KEY'))->setHost('example.com'),
                'mandrill+api://example.com',
            ],
            [
                (new MandrillApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'mandrill+api://example.com:99',
            ],
        ];
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MandrillApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MandrillApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('headers', $payload['message']);
        $this->assertCount(1, $payload['message']['headers']);
        $this->assertEquals('foo: bar', $payload['message']['headers'][0]);
    }

    public function testTagAndMetadataHeaders()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MandrillApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MandrillApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayNotHasKey('headers', $payload['message']);
        $this->assertArrayHasKey('tags', $payload['message']);
        $this->assertSame(['password-reset'], $payload['message']['tags']);
        $this->assertArrayHasKey('metadata', $payload['message']);
        $this->assertSame(['Color' => 'blue', 'Client-ID' => '12345'], $payload['message']['metadata']);
    }

    public function testCanHaveMultipleTags()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('password-reset,user'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MandrillApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MandrillApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayNotHasKey('headers', $payload['message']);
        $this->assertArrayHasKey('tags', $payload['message']);
        $this->assertSame(['password-reset', 'user'], $payload['message']['tags']);
    }
}
