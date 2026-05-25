<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twilio\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Bridge\Twilio\Webhook\TwilioRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class TwilioRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new TwilioRequestParser();
    }

    protected function createRequest(string $payload): Request
    {
        parse_str(trim($payload), $parameters);

        return Request::create('/', 'POST', $parameters, [], [], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
    }

    protected static function getFixtureExtension(): string
    {
        return 'txt';
    }

    public function testValidSignatureIsAccepted()
    {
        $secret = 's3cret-token';
        $params = [
            'MessageSid' => 'SM1234',
            'MessageStatus' => 'delivered',
            'To' => '+15555550100',
        ];
        $url = 'https://example.com/webhook';
        $signature = $this->computeTwilioSignature($url, $params, $secret);

        $request = Request::create($url, 'POST', $params, [], [], [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'HTTP_X-Twilio-Signature' => $signature,
        ]);

        $event = (new TwilioRequestParser())->parse($request, $secret);
        $this->assertNotNull($event);
        $this->assertSame('SM1234', $event->getId());
    }

    public function testMissingSignatureHeaderIsRejected()
    {
        $request = Request::create('https://example.com/webhook', 'POST', [
            'MessageSid' => 'SM1234',
            'MessageStatus' => 'delivered',
            'To' => '+15555550100',
        ], [], [], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        $this->expectException(RejectWebhookException::class);
        (new TwilioRequestParser())->parse($request, 's3cret-token');
    }

    public function testBadSignatureIsRejected()
    {
        $request = Request::create('https://example.com/webhook', 'POST', [
            'MessageSid' => 'SM1234',
            'MessageStatus' => 'delivered',
            'To' => '+15555550100',
        ], [], [], [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'HTTP_X-Twilio-Signature' => base64_encode('not-the-right-mac'),
        ]);

        $this->expectException(RejectWebhookException::class);
        (new TwilioRequestParser())->parse($request, 's3cret-token');
    }

    public function testJsonContentTypeIsRejected()
    {
        $request = Request::create(
            'https://example.com/webhook',
            'POST',
            [],
            [],
            [],
            ['Content-Type' => 'application/json'],
            json_encode([
                'MessageSid' => 'SM1234',
                'MessageStatus' => 'delivered',
                'To' => '+15555550100',
            ])
        );

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Payload is malformed.');
        (new TwilioRequestParser())->parse($request, 's3cret-token');
    }

    private function computeTwilioSignature(string $url, array $params, string $secret): string
    {
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }

        return base64_encode(hash_hmac('sha1', $data, $secret, true));
    }
}
