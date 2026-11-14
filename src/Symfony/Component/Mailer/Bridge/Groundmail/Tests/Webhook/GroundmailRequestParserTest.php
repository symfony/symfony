<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Groundmail\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Groundmail\RemoteEvent\GroundmailPayloadConverter;
use Symfony\Component\Mailer\Bridge\Groundmail\Webhook\GroundmailRequestParser;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class GroundmailRequestParserTest extends AbstractRequestParserTestCase
{
    private const SECRET = 'whsec_5f0c1d2e3a4b5c6d7e8f9a0b1c2d3e4f';
    private const TIMESTAMP = 1792249331;

    public function testRequestWithAnEmptySecretIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-empty secret is required.');

        $this->createRequestParser()->parse($this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json')), '');
    }

    public function testRequestWithoutSignatureDoesNotMatch()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        $request->headers->remove('Groundmail-Signature');

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Request does not match.');

        $this->createRequestParser()->parse($request, self::SECRET);
    }

    #[DataProvider('provideInvalidSignatureHeaders')]
    public function testRejectsInvalidSignatureHeader(string $header)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        $request->headers->set('Groundmail-Signature', $header);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $this->createRequestParser()->parse($request, self::SECRET);
    }

    public static function provideInvalidSignatureHeaders(): iterable
    {
        yield 'forged' => ['t='.self::TIMESTAMP.',v1='.str_repeat('a', 64)];
        yield 'empty' => [''];
        yield 'no timestamp' => ['v1='.str_repeat('a', 64)];
        yield 'no signature' => ['t='.self::TIMESTAMP];
        yield 'timestamp not a number' => ['t=yesterday,v1='.str_repeat('a', 64)];
        yield 'unknown version' => ['t='.self::TIMESTAMP.',v2='.str_repeat('a', 64)];
    }

    public function testRejectsSignatureMadeWithAnotherSecret()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $this->createRequestParser()->parse($request, 'whsec_another');
    }

    public function testRejectsTamperedBody()
    {
        $payload = file_get_contents(__DIR__.'/Fixtures/delivered.json');
        $request = $this->createRequest($payload);
        $tampered = Request::create('/', 'POST', [], [], [], $request->server->all(), str_replace('user@example.com', 'attacker@example.com', $payload));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $this->createRequestParser()->parse($tampered, self::SECRET);
    }

    public function testRejectForgedSignatureBeforeParsingThePayload()
    {
        $request = $this->createRequest('1');
        $request->headers->set('Groundmail-Signature', 't='.self::TIMESTAMP.',v1='.str_repeat('a', 64));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $this->createRequestParser()->parse($request, self::SECRET);
    }

    public static function getStaleClockOffsets(): iterable
    {
        yield 'too old' => [301];
        yield 'too far in the future' => [-301];
    }

    #[DataProvider('getStaleClockOffsets')]
    public function testRejectSignedRequestWithStaleTimestamp(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        ClockMock::withClockMock(self::TIMESTAMP + $offset);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Timestamp is outside the allowed time window.');

        $this->createRequestParser()->parse($request, self::SECRET);
    }

    public static function getToleratedClockOffsets(): iterable
    {
        yield 'at the past edge' => [300];
        yield 'at the future edge' => [-300];
    }

    #[DataProvider('getToleratedClockOffsets')]
    public function testAcceptSignedRequestWithinTolerance(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        ClockMock::withClockMock(self::TIMESTAMP + $offset);

        $this->assertNotNull($this->createRequestParser()->parse($request, self::SECRET));
    }

    public function testTheTimestampIsCheckedAgainstTheGivenClock()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        ClockMock::withClockMock(self::TIMESTAMP + 86400);

        $parser = new GroundmailRequestParser(new GroundmailPayloadConverter(), new MockClock('@'.self::TIMESTAMP));

        $this->assertNotNull($parser->parse($request, self::SECRET));
    }

    #[DataProvider('provideMalformedPayloads')]
    public function testRejectsMalformedPayload(string $payload, string $message)
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage($message);

        $this->createRequestParser()->parse($this->createRequest($payload), self::SECRET);
    }

    public static function provideMalformedPayloads(): iterable
    {
        yield 'empty object' => ['{}', 'Payload is malformed.'];
        yield 'data is not an object' => ['{"type":"email.delivered","created_at":"2026-10-17T15:02:11.000Z","data":"x"}', 'Payload is malformed.'];
        yield 'unsupported event' => ['{"type":"automation.started","created_at":"2026-10-17T15:02:11.000Z","data":{"automation_id":"a1"}}', 'Unsupported event "automation.started".'];
        yield 'inbound email' => ['{"type":"email.received","created_at":"2026-10-17T15:02:11.000Z","data":{"email_id":"inb_1","to":["sales@example.com"]}}', 'Unsupported event "email.received".'];
        yield 'no email id' => ['{"type":"email.delivered","created_at":"2026-10-17T15:02:11.000Z","data":{"to":["user@example.com"]}}', 'The "email.delivered" event has no email id or recipient.'];
        yield 'invalid date' => ['{"type":"email.delivered","created_at":"yesterday","data":{"email_id":"msg_1","to":["user@example.com"]}}', 'Invalid date "yesterday".'];
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new GroundmailRequestParser(new GroundmailPayloadConverter());
    }

    protected function getSecret(): string
    {
        return self::SECRET;
    }

    protected function createRequest(string $payload): Request
    {
        ClockMock::withClockMock(self::TIMESTAMP);

        return Request::create('/', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => 'Groundmail-Webhooks/1.0',
            'HTTP_GROUNDMAIL_SIGNATURE' => 't='.self::TIMESTAMP.',v1='.hash_hmac('sha256', self::TIMESTAMP.'.'.$payload, self::SECRET),
        ], $payload);
    }
}
