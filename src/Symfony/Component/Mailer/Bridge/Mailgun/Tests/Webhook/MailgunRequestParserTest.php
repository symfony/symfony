<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Mailgun\RemoteEvent\MailgunPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailgun\Webhook\MailgunRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class MailgunRequestParserTest extends AbstractRequestParserTestCase
{
    public static function getStaleClockOffsets(): iterable
    {
        yield 'too old' => [301];
        yield 'too far in the future' => [-301];
    }

    #[DataProvider('getStaleClockOffsets')]
    public function testRejectSignedRequestWithStaleTimestamp(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered_messages.json'));
        ClockMock::withClockMock($request->toArray()['signature']['timestamp'] + $offset);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Timestamp is outside the allowed time window.');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }

    public static function getToleratedClockOffsets(): iterable
    {
        yield 'at the past edge' => [300];
        yield 'at the future edge' => [-300];
    }

    #[DataProvider('getToleratedClockOffsets')]
    public function testAcceptSignedRequestWithinTolerance(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered_messages.json'));
        ClockMock::withClockMock($request->toArray()['signature']['timestamp'] + $offset);

        $this->assertNotNull($this->createRequestParser()->parse($request, $this->getSecret()));
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new MailgunRequestParser(new MailgunPayloadConverter());
    }

    protected function createRequest(string $payload): Request
    {
        $request = parent::createRequest($payload);
        ClockMock::withClockMock($request->toArray()['signature']['timestamp'] ?? 0);

        return $request;
    }

    protected function getSecret(): string
    {
        return 'key-0p6mqbf74lb20gzq9f4dhpn9rg3zyk26';
    }

    public function testWrongSignatureIsRejected()
    {
        $payload = json_encode([
            'signature' => ['timestamp' => '1', 'token' => 'token', 'signature' => 'wrong'],
            'event-data' => ['event' => 'delivered'],
        ]);

        $this->expectException(RejectWebhookException::class);
        $this->createRequestParser()->parse($this->createRequest($payload), $this->getSecret());
    }

    #[DataProvider('provideNonStringSignatureFields')]
    public function testNonStringSignatureFieldsAreRejected(string $payload)
    {
        $this->expectException(RejectWebhookException::class);
        $this->createRequestParser()->parse($this->createRequest($payload), $this->getSecret());
    }

    public static function provideNonStringSignatureFields(): iterable
    {
        yield 'int signature' => ['{"signature":{"timestamp":"1","token":"token","signature":123},"event-data":{"event":"delivered"}}'];
        yield 'array signature' => ['{"signature":{"timestamp":"1","token":"token","signature":["wrong"]},"event-data":{"event":"delivered"}}'];
        yield 'array timestamp' => ['{"signature":{"timestamp":["1"],"token":"token","signature":"wrong"},"event-data":{"event":"delivered"}}'];
        yield 'array token' => ['{"signature":{"timestamp":"1","token":["token"],"signature":"wrong"},"event-data":{"event":"delivered"}}'];
    }

    public function testMalformedPayloadIsRejected()
    {
        $this->expectException(RejectWebhookException::class);
        $this->createRequestParser()->parse($this->createRequest('{"event-data": {}}'), $this->getSecret());
    }
}
