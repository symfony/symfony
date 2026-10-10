<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\ZohoCpaas\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\ZohoCpaas\Transport\ZohoCpaasApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ZohoCpaasApiTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(ZohoCpaasApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): iterable
    {
        yield [
            new ZohoCpaasApiTransport('KEY'),
            'zohocpaas+api://cpaas.zoho.com',
        ];

        yield [
            (new ZohoCpaasApiTransport('KEY'))->setHost('cpaas.zoho.eu'),
            'zohocpaas+api://cpaas.zoho.eu',
        ];

        yield [
            (new ZohoCpaasApiTransport('KEY'))->setHost('example.com')->setPort(99),
            'zohocpaas+api://example.com:99',
        ];
    }

    public function testSend()
    {
        $email = (new Email())
            ->from(new Address('foo@example.com', 'Ms. Foo Bar'))
            ->to(new Address('bar@example.com', 'Mr. Recipient'))
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo(new Address('reply@example.com', 'Ms. Reply'), 'other@example.com')
            ->subject('An email')
            ->text('Test email body')
            ->html('<html lang="en"><body><p>Test email body</p></body></html>');
        $email->getHeaders()->addTextHeader('List-Unsubscribe', '<https://example.com/unsubscribe>');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://cpaas.zoho.eu/v1.1/email', $url);
            $this->assertContains('Authorization: Zoho-enczapikey KEY', $options['headers']);

            $this->assertSame([
                'from' => ['address' => 'foo@example.com', 'name' => 'Ms. Foo Bar'],
                'to' => [['email_address' => ['address' => 'bar@example.com', 'name' => 'Mr. Recipient']]],
                'cc' => [['email_address' => ['address' => 'cc@example.com']]],
                'bcc' => [['email_address' => ['address' => 'bcc@example.com']]],
                'reply_to' => [['address' => 'reply@example.com', 'name' => 'Ms. Reply'], ['address' => 'other@example.com']],
                'subject' => 'An email',
                'htmlbody' => '<html lang="en"><body><p>Test email body</p></body></html>',
                'textbody' => 'Test email body',
                'mime_headers' => ['List-Unsubscribe' => '<https://example.com/unsubscribe>'],
            ], json_decode($options['body'], true));

            return new JsonMockResponse([
                'data' => [['code' => 'EM_104', 'additional_info' => [], 'message' => 'Email request received']],
                'message' => 'OK',
                'request_id' => 'req-1234',
            ]);
        });

        $sentMessage = (new ZohoCpaasApiTransport('KEY', $client))->setHost('cpaas.zoho.eu')->send($email);

        $this->assertSame('req-1234', $sentMessage->getMessageId());
    }

    public function testSendAcceptsCreated()
    {
        // The live API answers a send with 201, the documentation shows 200
        $client = new MockHttpClient(new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-5678'], ['http_code' => 201]));

        $sentMessage = (new ZohoCpaasApiTransport('KEY', $client))->send((new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body'));

        $this->assertSame('req-5678', $sentMessage->getMessageId());
    }

    public function testSendWithAttachmentAndInlineImage()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->html('<html lang="en"><body><img src="cid:logo"></body></html>')
            ->addPart(new DataPart('some text', 'notes.txt', 'text/plain'))
            ->addPart((new DataPart('image-bytes', 'logo', 'image/png'))->asInline());

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame([[
                'content' => base64_encode('some text'),
                'mime_type' => 'text/plain',
                'name' => 'notes.txt',
            ]], $body['attachments']);
            $this->assertSame([[
                'content' => base64_encode('image-bytes'),
                'mime_type' => 'image/png',
                'name' => 'logo',
                'cid' => 'logo',
            ]], $body['inline_images']);

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    public function testSendWithTrackingHeader()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body');
        $email->getHeaders()->add(new TrackingHeader(false, true));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertFalse($body['track_opens']);
            $this->assertTrue($body['track_clicks']);
            $this->assertArrayNotHasKey('mime_headers', $body);

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    public function testSendLeavesTrackingToTheAgentWithoutTheHeader()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertArrayNotHasKey('track_opens', $body);
            $this->assertArrayNotHasKey('track_clicks', $body);

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    public function testSendWithCustomEnvelope()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame(['address' => 'sender@example.com'], $body['from']);
            $this->assertSame([['email_address' => ['address' => 'envelope@example.com']]], $body['to']);

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        $envelope = new Envelope(new Address('sender@example.com'), [new Address('envelope@example.com')]);

        (new ZohoCpaasApiTransport('KEY', $client))->send($email, $envelope);
    }

    public function testSendWithSeveralRecipients()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('one@example.com', 'two@example.com')
            ->cc('cc1@example.com', 'cc2@example.com')
            ->subject('An email')
            ->text('Test email body');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame([
                ['email_address' => ['address' => 'one@example.com']],
                ['email_address' => ['address' => 'two@example.com']],
            ], $body['to']);
            $this->assertSame([
                ['email_address' => ['address' => 'cc1@example.com']],
                ['email_address' => ['address' => 'cc2@example.com']],
            ], $body['cc']);
            $this->assertArrayNotHasKey('bcc', $body);

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    public function testSendKeepsDisplayNamesAsGiven()
    {
        $email = (new Email())
            ->from(new Address('foo@example.com', 'Müller, Hans "Hansi"'))
            ->to(new Address('bar@example.com', 'Zoë Öztürk'))
            ->subject('An email')
            ->text('Test email body');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame(['address' => 'foo@example.com', 'name' => 'Müller, Hans "Hansi"'], $body['from']);
            $this->assertSame([['email_address' => ['address' => 'bar@example.com', 'name' => 'Zoë Öztürk']]], $body['to']);

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    #[DataProvider('getBodies')]
    public function testSendCarriesOnlyTheBodiesTheMailHas(Email $email, array $expectedKeys, array $absentKeys)
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($expectedKeys, $absentKeys): ResponseInterface {
            $body = json_decode($options['body'], true);

            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $body);
            }
            foreach ($absentKeys as $key) {
                $this->assertArrayNotHasKey($key, $body);
            }

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    public static function getBodies(): iterable
    {
        $base = static fn (): Email => (new Email())->from('foo@example.com')->to('bar@example.com')->subject('An email');

        yield 'text only' => [$base()->text('Text'), ['textbody'], ['htmlbody']];
        yield 'html only' => [$base()->html('<p>Html</p>'), ['htmlbody'], ['textbody']];
    }

    #[DataProvider('getMailsWithEmbeddedImages')]
    public function testSendGivesAnInlineImageTheCidTheHtmlRefersTo(Email $email, string $expectedCid, bool $buildBodyFirst)
    {
        if ($buildBodyFirst) {
            // A listener, such as a DKIM signer, builds the MIME body first, and Symfony then gives the part a content id
            $email->getBody();
        }

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($expectedCid): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertArrayNotHasKey('attachments', $body);
            $this->assertCount(1, $body['inline_images']);
            $this->assertSame($expectedCid, $body['inline_images'][0]['cid']);
            $this->assertMatchesRegularExpression('/\bcid:'.preg_quote($expectedCid, '/').'["\s>]/', $body['htmlbody']);

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    public static function getMailsWithEmbeddedImages(): iterable
    {
        $base = static fn (string $html): Email => (new Email())->from('foo@example.com')->to('bar@example.com')->subject('An email')->html($html);

        foreach ([false, true] as $buildBodyFirst) {
            $when = $buildBodyFirst ? ', body built first' : '';

            yield 'embed() by name'.$when => [$base('<img src="cid:logo.png">')->embed('bytes', 'logo.png', 'image/png'), 'logo.png', $buildBodyFirst];

            yield 'attach() that the HTML refers to'.$when => [$base('<img src="cid:logo.png">')->attach('bytes', 'logo.png', 'image/png'), 'logo.png', $buildBodyFirst];

            yield 'an explicit content id the HTML refers to'.$when => [
                $base('<img src="cid:logo@example.com">')->addPart((new DataPart('bytes', 'logo.png', 'image/png'))->setContentId('logo@example.com')->asInline()),
                'logo@example.com',
                $buildBodyFirst,
            ];

            yield 'an attached part with a content id the HTML refers to'.$when => [
                $base('<img src="cid:logo@example.com">')->addPart((new DataPart('bytes', 'logo.png', 'image/png'))->setContentId('logo@example.com')),
                'logo@example.com',
                $buildBodyFirst,
            ];

            yield 'an explicit content id, the HTML refers to the name'.$when => [
                $base('<img src="cid:logo.png">')->addPart((new DataPart('bytes', 'logo.png', 'image/png'))->setContentId('logo@example.com')->asInline()),
                'logo.png',
                $buildBodyFirst,
            ];

            yield 'a background image'.$when => [$base('<table background="cid:bg.png"></table>')->embed('bytes', 'bg.png', 'image/png'), 'bg.png', $buildBodyFirst];
        }
    }

    public function testSendKeepsAnAttachmentThatTheHtmlDoesNotReferTo()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->html('<p><img src="cid:logo.png"></p>')
            ->attach('notes', 'logo', 'text/plain');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            // "logo" is only the start of the reference "logo.png", not a reference itself
            $this->assertArrayNotHasKey('inline_images', $body);
            $this->assertSame('logo', $body['attachments'][0]['name']);

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    public function testSendMatchesTheCidPrefixInAnyCaseButTheNameExactly()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->html('<p><IMG SRC="CID:logo.png"><img src="cid:BANNER.png"></p>')
            ->attach('bytes', 'logo.png', 'image/png')
            ->attach('bytes', 'banner.png', 'image/png');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame(['logo.png'], array_column($body['inline_images'], 'cid'));
            $this->assertSame(['banner.png'], array_column($body['attachments'], 'name'));

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    #[DataProvider('getStreamedBodies')]
    public function testSendReadsBodiesGivenAsStreams(string $kind, bool $atTheEnd, string $content)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        if (!$atTheEnd) {
            rewind($stream);
        }

        $email = (new Email())->from('foo@example.com')->to('bar@example.com')->subject('An email');
        $email->{$kind}($stream);

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($kind, $content): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame($content, $body[$kind.'body']);

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    public static function getStreamedBodies(): iterable
    {
        yield 'text' => ['text', false, 'Streamed ÄÖÜ body'];
        yield 'html' => ['html', false, '<p>Streamed ÄÖÜ body</p>'];
        yield 'text, the stream was read to its end before' => ['text', true, 'Streamed ÄÖÜ body'];
        yield 'html, the stream was read to its end before' => ['html', true, '<p>Streamed ÄÖÜ body</p>'];
        yield 'text with the content "0"' => ['text', false, '0'];
        yield 'html with the content "0"' => ['html', false, '0'];
        yield 'text with the content "0", read to its end before' => ['text', true, '0'];
    }

    public function testTrackingHeaderControlsOpensAndClicksIndependently()
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body');
        $email->getHeaders()->add(new TrackingHeader(false));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertFalse($body['track_opens']);
            $this->assertArrayNotHasKey('track_clicks', $body);

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    public function testPlainTextTrackingHeaderIsResolved()
    {
        // The "headers" option of the mailer configuration produces a plain text header
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body');
        $email->getHeaders()->addTextHeader(TrackingHeader::NAME, 'opens=false; clicks=true');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertFalse($body['track_opens']);
            $this->assertTrue($body['track_clicks']);
            $this->assertArrayNotHasKey('mime_headers', $body);

            return new JsonMockResponse(['data' => [], 'message' => 'OK', 'request_id' => 'req-1234']);
        });

        (new ZohoCpaasApiTransport('KEY', $client))->send($email);
    }

    public function testSendKeepsTheMessageIdWhenTheResponseHasNoRequestId()
    {
        $client = new MockHttpClient(new JsonMockResponse(['data' => [], 'message' => 'OK'], ['http_code' => 201]));

        $sentMessage = (new ZohoCpaasApiTransport('KEY', $client))->send((new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body'));

        $this->assertNotSame('', $sentMessage->getMessageId());
    }

    public function testSendThrowsWhenTheServerCannotBeReached()
    {
        $client = new MockHttpClient(new MockResponse('', ['error' => 'connection refused']));

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Could not reach the remote Zoho CPaaS server.');

        (new ZohoCpaasApiTransport('KEY', $client))->send((new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body'));
    }

    #[DataProvider('getErrorResponses')]
    public function testSendThrowsForErrorResponse(ResponseInterface $response, string $expectedMessage)
    {
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new ZohoCpaasApiTransport('KEY', new MockHttpClient($response)))->send($email);
    }

    public static function getErrorResponses(): iterable
    {
        yield 'documented shape' => [
            new JsonMockResponse(['data' => ['error_code' => 'TM_3004', 'message' => 'Invalid request'], 'message' => 'error'], ['http_code' => 400]),
            'Unable to send an email: Invalid request [TM_3004] (code 400).',
        ];

        yield 'error object with details' => [
            new JsonMockResponse(['error' => [
                'code' => 'TM_5001',
                'details' => [['code' => 'LE_102', 'message' => 'Credit exhausted', 'target' => 'credits']],
                'message' => 'Resource Limit Exhausted.',
                'request_id' => 'req-1234',
            ]], ['http_code' => 429]),
            'Unable to send an email: Resource Limit Exhausted. [TM_5001; LE_102 Credit exhausted] (code 429).',
        ];

        yield 'documented shape without a code' => [
            new JsonMockResponse(['data' => ['message' => 'Something went wrong'], 'message' => 'error'], ['http_code' => 400]),
            'Unable to send an email: Something went wrong (code 400).',
        ];

        yield 'unknown JSON' => [
            new JsonMockResponse(['unexpected' => true], ['http_code' => 500]),
            'Unable to send an email: {"unexpected":true} (code 500).',
        ];

        yield 'not JSON' => [
            new MockResponse('Bad Gateway', ['http_code' => 502]),
            'Unable to send an email: Bad Gateway (code 502).',
        ];
    }
}
