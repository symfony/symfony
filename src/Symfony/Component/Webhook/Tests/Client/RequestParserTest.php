<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests\Client;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Client\RequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Server\SignatureFormat;

class RequestParserTest extends TestCase
{
    private const SECRET = 's3cr3t';
    private const NOW = 1700000000;

    public function testParseDoesNotMatch()
    {
        $this->expectException(RejectWebhookException::class);
        (new RequestParser())->parse(new Request(), '$ecret');
    }

    public function testParseRejectsAJsonBodyThatIsNotAnArray()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Request body is malformed.');

        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '1');
        $parser = new class extends AbstractRequestParser {
            protected function getRequestMatcher(): RequestMatcherInterface
            {
                return new IsJsonRequestMatcher();
            }

            protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?RemoteEvent
            {
                $request->toArray();

                return null;
            }
        };
        $parser->parse($request, '$ecret');
    }

    public function testLegacyAcceptsItsOwnSignature()
    {
        $body = '{"foo":"bar"}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->legacy('event-name', $body),
            'Webhook-Event' => 'event-name',
            'Webhook-Id' => 'event-id',
        ]);

        $this->assertSame('event-name', $this->parser()->parse($request, self::SECRET)->getName());
    }

    public function testLegacyRejectsAWrongSignature()
    {
        $body = '{"foo":"bar"}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => 'sha256='.hash_hmac('sha256', 'event-nameevent-id'.$body, 'wrong-secret'),
            'Webhook-Event' => 'event-name',
            'Webhook-Id' => 'event-id',
        ]);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        $this->parser()->parse($request, self::SECRET);
    }

    public function testLegacyRejectsARewrittenEventHeader()
    {
        $body = '{"foo":"bar"}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->legacy('event-name', $body),
            'Webhook-Event' => 'rewritten-name',
            'Webhook-Id' => 'event-id',
        ]);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        $this->parser()->parse($request, self::SECRET);
    }

    public function testLegacyIgnoresAStandardEntry()
    {
        $body = '{"type":"event-name"}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->standard(self::NOW, $body),
            'Webhook-Event' => 'event-name',
            'Webhook-Id' => 'event-id',
            'Webhook-Timestamp' => self::NOW,
        ]);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        $this->parser()->parse($request, self::SECRET);
    }

    public function testStandardReadsTheEventNameFromThePayload()
    {
        $body = '{"type":"payment.authorized","amount":42}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->standard(self::NOW, $body),
            'Webhook-Id' => 'event-id',
            'Webhook-Timestamp' => self::NOW,
        ]);

        $event = $this->parser(SignatureFormat::Standard)->parse($request, self::SECRET);
        $this->assertSame('payment.authorized', $event->getName());
        $this->assertSame('event-id', $event->getId());
        $this->assertSame(['type' => 'payment.authorized', 'amount' => 42], $event->getPayload());
    }

    public function testStandardRejectsAPayloadWithoutAType()
    {
        $body = '{"amount":42}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->standard(self::NOW, $body),
            'Webhook-Id' => 'event-id',
            'Webhook-Timestamp' => self::NOW,
        ]);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The payload is missing the "type" key holding the event name.');

        $this->parser(SignatureFormat::Standard)->parse($request, self::SECRET);
    }

    public function testStandardIgnoresAnEventHeader()
    {
        $body = '{"type":"payment.authorized"}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->standard(self::NOW, $body),
            'Webhook-Event' => 'payment.refunded',
            'Webhook-Id' => 'event-id',
            'Webhook-Timestamp' => self::NOW,
        ]);

        $this->assertSame('payment.authorized', $this->parser(SignatureFormat::Standard)->parse($request, self::SECRET)->getName());
    }

    public function testStandardRejectsAReplay()
    {
        $body = '{"type":"event-name"}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->standard(self::NOW - 3600, $body),
            'Webhook-Id' => 'event-id',
            'Webhook-Timestamp' => self::NOW - 3600,
        ]);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The "Webhook-Timestamp" HTTP request header is outside the accepted window.');

        $this->parser(SignatureFormat::Standard)->parse($request, self::SECRET);
    }

    public function testStandardRejectsANonNumericTimestamp()
    {
        $body = '{"type":"event-name"}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->standard('not-a-number', $body),
            'Webhook-Id' => 'event-id',
            'Webhook-Timestamp' => 'not-a-number',
        ]);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The "Webhook-Timestamp" HTTP request header is outside the accepted window.');

        $this->parser(SignatureFormat::Standard)->parse($request, self::SECRET);
    }

    public function testAToleranceOfZeroAcceptsAnyTimestamp()
    {
        $body = '{"type":"event-name"}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->standard(self::NOW - 86400, $body),
            'Webhook-Id' => 'event-id',
            'Webhook-Timestamp' => self::NOW - 86400,
        ]);

        $this->assertSame('event-name', $this->parser(SignatureFormat::Standard, 0)->parse($request, self::SECRET)->getName());
    }

    public function testStandardRequiresTheTimestampHeader()
    {
        $body = '{"type":"event-name"}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->standard(self::NOW, $body),
            'Webhook-Id' => 'event-id',
        ]);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Missing "Webhook-Timestamp" HTTP request signature header.');

        $this->parser(SignatureFormat::Standard)->parse($request, self::SECRET);
    }

    public function testStandardUsesTheDecodedWhsecSecret()
    {
        $secret = 'whsec_'.base64_encode('raw-key-bytes');
        $body = '{"type":"event-name"}';
        // the peer signs with the decoded bytes, as every Standard Webhooks implementation does
        $signature = 'v1,'.base64_encode(hash_hmac('sha256', 'event-id.'.self::NOW.'.'.$body, 'raw-key-bytes', true));

        $request = $this->createRequest($body, [
            'Webhook-Signature' => $signature,
            'Webhook-Id' => 'event-id',
            'Webhook-Timestamp' => self::NOW,
        ]);

        $this->assertSame('event-name', $this->parser(SignatureFormat::Standard)->parse($request, $secret)->getName());
    }

    public function testAnyOfSeveralEntriesIsEnoughDuringARotation()
    {
        $body = '{"type":"event-name"}';
        $stale = 'v1,'.base64_encode(hash_hmac('sha256', 'event-id.'.self::NOW.'.'.$body, 'old-secret', true));

        $request = $this->createRequest($body, [
            'Webhook-Signature' => $stale.'  '.$this->standard(self::NOW, $body),
            'Webhook-Id' => 'event-id',
            'Webhook-Timestamp' => self::NOW,
        ]);

        $this->assertSame('event-name', $this->parser(SignatureFormat::Standard)->parse($request, self::SECRET)->getName());
    }

    public function testTransitionalAcceptsALegacyOnlySender()
    {
        $body = '{"foo":"bar"}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->legacy('event-name', $body),
            'Webhook-Event' => 'event-name',
            'Webhook-Id' => 'event-id',
        ]);

        $this->assertSame('event-name', $this->parser(SignatureFormat::Transitional)->parse($request, self::SECRET)->getName());
    }

    public function testTransitionalAcceptsAStandardOnlySender()
    {
        $body = '{"type":"event-name"}';
        $request = $this->createRequest($body, [
            'Webhook-Signature' => $this->standard(self::NOW, $body),
            'Webhook-Id' => 'event-id',
            'Webhook-Timestamp' => self::NOW,
        ]);

        $this->assertSame('event-name', $this->parser(SignatureFormat::Transitional)->parse($request, self::SECRET)->getName());
    }

    public function testTransitionalBoundsReplaysWhateverTheEntryOrder()
    {
        $body = '{"type":"event-name"}';
        $legacy = $this->legacy('event-name', $body);
        $standard = $this->standard(self::NOW - 3600, $body);

        foreach ([$legacy.' '.$standard, $standard.' '.$legacy] as $signature) {
            $request = $this->createRequest($body, [
                'Webhook-Signature' => $signature,
                'Webhook-Event' => 'event-name',
                'Webhook-Id' => 'event-id',
                'Webhook-Timestamp' => self::NOW - 3600,
            ]);

            try {
                $this->parser(SignatureFormat::Transitional)->parse($request, self::SECRET);
                $this->fail('The replay should have been rejected.');
            } catch (RejectWebhookException $e) {
                $this->assertSame('The "Webhook-Timestamp" HTTP request header is outside the accepted window.', $e->getMessage());
            }
        }
    }

    private function parser(SignatureFormat $format = SignatureFormat::Legacy, int $tolerance = 300): RequestParser
    {
        return new RequestParser(format: $format, timestampTolerance: $tolerance, clock: new MockClock('@'.self::NOW));
    }

    private function legacy(string $event, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $event.'event-id'.$body, self::SECRET);
    }

    private function standard(string|int $timestamp, string $body): string
    {
        return 'v1,'.base64_encode(hash_hmac('sha256', 'event-id.'.$timestamp.'.'.$body, self::SECRET, true));
    }

    private function createRequest(string $body, array $headers): Request
    {
        $server = ['HTTP_CONTENT_TYPE' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return Request::create('/', 'POST', [], [], [], $server, $body);
    }
}
