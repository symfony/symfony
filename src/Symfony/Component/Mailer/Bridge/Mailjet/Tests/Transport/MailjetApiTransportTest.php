<?php

namespace Symfony\Component\Mailer\Bridge\Mailjet\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailjetApiTransportTest extends TestCase
{
    protected const USER = 'u$er';
    protected const PASSWORD = 'pa$s';

    /**
     * @dataProvider getTransportData
     */
    public function testToString(MailjetApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new MailjetApiTransport(self::USER, self::PASSWORD),
                'mailjet+api://api.mailjet.com',
            ],
            [
                (new MailjetApiTransport(self::USER, self::PASSWORD))->setHost('example.com'),
                'mailjet+api://example.com',
            ],
        ];
    }

    public function testPayloadFormat()
    {
        $email = (new Email())
            ->subject('Sending email to mailjet API')
            ->replyTo(new Address('qux@example.com', 'Qux'));
        $email->getHeaders()
            ->addTextHeader('X-authorized-header', 'authorized')
            ->addTextHeader('X-MJ-TemplateLanguage', 'forbidden'); // This header is forbidden
        $envelope = new Envelope(new Address('foo@example.com', 'Foo'), [new Address('bar@example.com', 'Bar'), new Address('baz@example.com', 'Baz')]);

        $transport = new MailjetApiTransport(self::USER, self::PASSWORD);
        $method = new \ReflectionMethod(MailjetApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('Messages', $payload);
        $this->assertNotEmpty($payload['Messages']);

        $message = $payload['Messages'][0];
        $this->assertArrayHasKey('Subject', $message);
        $this->assertEquals('Sending email to mailjet API', $message['Subject']);

        $this->assertArrayHasKey('Headers', $message);
        $headers = $message['Headers'];
        $this->assertArrayHasKey('X-authorized-header', $headers);
        $this->assertEquals('authorized', $headers['X-authorized-header']);
        $this->assertArrayNotHasKey('x-mj-templatelanguage', $headers);
        $this->assertArrayNotHasKey('X-MJ-TemplateLanguage', $headers);

        $this->assertArrayHasKey('From', $message);
        $sender = $message['From'];
        $this->assertArrayHasKey('Email', $sender);
        $this->assertEquals('foo@example.com', $sender['Email']);

        $this->assertArrayHasKey('To', $message);
        $recipients = $message['To'];
        $this->assertIsArray($recipients);
        $this->assertCount(2, $recipients);
        $this->assertEquals('bar@example.com', $recipients[0]['Email']);
        $this->assertEquals('', $recipients[0]['Name']); // For Recipients, even if the name is filled, it is empty
        $this->assertEquals('baz@example.com', $recipients[1]['Email']);
        $this->assertEquals('', $recipients[1]['Name']);

        $this->assertArrayHasKey('ReplyTo', $message);
        $replyTo = $message['ReplyTo'];
        $this->assertIsArray($replyTo);
        $this->assertEquals('qux@example.com', $replyTo['Email']);
        $this->assertEquals('Qux', $replyTo['Name']);
    }

    public function testReplyTo()
    {
        $from = 'foo@example.com';
        $to = 'bar@example.com';
        $email = new Email();
        $email
            ->from($from)
            ->to($to)
            ->replyTo(new Address('qux@example.com', 'Qux'), new Address('quux@example.com', 'Quux'));
        $envelope = new Envelope(new Address($from), [new Address($to)]);

        $transport = new MailjetApiTransport(self::USER, self::PASSWORD);
        $method = new \ReflectionMethod(MailjetApiTransport::class, 'getPayload');
        $method->setAccessible(true);

        $this->expectExceptionMessage('Mailjet\'s API only supports one Reply-To email, 2 given.');

        $method->invoke($transport, $email, $envelope);
    }
}
