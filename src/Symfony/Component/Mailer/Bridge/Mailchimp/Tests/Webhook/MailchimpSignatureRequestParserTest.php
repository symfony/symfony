<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Tests\Webhook;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Mailchimp\RemoteEvent\MailchimpPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailchimp\Webhook\MailchimpRequestParser;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

class MailchimpSignatureRequestParserTest extends TestCase
{
    private const SECRET = 'key-0p6mqbf74lb20gzq9f4dhpn9rg3zyk26';

    public function testMissingSignatureIsRejected()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        $this->parse($this->createRequest(['mandrill_events' => $this->events()]));
    }

    public function testForgedSignatureIsRejected()
    {
        $request = $this->createRequest(['mandrill_events' => $this->events()]);
        $request->headers->set('X-Mandrill-Signature', base64_encode(hash_hmac('sha1', 'http://localhost/mandrill_events'.$this->events(), 'wrong-secret', true)));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        $this->parse($request);
    }

    public function testEmptyCheckSignedWithTheRealSecretIsRejected()
    {
        $request = $this->createRequest(['mandrill_events' => '[]']);
        $request->headers->set('X-Mandrill-Signature', base64_encode(hash_hmac('sha1', 'http://localhost/mandrill_events[]', self::SECRET, true)));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        $this->parse($request);
    }

    public function testEventsSignedWithAnEmptySecretAreRejected()
    {
        $request = $this->createRequest(['mandrill_events' => $this->events()]);
        $request->headers->set('X-Mandrill-Signature', base64_encode(hash_hmac('sha1', 'http://localhost/mandrill_events'.$this->events(), '', true)));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-empty secret is required.');

        (new MailchimpRequestParser(new MailchimpPayloadConverter()))->parse($request, '');
    }

    public function testEmptyCheckIsRejectedWhenNoSecretIsConfigured()
    {
        $request = $this->createRequest(['mandrill_events' => '[]']);
        $request->headers->set('X-Mandrill-Signature', base64_encode(hash_hmac('sha1', 'http://localhost/mandrill_events[]', 'test-webhook', true)));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-empty secret is required.');

        (new MailchimpRequestParser(new MailchimpPayloadConverter()))->parse($request, '');
    }

    public function testNonStringEventsParameterIsRejected()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Payload malformed.');

        $this->parse($this->createRequest(['mandrill_events' => ['[]']]));
    }

    public function testUrlIsSignedAsSentAndNotNormalized()
    {
        $request = Request::create('/hook?foo=1&bar=2', 'POST', ['mandrill_events' => $this->events()], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $request->headers->set('X-Mandrill-Signature', base64_encode(hash_hmac('sha1', 'http://localhost/hook?foo=1&bar=2mandrill_events'.$this->events(), self::SECRET, true)));

        $this->assertCount(1, $this->parse($request));
    }

    private function events(): string
    {
        return json_encode(json_decode(file_get_contents(__DIR__.'/Fixtures/send.json'), true)['mandrill_events'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    private function createRequest(array $params): Request
    {
        return Request::create('/', 'POST', $params, [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
    }

    private function parse(Request $request): mixed
    {
        return (new MailchimpRequestParser(new MailchimpPayloadConverter()))->parse($request, self::SECRET);
    }
}
