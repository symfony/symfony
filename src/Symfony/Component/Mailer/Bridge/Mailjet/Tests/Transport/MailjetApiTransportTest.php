<?php

namespace Symfony\Component\Mailer\Bridge\Mailjet\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
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

    public function testSendSuccess()
    {
        $json = json_encode([
            'Messages' => [
                'foo' => 'bar',
            ],
        ]);

        $responseHeaders = [
            'x-mj-request-guid' => ['baz'],
        ];

        $response = new MockResponse($json, ['response_headers' => $responseHeaders]);

        $client = new MockHttpClient($response);

        $transport = new MailjetApiTransport(self::USER, self::PASSWORD, $client);

        $email = new Email();
        $email
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->text('foobar');

        $sentMessage = $transport->send($email);
        $this->assertInstanceOf(SentMessage::class, $sentMessage);
        $this->assertSame('baz', $sentMessage->getMessageId());
    }

    public function testSendWithDecodingException()
    {
        $response = new MockResponse('cannot-be-decoded');

        $client = new MockHttpClient($response);

        $transport = new MailjetApiTransport(self::USER, self::PASSWORD, $client);

        $email = new Email();
        $email
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->text('foobar');

        $this->expectExceptionObject(
            new HttpTransportException('Unable to send an email: "cannot-be-decoded" (code 200).', $response)
        );

        $transport->send($email);
    }

    public function testSendWithTransportException()
    {
        $response = new MockResponse('', ['error' => 'foo']);

        $client = new MockHttpClient($response);

        $transport = new MailjetApiTransport(self::USER, self::PASSWORD, $client);

        $email = new Email();
        $email
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->text('foobar');

        $this->expectExceptionObject(
            new HttpTransportException('Could not reach the remote Mailjet server.', $response)
        );

        $transport->send($email);
    }

    public function testSendWithBadRequestResponse()
    {
        $json = json_encode([
            'Messages' => [
                [
                    'Errors' => [
                        [
                            'ErrorIdentifier' => '8e28ac9c-1fd7-41ad-825f-1d60bc459189',
                            'ErrorCode' => 'mj-0005',
                            'StatusCode' => 400,
                            'ErrorMessage' => 'The To is mandatory but missing from the input',
                            'ErrorRelatedTo' => ['To'],
                        ],
                    ],
                    'Status' => 'error',
                ],
            ],
        ]);

        $response = new MockResponse($json, ['http_code' => 400]);

        $client = new MockHttpClient($response);

        $transport = new MailjetApiTransport(self::USER, self::PASSWORD, $client);

        $email = new Email();
        $email
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->text('foobar');

        $this->expectExceptionObject(
            new HttpTransportException('Unable to send an email: "The To is mandatory but missing from the input" (code 400).', $response)
        );

        $transport->send($email);
    }

    public function testSendWithNoErrorMessageBadRequestResponse()
    {
        $response = new MockResponse('response-content', ['http_code' => 400]);

        $client = new MockHttpClient($response);

        $transport = new MailjetApiTransport(self::USER, self::PASSWORD, $client);

        $email = new Email();
        $email
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->text('foobar');

        $this->expectExceptionObject(
            new HttpTransportException('Unable to send an email: "response-content" (code 400).', $response)
        );

        $transport->send($email);
    }

    /**
     * @dataProvider getMalformedResponse
     */
    public function testSendWithMalformedResponse(array $body)
    {
        $json = json_encode($body);

        $response = new MockResponse($json);

        $client = new MockHttpClient($response);

        $transport = new MailjetApiTransport(self::USER, self::PASSWORD, $client);

        $email = new Email();
        $email
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->text('foobar');

        $this->expectExceptionObject(
            new HttpTransportException(sprintf('Unable to send an email: "%s" malformed api response.', $json), $response)
        );

        $transport->send($email);
    }

    public function getMalformedResponse(): \Generator
    {
        yield 'Missing Messages key' => [
            [
                'foo' => 'bar',
            ],
        ];

        yield 'Messages is not an array' => [
            [
                'Messages' => 'bar',
            ],
        ];

        yield 'Messages is an empty array' => [
            [
                'Messages' => [],
            ],
        ];
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
