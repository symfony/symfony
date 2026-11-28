<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Groundmail\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Groundmail\Transport\GroundmailApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\RemoteTemplateEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GroundmailApiTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(GroundmailApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): iterable
    {
        yield [
            new GroundmailApiTransport('KEY'),
            'groundmail+api://api.groundmail.dev',
        ];

        yield [
            (new GroundmailApiTransport('KEY'))->setHost('example.com'),
            'groundmail+api://example.com',
        ];

        yield [
            (new GroundmailApiTransport('KEY'))->setHost('example.com')->setPort(99),
            'groundmail+api://example.com:99',
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.groundmail.dev/emails', $url);
            $this->assertContains('Authorization: Bearer KEY', $options['headers']);
            $this->assertContains('Accept: application/json', $options['headers']);

            $this->assertSame([
                'from' => ['email' => 'fabpot@symfony.com', 'name' => 'Fabien'],
                'to' => ['Tony Stark <tony.stark@marvel.com>'],
                'cc' => ['cc@example.com'],
                'bcc' => ['bcc@example.com'],
                'reply_to' => ['reply1@example.com', 'Reply <reply2@example.com>'],
                'subject' => 'Hello!',
                'html' => '<p>Hello there!</p>',
                'text' => 'Hello there!',
                'attachments' => [
                    ['name' => 'invoice.pdf', 'content_type' => 'application/pdf', 'data' => base64_encode('%PDF')],
                ],
            ], json_decode($options['body'], true));

            return new JsonMockResponse(['id' => 'msg_01JC8Q7K2Z', 'status' => 'queued']);
        });

        $email = (new Email())
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->to(new Address('tony.stark@marvel.com', 'Tony Stark'))
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo('reply1@example.com', new Address('reply2@example.com', 'Reply'))
            ->subject('Hello!')
            ->text('Hello there!')
            ->html('<p>Hello there!</p>')
            ->addPart(new DataPart('%PDF', 'invoice.pdf', 'application/pdf'));

        $message = (new GroundmailApiTransport('KEY', $client))->send($email);

        $this->assertSame('msg_01JC8Q7K2Z', $message->getMessageId());
    }

    public function testSendWithCustomHostAndPort()
    {
        $client = new MockHttpClient(function (string $method, string $url): ResponseInterface {
            $this->assertSame('https://example.com:8080/emails', $url);

            return new JsonMockResponse(['id' => 'msg_1', 'status' => 'queued']);
        });

        (new GroundmailApiTransport('KEY', $client))->setHost('example.com')->setPort(8080)->send($this->createEmail());
    }

    public function testRecipientsComeFromTheEnvelope()
    {
        $email = $this->createEmail()
            ->to('customer1@example.com', 'customer2@example.com')
            ->cc('cc@example.com');

        // framework.mailer.envelope.recipients redirects the message: the To and Cc headers are left as they were
        $payload = $this->getPayload($email, new Envelope(new Address('fabpot@symfony.com'), [new Address('dev@example.com')]));

        $this->assertSame(['dev@example.com'], $payload['to']);
        $this->assertArrayNotHasKey('cc', $payload);
        $this->assertArrayNotHasKey('bcc', $payload);
    }

    public function testCcAndBccAreSentOnce()
    {
        $email = $this->createEmail()
            ->to('a@example.com', 'b@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com');

        $payload = $this->getPayload($email, Envelope::create($email));

        $this->assertSame(['a@example.com', 'b@example.com'], $payload['to']);
        $this->assertSame(['cc@example.com'], $payload['cc']);
        $this->assertSame(['bcc@example.com'], $payload['bcc']);
    }

    public function testMessageWithoutToIsRefused()
    {
        $email = (new Email())
            ->from('fabpot@symfony.com')
            ->bcc('x@example.com', 'y@example.com')
            ->text('Hello');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Groundmail API needs at least one "To" recipient.');

        $this->getPayload($email, Envelope::create($email));
    }

    public function testFromIsTheFromHeader()
    {
        $email = $this->createEmail()->sender('bounces@symfony.com');

        $payload = $this->getPayload($email, Envelope::create($email));

        $this->assertSame('fabpot@symfony.com', $payload['from']);
    }

    public function testDisplayNamesWithSpecialCharactersAreQuoted()
    {
        $email = $this->createEmail()
            ->to(new Address('ada@example.com', 'Lovelace, Ada'), new Address('bob@example.com', 'Bob "the" Builder'));

        $payload = $this->getPayload($email, Envelope::create($email));

        $this->assertSame(['"Lovelace, Ada" <ada@example.com>', '"Bob \"the\" Builder" <bob@example.com>'], $payload['to']);
    }

    public function testIdnDomainsAreEncoded()
    {
        $email = $this->createEmail()
            ->from(new Address('info@kältetechnik-xyz.de', 'Kältetechnik Xyz'))
            ->to(new Address('kältetechnik@kältetechnik-xyz.de', 'Kältetechnik Xyz'));

        $payload = $this->getPayload($email, Envelope::create($email));

        $this->assertSame(['email' => 'info@xn--kltetechnik-xyz-0kb.de', 'name' => 'Kältetechnik Xyz'], $payload['from']);
        $this->assertSame(['Kältetechnik Xyz <kältetechnik@xn--kltetechnik-xyz-0kb.de>'], $payload['to']);
    }

    public function testInlineImages()
    {
        $email = $this->createEmail()
            ->html('<img src="cid:logo"><img src="cid:banner.png">')
            ->addPart((new DataPart('LOGO', 'logo', 'image/png'))->asInline())
            ->addPart(new DataPart('BANNER', 'banner.png', 'image/png'))
            ->addPart(new DataPart('%PDF', 'invoice.pdf', 'application/pdf'));

        $payload = $this->getPayload($email, Envelope::create($email));

        $this->assertSame([
            ['name' => 'logo', 'content_type' => 'image/png', 'data' => base64_encode('LOGO'), 'content_id' => 'logo'],
            ['name' => 'banner.png', 'content_type' => 'image/png', 'data' => base64_encode('BANNER'), 'content_id' => 'banner.png'],
            ['name' => 'invoice.pdf', 'content_type' => 'application/pdf', 'data' => base64_encode('%PDF')],
        ], $payload['attachments']);
    }

    public function testStreamedBodies()
    {
        $html = fopen('php://memory', 'r+');
        fwrite($html, '<p>0</p>');
        $text = fopen('php://memory', 'r+');
        fwrite($text, '0');

        $email = $this->createEmail()->html($html)->text($text);

        $payload = $this->getPayload($email, Envelope::create($email));

        $this->assertSame('<p>0</p>', $payload['html']);
        $this->assertSame('0', $payload['text']);
    }

    public function testCustomHeadersAndTags()
    {
        $email = $this->createEmail();
        $email->getHeaders()
            ->add(new MetadataHeader('order_id', 'ord_123'))
            ->addTextHeader('X-Groundmail-Tag', 'campaign = welcome')
            ->addTextHeader('X-Entity-ID', 'ent_1')
            ->addParameterizedHeader('X-Custom-Params', 'custom', ['param1' => 'foo']);

        $payload = $this->getPayload($email, Envelope::create($email));

        $this->assertSame([
            ['name' => 'order_id', 'value' => 'ord_123'],
            ['name' => 'campaign', 'value' => 'welcome'],
        ], $payload['tags']);
        $this->assertSame([
            'X-Entity-ID' => 'ent_1',
            'X-Custom-Params' => 'custom; param1=foo',
        ], $payload['headers']);
    }

    public function testTagWithoutValueIsRefused()
    {
        $email = $this->createEmail();
        $email->getHeaders()->addTextHeader('X-Groundmail-Tag', 'welcome');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "X-Groundmail-Tag" header must be written as "name=value", "welcome" given.');

        $this->getPayload($email, Envelope::create($email));
    }

    public function testGroundmailHeaders()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertContains('Idempotency-Key: welcome-123', $options['headers']);

            $body = json_decode($options['body'], true);
            $this->assertSame('product', $body['category']);
            $this->assertSame('2030-01-01T00:00:00Z', $body['scheduled_at']);
            $this->assertArrayNotHasKey('headers', $body);

            return new JsonMockResponse(['id' => 'msg_1', 'status' => 'scheduled']);
        });

        $email = $this->createEmail();
        $email->getHeaders()
            ->addTextHeader('X-Groundmail-Category', 'product')
            ->addTextHeader('X-Groundmail-Scheduled-At', '2030-01-01T00:00:00Z')
            ->addTextHeader('X-Groundmail-Idempotency-Key', 'welcome-123');

        (new GroundmailApiTransport('KEY', $client))->send($email);
    }

    public function testRemoteTemplate()
    {
        $email = (new RemoteTemplateEmail())
            ->from('fabpot@symfony.com')
            ->to('kevin@symfony.com')
            ->template('welcome', ['name' => 'Kevin']);

        $payload = $this->getPayload($email, Envelope::create($email));

        $this->assertSame(['id' => 'welcome', 'variables' => ['name' => 'Kevin']], $payload['template']);
        $this->assertArrayNotHasKey('subject', $payload);
        $this->assertArrayNotHasKey('html', $payload);
        $this->assertArrayNotHasKey('text', $payload);
    }

    public function testRemoteTemplateWithSubject()
    {
        $email = (new RemoteTemplateEmail())
            ->from('fabpot@symfony.com')
            ->to('kevin@symfony.com')
            ->subject('Welcome, Kevin')
            ->template('welcome');

        $payload = $this->getPayload($email, Envelope::create($email));

        $this->assertSame(['id' => 'welcome'], $payload['template']);
        $this->assertSame('Welcome, Kevin', $payload['subject']);
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(new JsonMockResponse([
            'error' => [
                'code' => 'forbidden',
                'message' => 'Sandbox sends are limited to owner@example.com.',
                'hint' => 'Verify your domain.',
                'docs_url' => 'https://docs.groundmail.dev/errors#forbidden',
            ],
        ], ['http_code' => 403]));

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: Sandbox sends are limited to owner@example.com. [forbidden] (code 403).');

        (new GroundmailApiTransport('KEY', $client))->send($this->createEmail());
    }

    public function testSendThrowsForStringErrorResponse()
    {
        $client = new MockHttpClient(new JsonMockResponse([
            'error' => 'Domain not found',
            'code' => 'not_found',
            'message' => 'Domain not found',
        ], ['http_code' => 404]));

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: Domain not found [not_found] (code 404).');

        (new GroundmailApiTransport('KEY', $client))->send($this->createEmail());
    }

    public function testSendThrowsForNonJsonResponse()
    {
        $client = new MockHttpClient(new MockResponse('Bad Gateway', ['http_code' => 502]));

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: Bad Gateway (code 502).');

        (new GroundmailApiTransport('KEY', $client))->send($this->createEmail());
    }

    private function createEmail(): Email
    {
        return (new Email())
            ->from('fabpot@symfony.com')
            ->to('kevin@symfony.com')
            ->subject('Hello!')
            ->text('Hello there!');
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        return (new \ReflectionMethod(GroundmailApiTransport::class, 'getPayload'))->invoke(new GroundmailApiTransport('KEY'), $email, $envelope);
    }
}
