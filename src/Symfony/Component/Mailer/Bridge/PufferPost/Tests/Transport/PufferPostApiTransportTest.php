<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\PufferPost\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\PufferPost\Transport\PufferPostApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\RemoteTemplateEmail;
use Symfony\Component\Mailer\Transport\RemoteTemplateTransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class PufferPostApiTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(PufferPostApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new PufferPostApiTransport('KEY'),
                'pufferpost+api://pufferpost.com',
            ],
            [
                (new PufferPostApiTransport('KEY'))->setHost('example.com'),
                'pufferpost+api://example.com',
            ],
            [
                (new PufferPostApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'pufferpost+api://example.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $email = new Email();
        $email->from(new Address('foo@example.com', 'Ms. Foo Bar'))
            ->to(new Address('bar@example.com', 'Mr. Recipient'))
            ->cc('cc@example.com')
            ->bcc('baz@example.com')
            ->replyTo(new Address('reply@example.com', 'Ms. Reply'))
            ->subject('Hello!')
            ->text('Hello There!')
            ->html('<p>Hello There!</p>');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://pufferpost.com/api/v1/messages/batch', $url);
            $this->assertContains('Authorization: Bearer KEY', $options['headers']);

            $body = json_decode($options['body'], true);
            $this->assertCount(1, $body['messages']);
            $message = $body['messages'][0];
            $this->assertSame('foo@example.com', $message['from']);
            $this->assertSame('bar@example.com', $message['to']);
            $this->assertSame(['cc@example.com'], $message['cc']);
            $this->assertSame(['baz@example.com'], $message['bcc']);
            $this->assertSame('reply@example.com', $message['replyTo']);
            $this->assertSame('Hello!', $message['subject']);
            $this->assertSame('Hello There!', $message['text']);
            $this->assertSame('<p>Hello There!</p>', $message['html']);

            return new JsonMockResponse(['data' => [['index' => 0, 'status' => 'accepted', 'id' => 'msg_foobar']]], ['http_code' => 200]);
        });

        $transport = new PufferPostApiTransport('KEY', $client);
        $sentMessage = $transport->send($email);

        $this->assertSame('msg_foobar', $sentMessage->getMessageId());
    }

    public function testSendFansOutOverEveryToRecipient()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('one@example.com', 'two@example.com')
            ->cc('cc@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);
            $this->assertCount(2, $body['messages']);
            $this->assertSame('one@example.com', $body['messages'][0]['to']);
            $this->assertSame('two@example.com', $body['messages'][1]['to']);
            // Cc rides on the first message only: each item is an independent email, so repeating
            // it would deliver a copy per recipient.
            $this->assertSame(['cc@example.com'], $body['messages'][0]['cc']);
            $this->assertArrayNotHasKey('cc', $body['messages'][1]);

            return new JsonMockResponse(['data' => [
                ['index' => 0, 'status' => 'accepted', 'id' => 'msg_1'],
                ['index' => 1, 'status' => 'accepted', 'id' => 'msg_2'],
            ]], ['http_code' => 200]);
        });

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testHonoursAnEnvelopeThatRedirectsAwayFromTheHeaders()
    {
        // framework.mailer.envelope.recipients rewrites the envelope and not the headers, so a
        // staging redirect must win over the To header or real customers receive the mail.
        $email = (new Email())
            ->from('foo@example.com')
            ->to('customer1@example.com', 'customer2@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $envelope = new Envelope(new Address('foo@example.com'), [new Address('dev@example.com')]);

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $messages = json_decode($options['body'], true)['messages'];

            $this->assertCount(1, $messages);
            $this->assertSame('dev@example.com', $messages[0]['to']);

            return new JsonMockResponse(['data' => [['index' => 0, 'status' => 'accepted', 'id' => 'msg_1']]], ['http_code' => 200]);
        });

        (new PufferPostApiTransport('KEY', $client))->send($email, $envelope);
    }

    public function testSendsABccOnlyMessageOncePerRecipient()
    {
        // No To header at all: every envelope recipient becomes its own message, and Bcc is not
        // repeated onto each one.
        $email = (new Email())
            ->from('foo@example.com')
            ->bcc('x@example.com', 'y@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $messages = json_decode($options['body'], true)['messages'];

            $this->assertCount(2, $messages);
            $this->assertSame(['x@example.com', 'y@example.com'], array_column($messages, 'to'));
            $this->assertArrayNotHasKey('bcc', $messages[0]);
            $this->assertArrayNotHasKey('bcc', $messages[1]);

            return new JsonMockResponse(['data' => [
                ['index' => 0, 'status' => 'accepted', 'id' => 'msg_1'],
                ['index' => 1, 'status' => 'accepted', 'id' => 'msg_2'],
            ]], ['http_code' => 200]);
        });

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testSendsACcOnlyMessageOncePerRecipient()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->cc('cc1@example.com', 'cc2@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $messages = json_decode($options['body'], true)['messages'];

            $this->assertCount(2, $messages);
            $this->assertSame(['cc1@example.com', 'cc2@example.com'], array_column($messages, 'to'));
            $this->assertArrayNotHasKey('cc', $messages[0]);

            return new JsonMockResponse(['data' => [
                ['index' => 0, 'status' => 'accepted', 'id' => 'msg_1'],
                ['index' => 1, 'status' => 'accepted', 'id' => 'msg_2'],
            ]], ['http_code' => 200]);
        });

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testDropsCopyRecipientsTheEnvelopeNoLongerCarries()
    {
        // A redirected envelope must not keep mailing the original Cc.
        $email = (new Email())
            ->from('foo@example.com')
            ->to('customer@example.com')
            ->cc('cc@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $envelope = new Envelope(new Address('foo@example.com'), [new Address('dev@example.com')]);

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $messages = json_decode($options['body'], true)['messages'];

            $this->assertCount(1, $messages);
            $this->assertSame('dev@example.com', $messages[0]['to']);
            $this->assertArrayNotHasKey('cc', $messages[0]);

            return new JsonMockResponse(['data' => [['index' => 0, 'status' => 'accepted', 'id' => 'msg_1']]], ['http_code' => 200]);
        });

        (new PufferPostApiTransport('KEY', $client))->send($email, $envelope);
    }

    public function testSendsAStoredTemplateInsteadOfTheBody()
    {
        $email = (new RemoteTemplateEmail())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->template('tpl_welcome', ['name' => 'Jane']);

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $message = json_decode($options['body'], true)['messages'][0];

            $this->assertSame('tpl_welcome', $message['templateId']);
            $this->assertSame(['name' => 'Jane'], $message['data']);
            // The API refuses a message carrying both a template and inline content.
            $this->assertArrayNotHasKey('subject', $message);
            $this->assertArrayNotHasKey('html', $message);
            $this->assertArrayNotHasKey('text', $message);
            $this->assertArrayNotHasKey('headers', $message);

            return new JsonMockResponse(['data' => [['index' => 0, 'status' => 'accepted', 'id' => 'msg_1']]], ['http_code' => 200]);
        });

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testSendsTheRemainingProviderOptions()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $email->getHeaders()->addTextHeader('X-PufferPost-Metadata', '{"order_id":"o_9"}');
        $email->getHeaders()->addTextHeader('X-PufferPost-Unsubscribe-Group', 'receipts');
        $email->getHeaders()->addTextHeader('X-PufferPost-Locale', 'nl');
        $email->getHeaders()->addTextHeader('X-PufferPost-Timezone', 'Europe/Amsterdam');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $message = json_decode($options['body'], true)['messages'][0];

            $this->assertSame(['order_id' => 'o_9'], $message['metadata']);
            $this->assertSame('receipts', $message['unsubscribeGroup']);
            $this->assertSame('nl', $message['locale']);
            $this->assertSame('Europe/Amsterdam', $message['timezone']);

            return new JsonMockResponse(['data' => [['index' => 0, 'status' => 'accepted', 'id' => 'msg_1']]], ['http_code' => 200]);
        });

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testRejectsMalformedMetadata()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $email->getHeaders()->addTextHeader('X-PufferPost-Metadata', 'not json');

        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse(['data' => []], ['http_code' => 200]));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('must contain a JSON object');

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testRejectsASubjectAlongsideATemplate()
    {
        $email = (new RemoteTemplateEmail())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('overridden')
            ->template('tpl_welcome');

        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse(['data' => []], ['http_code' => 200]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not support overriding the subject of a template');

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testAcceptsAnEmailRenderedFromARemoteTemplate()
    {
        // AbstractTransport refuses a RemoteTemplateEmail unless the transport declares support.
        $this->assertInstanceOf(RemoteTemplateTransportInterface::class, new PufferPostApiTransport('KEY'));
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
            $body = json_decode($options['body'], true);
            $attachment = $body['messages'][0]['attachments'][0];

            $this->assertSame('attachment.txt', $attachment['filename']);
            $this->assertSame('text/plain', $attachment['contentType']);
            $this->assertSame(base64_encode('body'), $attachment['content']);

            return new JsonMockResponse(['data' => [['index' => 0, 'status' => 'accepted', 'id' => 'msg_1']]], ['http_code' => 200]);
        });

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testSendWithInlineAttachmentThrows()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!')
            ->addPart((new DataPart('body', 'logo.png', 'image/png'))->asInline());

        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse(['data' => []], ['http_code' => 200]));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('PufferPost does not support inline (cid-embedded) attachments');

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testKeepsOnlyTheFirstReplyToAddress()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->replyTo('a@example.com', 'b@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $message = json_decode($options['body'], true)['messages'][0];

            // The headers map is allow-listed to X- names, so extra reply-to addresses cannot be
            // smuggled through it; the first is used.
            $this->assertSame('a@example.com', $message['replyTo']);
            $this->assertArrayNotHasKey('headers', $message);

            return new JsonMockResponse(['data' => [['index' => 0, 'status' => 'accepted', 'id' => 'msg_1']]], ['http_code' => 200]);
        });

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testSendWithCustomHeader()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $email->getHeaders()->addTextHeader('X-Campaign', 'welcome');
        $email->getHeaders()->addTextHeader('In-Reply-To', '<prev@example.com>');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $message = json_decode($options['body'], true)['messages'][0];

            $this->assertSame('welcome', $message['headers']['X-Campaign']);
            // Only X- names are forwarded; anything else the API would refuse.
            $this->assertArrayNotHasKey('Subject', $message['headers']);
            $this->assertArrayNotHasKey('In-Reply-To', $message['headers']);

            return new JsonMockResponse(['data' => [['index' => 0, 'status' => 'accepted', 'id' => 'msg_1']]], ['http_code' => 200]);
        });

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testSendWithCustomEnvelopeSender()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('bar@example.com')]);

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $message = json_decode($options['body'], true)['messages'][0];

            // The From header is the visible sender the API verifies, not the envelope sender.
            $this->assertSame('foo@example.com', $message['from']);
            $this->assertSame('bar@example.com', $message['to']);

            return new JsonMockResponse(['data' => [['index' => 0, 'status' => 'accepted', 'id' => 'msg_1']]], ['http_code' => 200]);
        });

        (new PufferPostApiTransport('KEY', $client))->send($email, $envelope);
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse(
            ['error' => ['code' => 'sending_paused', 'message' => 'Sending is paused for this workspace.']],
            ['http_code' => 403],
        ));

        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: Sending is paused for this workspace. (code 403).');

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }

    public function testSendThrowsWhenABatchItemIsRejected()
    {
        // A batch answers 200 even when a message is refused, so the rejection must still surface.
        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse(['data' => [
            ['index' => 0, 'status' => 'error', 'error' => ['code' => 'recipient_suppressed', 'message' => 'The recipient is suppressed.']],
        ]], ['http_code' => 200]));

        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('Hello!')
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: The recipient is suppressed. (code 200).');

        (new PufferPostApiTransport('KEY', $client))->send($email);
    }
}
