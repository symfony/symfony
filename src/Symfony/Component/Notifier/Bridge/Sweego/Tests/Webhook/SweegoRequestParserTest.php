<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sweego\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Bridge\Sweego\Webhook\SweegoRequestParser;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class SweegoRequestParserTest extends AbstractRequestParserTestCase
{
    private const SECRET = 'GvLY88Uyj70jQm3fUwYyWmAaiz98wWim';
    private const WEBHOOK_ID = 'a5ccc627-6e43-4012-bb29-f1bfe3a3d13e';
    private const WEBHOOK_TIMESTAMP = '1725290740';

    public static function getStaleClockOffsets(): iterable
    {
        yield 'too old' => [301];
        yield 'too far in the future' => [-301];
    }

    #[DataProvider('getStaleClockOffsets')]
    public function testRejectSignedRequestWithStaleTimestamp(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/sent.json'));
        ClockMock::withClockMock((int) $request->headers->get('webhook-timestamp') + $offset);

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
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/sent.json'));
        ClockMock::withClockMock((int) $request->headers->get('webhook-timestamp') + $offset);

        $this->assertNotNull($this->createRequestParser()->parse($request, $this->getSecret()));
    }

    public function testRequestSignedWithAnEmptySecretIsRejected()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/sent.json'));
        $request->headers->set('webhook-signature', 'k7SwzHXZqVKNvCpp6HwGS/5aDZ6NraYnKmVkBdx7MHE=');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-empty secret is required.');

        $this->createRequestParser()->parse($request, '');
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new SweegoRequestParser();
    }

    protected function createRequest(string $payload): Request
    {
        ClockMock::withClockMock((int) self::WEBHOOK_TIMESTAMP);

        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_webhook-id' => self::WEBHOOK_ID,
            'HTTP_webhook-timestamp' => self::WEBHOOK_TIMESTAMP,
            'HTTP_webhook-signature' => base64_encode(hash_hmac('sha256', \sprintf('%s.%s.%s', self::WEBHOOK_ID, self::WEBHOOK_TIMESTAMP, $payload), base64_decode(self::SECRET), true)),
        ], $payload);
    }

    protected function getSecret(): string
    {
        return self::SECRET;
    }

    public function testRejectForgedSignatureBeforeParsingThePayload()
    {
        $request = $this->createRequest('1');
        $request->headers->set('webhook-signature', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }
}
