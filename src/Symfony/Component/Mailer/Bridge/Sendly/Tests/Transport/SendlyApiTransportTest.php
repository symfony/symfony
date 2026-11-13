<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendly\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Sendly\Transport\SendlyApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SendlyApiTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(SendlyApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new SendlyApiTransport('KEY'),
                'sendly+api://api.sendly.now',
            ],
            [
                (new SendlyApiTransport('KEY'))->setHost('example.com'),
                'sendly+api://example.com',
            ],
            [
                (new SendlyApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'sendly+api://example.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $email = (new Email())
            ->from(new Address('foo@example.com', 'Ms. Foo Bar'))
            ->to(new Address('bar@example.com', 'Mr. Recipient'))
            ->cc('cc@example.com')
            ->bcc('baz@example.com')
            ->replyTo('reply@example.com')
            ->subject('Hello!')
            ->html('<p>Hello There!</p>');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.sendly.now/api/v1/emails', $url);
            $this->assertContains('Authorization: Bearer KEY', $options['headers']);
            $this->assertSame([
                'to' => ['email' => 'bar@example.com', 'name' => 'Mr. Recipient'],
                'from' => ['email' => 'foo@example.com', 'name' => 'Ms. Foo Bar'],
                'body' => '<p>Hello There!</p>',
                'subject' => 'Hello!',
                'reply' => 'reply@example.com',
                'cc' => ['cc@example.com'],
                'bcc' => ['baz@example.com'],
            ], json_decode($options['body'], true));

            return $this->accepted('6f1c1b3e-9a7b-4f0b-8f43-0d2f5b0a7c11');
        });

        $sentMessage = (new SendlyApiTransport('KEY', $client))->send($email);

        $this->assertSame('6f1c1b3e-9a7b-4f0b-8f43-0d2f5b0a7c11', $sentMessage->getMessageId());
    }

    public function testAddressesWithoutNameAreSentAsPlainStrings()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $payload = json_decode($options['body'], true);

            $this->assertSame('foo@example.com', $payload['from']);
            $this->assertSame('bar@example.com', $payload['to']);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testBodyPrefersHtmlOverText()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!')
            ->html('<p>Hello There!</p>');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('<p>Hello There!</p>', json_decode($options['body'], true)['body']);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testBodyFallsBackToText()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('Hello There!', json_decode($options['body'], true)['body']);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testSendsOneRequestPerToRecipient()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('one@example.com', 'two@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $recipients = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$recipients): ResponseInterface {
            $recipients[] = json_decode($options['body'], true)['to'];

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);

        $this->assertSame(['one@example.com', 'two@example.com'], $recipients);
    }

    public function testCopiesAreAttachedToTheFirstRequestOnly()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('one@example.com', 'two@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $payloads = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$payloads): ResponseInterface {
            $payloads[] = json_decode($options['body'], true);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);

        $this->assertSame(['one@example.com', 'two@example.com'], array_column($payloads, 'to'));
        $this->assertSame(['cc@example.com'], $payloads[0]['cc']);
        $this->assertSame(['bcc@example.com'], $payloads[0]['bcc']);
        $this->assertArrayNotHasKey('cc', $payloads[1]);
        $this->assertArrayNotHasKey('bcc', $payloads[1]);
    }

    public function testMessageIdIsTheOneOfTheFirstRequest()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('one@example.com', 'two@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $ids = ['id-1', 'id-2'];
        $client = new MockHttpClient(fn (): ResponseInterface => $this->accepted(array_shift($ids)));

        $sentMessage = (new SendlyApiTransport('KEY', $client))->send($email);

        $this->assertSame('id-1', $sentMessage->getMessageId());
    }

    public function testRecipientsComeFromTheEnvelope()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('customer1@example.com', 'customer2@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $envelope = new Envelope(new Address('foo@example.com'), [new Address('dev@example.com')]);

        $recipients = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$recipients): ResponseInterface {
            $recipients[] = json_decode($options['body'], true)['to'];

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email, $envelope);

        $this->assertSame(['dev@example.com'], $recipients);
    }

    public function testCopiesTheEnvelopeDoesNotCarryAreDropped()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('customer@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $envelope = new Envelope(new Address('foo@example.com'), [new Address('dev@example.com')]);

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $payload = json_decode($options['body'], true);

            $this->assertArrayNotHasKey('cc', $payload);
            $this->assertArrayNotHasKey('bcc', $payload);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email, $envelope);
    }

    public function testBccOnlyEmailSendsOneRequestPerRecipient()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->bcc('x@example.com', 'y@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $payloads = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$payloads): ResponseInterface {
            $payloads[] = json_decode($options['body'], true);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);

        $this->assertSame(['x@example.com', 'y@example.com'], array_column($payloads, 'to'));
        $this->assertArrayNotHasKey('bcc', $payloads[0]);
        $this->assertArrayNotHasKey('bcc', $payloads[1]);
    }

    public function testCcOnlyEmailSendsOneRequestPerRecipient()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->cc('cc1@example.com', 'cc2@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $payloads = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$payloads): ResponseInterface {
            $payloads[] = json_decode($options['body'], true);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);

        $this->assertSame(['cc1@example.com', 'cc2@example.com'], array_column($payloads, 'to'));
        $this->assertArrayNotHasKey('cc', $payloads[0]);
    }

    public function testFromHeaderWinsOverTheEnvelopeSender()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('bar@example.com')]);

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('foo@example.com', json_decode($options['body'], true)['from']);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email, $envelope);
    }

    public function testEnvelopeSenderIsUsedWhenThereIsNoFromHeader()
    {
        $email = (new Email())
            ->sender('sender@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('sender@example.com', json_decode($options['body'], true)['from']);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testMultipleReplyToAddressesAreRefused()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->replyTo('a@example.com', 'b@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $client = new MockHttpClient(fn (): ResponseInterface => $this->accepted());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sendly accepts a single Reply-To address.');

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testCustomHeadersAreForwarded()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $email->getHeaders()->addTextHeader('X-Campaign', 'welcome');
        $email->getHeaders()->addTextHeader('In-Reply-To', '<prev@example.com>');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame(
                ['X-Campaign' => 'welcome', 'In-Reply-To' => '<prev@example.com>'],
                json_decode($options['body'], true)['headers'],
            );

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    #[DataProvider('getReservedHeaders')]
    public function testReservedHeadersAreNotForwarded(string $name)
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $email->getHeaders()->addTextHeader($name, 'value');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertArrayNotHasKey('headers', json_decode($options['body'], true));

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public static function getReservedHeaders(): iterable
    {
        yield ['MIME-Version'];
        yield ['Content-Type'];
        yield ['Content-Transfer-Encoding'];
        yield ['Content-Disposition'];
        yield ['DKIM-Signature'];
        yield ['DomainKey-Signature'];
        yield ['Received'];
        yield ['Authentication-Results'];
        yield ['List-Unsubscribe'];
        yield ['list-unsubscribe-post'];
        yield ['X-Sendly-Foo'];
        yield ['X-SES-CONFIGURATION-SET'];
        yield ['Resent-From'];
        yield ['ARC-Seal'];
    }

    public function testSenderReturnPathMessageIdAndDateAreNotForwarded()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->sender('sender@example.com')
            ->returnPath('bounce@example.com')
            ->date(new \DateTimeImmutable('2026-01-01 10:00:00'))
            ->subject('Hello!')
            ->text('Hello There!');
        $email->getHeaders()->addIdHeader('Message-ID', 'id@example.com');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertArrayNotHasKey('headers', json_decode($options['body'], true));

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testTagHeadersBecomeTags()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $email->getHeaders()->add(new TagHeader('welcome'));
        $email->getHeaders()->add(new TagHeader('onboarding'));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $payload = json_decode($options['body'], true);

            $this->assertSame(['welcome', 'onboarding'], $payload['tags']);
            $this->assertArrayNotHasKey('headers', $payload);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testMetadataHeadersAreNotForwarded()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $email->getHeaders()->add(new MetadataHeader('order', '42'));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertArrayNotHasKey('headers', json_decode($options['body'], true));

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testSendWithAttachment()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!')
            ->addPart(new DataPart('body', 'attachment.txt', 'text/plain'));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame([[
                'filename' => 'attachment.txt',
                'content' => base64_encode('body'),
                'contentType' => 'text/plain',
                'disposition' => 'attachment',
            ]], json_decode($options['body'], true)['attachments']);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testSendWithInlineAttachment()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->html('<img src="cid:logo@example.com">')
            ->addPart((new DataPart('body', 'logo.png', 'image/png'))->asInline()->setContentId('logo@example.com'));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame([[
                'filename' => 'logo.png',
                'content' => base64_encode('body'),
                'contentType' => 'image/png',
                'disposition' => 'inline',
                'contentId' => 'logo@example.com',
            ]], json_decode($options['body'], true)['attachments']);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testEmbeddedImageUsesItsNameAsContentId()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->html('<img src="cid:logo.png">')
            ->embed('body', 'logo.png', 'image/png');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $attachment = json_decode($options['body'], true)['attachments'][0];

            $this->assertSame('inline', $attachment['disposition']);
            $this->assertSame('logo.png', $attachment['contentId']);

            return $this->accepted();
        });

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testSendThrowsForProblemResponse()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new MockResponse(
            json_encode(['type' => 'https://docs.sendly.now/errors#domain_not_allowed', 'title' => 'Forbidden', 'status' => 403, 'code' => 'domain_not_allowed', 'detail' => 'The from address is not on a verified domain.']),
            ['http_code' => 403, 'response_headers' => ['content-type' => 'application/problem+json']],
        ));

        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: The from address is not on a verified domain. (code 403).');

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testSendThrowsForValidationErrors()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse([
            'code' => 'validation_error',
            'status' => 422,
            'detail' => 'The request body is invalid.',
            'errors' => [['pointer' => '/tags/0', 'code' => 'invalid', 'message' => 'Invalid tag']],
        ], ['http_code' => 422]));

        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: The request body is invalid. /tags/0: Invalid tag. (code 422).');

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testSendThrowsForErrorEnvelope()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse(
            ['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Invalid API key']],
            ['http_code' => 401],
        ));

        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: Invalid API key (code 401).');

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testSendThrowsForANonJsonResponse()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new MockResponse('Bad Gateway', ['http_code' => 502]));

        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: Bad Gateway (code 502).');

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testSendThrowsForAJsonResponseWithoutAMessage()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse(['foo' => 'bar'], ['http_code' => 500]));

        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: {"foo":"bar"} (code 500).');

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testSendThrowsWhenTheServerCannotBeReached()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new MockResponse('', ['error' => 'Connection refused']));

        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Could not reach the remote Sendly server.');

        (new SendlyApiTransport('KEY', $client))->send($email);
    }

    public function testSendStopsAtTheFirstFailedRecipient()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('one@example.com', 'two@example.com', 'three@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $requests = 0;
        $client = new MockHttpClient(function () use (&$requests): ResponseInterface {
            return 2 === ++$requests
                ? new JsonMockResponse(['code' => 'rate_limited', 'status' => 429, 'detail' => 'Too many requests.'], ['http_code' => 429])
                : $this->accepted();
        });

        try {
            (new SendlyApiTransport('KEY', $client))->send($email);
            $this->fail('An exception was expected.');
        } catch (HttpTransportException $e) {
            $this->assertSame(2, $requests);
        }
    }

    private function accepted(string $id = 'b6a2d9f0-1d54-4f0e-9d3b-0c4d6f1a2e55'): ResponseInterface
    {
        return new JsonMockResponse(['id' => $id, 'status' => 'PENDING', 'from' => 'foo@example.com', 'to' => 'bar@example.com'], ['http_code' => 202]);
    }
}
