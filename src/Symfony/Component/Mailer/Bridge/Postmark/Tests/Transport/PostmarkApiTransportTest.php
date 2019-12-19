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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class PostmarkApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(PostmarkApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new PostmarkApiTransport('KEY'),
                'postmark+api://api.postmarkapp.com',
            ],
            [
                (new PostmarkApiTransport('KEY'))->setHost('example.com'),
                'postmark+api://example.com',
            ],
            [
                (new PostmarkApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'postmark+api://example.com:99',
            ],
        ];
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new PostmarkApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('Headers', $payload);
        $this->assertCount(1, $payload['Headers']);

        $this->assertEquals(['Name' => 'foo', 'Value' => 'bar'], $payload['Headers'][0]);
    }

    public function testTagAndMetadataHeaders()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new PostmarkApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayNotHasKey('Headers', $payload);
        $this->assertArrayHasKey('Tag', $payload);
        $this->assertArrayHasKey('Metadata', $payload);

        $this->assertSame('password-reset', $payload['Tag']);
        $this->assertSame(['Color' => 'blue', 'Client-ID' => '12345'], $payload['Metadata']);
    }
}
