<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailomat\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Mailomat\RemoteEvent\MailomatPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailomat\Webhook\MailomatRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class MailomatRequestParserTest extends AbstractRequestParserTestCase
{
    private const SECRET = 'NgD3IyUA0oLfkM5IyL8tdMNJeIYeBXOpAcnulN1du1aqh3jFbo766lKdJvMePUy5';
    private const EVENT_ID = '1d958822-0934-4c6a-abc8-5defec4baa64';
    private const EVENT = 'delivered';

    protected function createRequestParser(): RequestParserInterface
    {
        return new MailomatRequestParser(new MailomatPayloadConverter());
    }

    protected function getSecret(): string
    {
        return self::SECRET;
    }

    protected function createRequest(string $payload): Request
    {
        return self::buildSignedRequest(
            str_replace("\n", "\r\n", $payload),
            self::EVENT_ID,
            self::EVENT,
            (string) time(),
            'sha256',
            self::SECRET,
        );
    }

    public function testStaleTimestampIsRejected()
    {
        $body = '{"id":"x","eventType":"delivered","occurredAt":"2024-06-10T09:23:31+02:00","messageId":"a","recipient":"a@b"}';
        $request = self::buildSignedRequest($body, self::EVENT_ID, self::EVENT, (string) (time() - 90000), 'sha256', self::SECRET);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Timestamp');
        $this->createRequestParser()->parse($request, self::SECRET);
    }

    public function testToleranceZeroDisablesTimestampCheck()
    {
        $body = '{"id":"x","eventType":"delivered","occurredAt":"2024-06-10T09:23:31+02:00","messageId":"a","recipient":"a@b"}';
        $request = self::buildSignedRequest($body, self::EVENT_ID, self::EVENT, '1718004211', 'sha256', self::SECRET);

        $parser = new MailomatRequestParser(new MailomatPayloadConverter(), 0);
        $this->assertNotNull($parser->parse($request, self::SECRET));
    }

    private static function buildSignedRequest(string $body, string $id, string $event, string $timestamp, string $algo, string $secret): Request
    {
        $data = implode('.', [$id, $event, $timestamp]);
        $signature = $algo.'='.hash_hmac($algo, $data, $secret);

        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_X-MOM-Webhook-Event' => $event,
            'HTTP_X-MOM-Webhook-ID' => $id,
            'HTTP_X-MOM-Webhook-Signature' => $signature,
            'HTTP_X-MOM-Webhook-Timestamp' => $timestamp,
        ], $body);
    }
}
