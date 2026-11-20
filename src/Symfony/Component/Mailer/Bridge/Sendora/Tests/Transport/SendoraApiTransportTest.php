<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendora\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\Mailer\Bridge\Sendora\Transport\SendoraApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SendoraApiTransportTest extends TestCase
{
    private const MESSAGE_ID = '5f432ffd-c005-499b-aa3a-5b8a088ea20e';

    #[DataProvider('getTransportData')]
    public function testToString(SendoraApiTransport $transport, string $expected): void
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): iterable
    {
        yield [
            new SendoraApiTransport('KEY'),
            'sendora+api://api.sendora.se',
        ];

        yield [
            (new SendoraApiTransport('KEY'))->setHost('example.com'),
            'sendora+api://example.com',
        ];

        yield [
            (new SendoraApiTransport('KEY'))->setHost('example.com')->setPort(99),
            'sendora+api://example.com:99',
        ];
    }

    public function testSend(): void
    {
        $email = (new Email())
            ->from(new Address('no-reply@example.se', 'Example AB'))
            ->to(new Address('anna@example.com', 'Anna Andersson'))
            ->cc('bo@example.com')
            ->bcc('cecilia@example.com')
            ->replyTo(new Address('support@example.se', 'Example AB support'))
            ->subject('Your invoice')
            ->text('Your invoice is attached.')
            ->html('<p>Your invoice is attached.</p>')
            ->attach('%PDF-1.7', 'invoice.pdf', 'application/pdf');
        $email->getHeaders()
            ->addTextHeader('List-Unsubscribe', '<https://example.se/unsubscribe>')
            ->addTextHeader('X-Customer', '4711')
            ->add(new TagHeader('invoice'))
            ->add(new MetadataHeader('invoiceId', '2026-0912'));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.sendora.se/v1/email', $url);
            $this->assertContains('Authorization: Bearer KEY', $options['headers']);
            $this->assertMatchesRegularExpression('/^Idempotency-Key: [0-9a-f]{32}$/', $options['normalized_headers']['idempotency-key'][0]);

            $this->assertSame([
                'from' => ['email' => 'no-reply@example.se', 'name' => 'Example AB'],
                'to' => [['email' => 'anna@example.com', 'name' => 'Anna Andersson']],
                'cc' => [['email' => 'bo@example.com']],
                'bcc' => [['email' => 'cecilia@example.com']],
                'subject' => 'Your invoice',
                'text' => 'Your invoice is attached.',
                'html' => '<p>Your invoice is attached.</p>',
                'attachments' => [['name' => 'invoice.pdf', 'content' => base64_encode('%PDF-1.7'), 'contentType' => 'application/pdf']],
                'headers' => [
                    'Reply-To' => '"Example AB support" <support@example.se>',
                    'List-Unsubscribe' => '<https://example.se/unsubscribe>',
                    'X-Customer' => '4711',
                ],
                'metadata' => ['invoiceId' => '2026-0912'],
                'tag' => 'invoice',
            ], json_decode($options['body'], true));

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID, 'status' => 'accepted', 'submittedAt' => '2026-09-15T12:00:00.000Z', 'test' => false]);
        });

        $sentMessage = (new SendoraApiTransport('KEY', $client))->send($email);

        $this->assertSame(self::MESSAGE_ID, $sentMessage->getMessageId());
    }

    public function testARetryOfTheRequestKeepsItsIdempotencyKey(): void
    {
        $keys = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$keys): ResponseInterface {
            $keys[] = $options['normalized_headers']['idempotency-key'][0];

            return 1 === \count($keys) ? new MockResponse('', ['http_code' => 503]) : new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', new RetryableHttpClient($client, new GenericRetryStrategy(delayMs: 0), 1)))->send($this->createEmail());

        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1]);
    }

    public function testSendOnAStream(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('https://api.example.com:8080/v1/email', $url);
            $this->assertSame('3d1a2b4c-5e6f-4a7b-8c9d-0e1f2a3b4c5d', json_decode($options['body'], true)['streamId']);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', $client))->setHost('api.example.com')->setPort(8080)->setStream('3d1a2b4c-5e6f-4a7b-8c9d-0e1f2a3b4c5d')->send($this->createEmail());
    }

    public function testTheVisibleSenderIsTheFromAddressRatherThanTheEnvelopeSender(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame(['email' => 'no-reply@example.se'], json_decode($options['body'], true)['from']);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        $email = $this->createEmail()->returnPath('bounces@example.se');

        (new SendoraApiTransport('KEY', $client))->send($email, new Envelope(new Address('bounces@example.se'), [new Address('anna@example.com')]));
    }

    public function testOnlyTheRecipientsOfTheEnvelopeGetTheMessage(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame([['email' => 'staging@example.se']], $body['to']);
            $this->assertArrayNotHasKey('cc', $body);
            $this->assertArrayNotHasKey('bcc', $body);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        $email = $this->createEmail()->cc('bo@example.com')->bcc('cecilia@example.com');

        (new SendoraApiTransport('KEY', $client))->send($email, new Envelope(new Address('no-reply@example.se'), [new Address('staging@example.se')]));
    }

    public function testSendWithInlineImages(): void
    {
        $email = $this->createEmail()
            ->html('<img src="cid:logo"><img src="cid:banner@example.se">')
            ->addPart((new DataPart('logo-bytes', 'logo', 'image/png'))->asInline())
            ->addPart((new DataPart('banner-bytes', 'banner.png', 'image/png'))->asInline()->setContentId('banner@example.se'));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame('<img src="cid:logo"><img src="cid:banner@example.se">', $body['html']);
            $this->assertSame([
                ['name' => 'logo', 'content' => base64_encode('logo-bytes'), 'contentType' => 'image/png', 'contentId' => 'logo'],
                ['name' => 'banner.png', 'content' => base64_encode('banner-bytes'), 'contentType' => 'image/png', 'contentId' => 'banner@example.se'],
            ], $body['attachments']);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', $client))->send($email);
    }

    public function testAnImageKeepsTheNameThatTheHtmlRefersToOnceTheMimeBodyIsBuilt(): void
    {
        $email = $this->createEmail()
            ->html('<img src="cid:logo">')
            ->attach('logo-bytes', 'logo', 'image/png');
        // as a failed transport of a failover transport would, which gives the part a content id of its own
        $email->toString();

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame('<img src="cid:logo">', $body['html']);
            $this->assertSame('logo', $body['attachments'][0]['contentId']);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', $client))->send($email);
    }

    public function testAnAttachmentThatTheHtmlDoesNotReferToHasNoContentId(): void
    {
        $email = $this->createEmail()
            ->html('<p>Your invoice is attached.</p>')
            ->attach('%PDF-1.7', 'invoice.pdf', 'application/pdf');
        $email->toString();

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertArrayNotHasKey('contentId', json_decode($options['body'], true)['attachments'][0]);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', $client))->send($email);
    }

    public function testHeaderValuesAreSentUnfoldedAndUnencoded(): void
    {
        $unsubscribe = '<https://example.se/unsubscribe?token=0123456789abcdef0123456789abcdef0123456789abcdef>, <mailto:unsubscribe@example.se?subject=unsubscribe>';
        $email = $this->createEmail()->replyTo(new Address('support@example.se', 'Åsa på Example'));
        $email->getHeaders()
            ->addTextHeader('List-Unsubscribe', $unsubscribe)
            ->addTextHeader('X-Department', 'Försäljning')
            ->addIdHeader('In-Reply-To', 'thread-1@example.se');

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($unsubscribe): ResponseInterface {
            $this->assertSame([
                'Reply-To' => '"Åsa på Example" <support@example.se>',
                'List-Unsubscribe' => $unsubscribe,
                'X-Department' => 'Försäljning',
                'In-Reply-To' => '<thread-1@example.se>',
            ], json_decode($options['body'], true)['headers']);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', $client))->send($email);
    }

    public function testHeadersThatSendoraWritesItselfAreNotSent(): void
    {
        $email = $this->createEmail()->returnPath('bounces@example.se')->sender('robot@example.se');
        $email->getHeaders()->addIdHeader('Message-ID', 'mine@example.se')->addDateHeader('Date', new \DateTimeImmutable());

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertArrayNotHasKey('headers', json_decode($options['body'], true));

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', $client))->send($email);
    }

    public function testTagsAndMetadataAreReadFromTheirHeaders(): void
    {
        $email = $this->createEmail();
        $email->getHeaders()
            ->add(new TagHeader('invoice'))
            ->addTextHeader('X-Tag', 'invoice')
            ->addTextHeader('X-Metadata-Customer', '4711')
            ->add(new MetadataHeader('Order-Id', '1042'));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame('invoice', $body['tag']);
            $this->assertSame(['Customer' => '4711', 'Order-Id' => '1042'], $body['metadata']);
            $this->assertArrayNotHasKey('headers', $body);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', $client))->send($email);
    }

    public function testTwoDifferentTagsAreRefused(): void
    {
        $email = $this->createEmail();
        $email->getHeaders()->add(new TagHeader('invoice'))->add(new TagHeader('reminder'));

        $client = new MockHttpClient(function (): ResponseInterface {
            $this->fail('No request is sent.');
        });

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Sendora allows a single tag per email.');

        (new SendoraApiTransport('KEY', $client))->send($email);
    }

    public function testSendWithTrackingHeader(): void
    {
        $email = $this->createEmail();
        $email->getHeaders()->add(new TrackingHeader(false, true));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertFalse($body['trackOpens']);
            $this->assertTrue($body['trackLinks']);
            $this->assertArrayNotHasKey('headers', $body);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', $client))->send($email);
    }

    public function testSendLeavesTrackingToTheStreamWithoutTheHeader(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertArrayNotHasKey('trackOpens', $body);
            $this->assertArrayNotHasKey('trackLinks', $body);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', $client))->send($this->createEmail());
    }

    public function testSendWithBodiesAsStreams(): void
    {
        $text = fopen('php://memory', 'r+');
        fwrite($text, 'Your invoice is attached.');
        $html = fopen('php://memory', 'r+');
        fwrite($html, '<p>Your invoice is attached.</p>');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame('Your invoice is attached.', $body['text']);
            $this->assertSame('<p>Your invoice is attached.</p>', $body['html']);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', $client))->send($this->createEmail()->text($text)->html($html));
    }

    public function testEmptyPartsAreNotSent(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame('Your invoice is attached.', $body['text']);
            $this->assertArrayNotHasKey('html', $body);

            return new JsonMockResponse(['messageId' => self::MESSAGE_ID]);
        });

        (new SendoraApiTransport('KEY', $client))->send($this->createEmail()->html(''));
    }

    public function testSendThrowsForErrorResponse(): void
    {
        $client = new MockHttpClient(new JsonMockResponse([
            'error' => 'domain_not_verified',
            'message' => 'The From domain example.se is not a verified sending domain of this server.',
        ], ['http_code' => 422]));

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: The From domain example.se is not a verified sending domain of this server. (code 422).');

        (new SendoraApiTransport('KEY', $client))->send($this->createEmail());
    }

    public function testSendThrowsForAnAnswerThatIsNotJson(): void
    {
        $client = new MockHttpClient(new MockResponse('Bad Gateway', ['http_code' => 502]));

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: Bad Gateway (code 502).');

        (new SendoraApiTransport('KEY', $client))->send($this->createEmail());
    }

    public function testSendThrowsWhenSendoraCannotBeReached(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['error' => 'Connection refused']));

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Could not reach the remote Sendora server.');

        (new SendoraApiTransport('KEY', $client))->send($this->createEmail());
    }

    private function createEmail(): Email
    {
        return (new Email())
            ->from('no-reply@example.se')
            ->to('anna@example.com')
            ->subject('Your invoice')
            ->text('Your invoice is attached.');
    }
}
