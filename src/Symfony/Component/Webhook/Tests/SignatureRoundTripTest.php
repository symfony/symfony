<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\RequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Server\HeadersConfigurator;
use Symfony\Component\Webhook\Server\HeaderSignatureConfigurator;
use Symfony\Component\Webhook\Server\JsonBodyConfigurator;
use Symfony\Component\Webhook\Server\NativeJsonPayloadSerializer;
use Symfony\Component\Webhook\Server\SignatureFormat;

/**
 * Sends through the configurators Transport chains, then parses the result back.
 */
class SignatureRoundTripTest extends TestCase
{
    private const SECRET = 's3cr3t';
    private const SENT_AT = 1700000000;

    #[TestWith(['legacy'])]
    #[TestWith(['standard'])]
    #[TestWith(['transitional'])]
    public function testARequestParsesBackToTheEventItWasSentFor(string $format)
    {
        $format = SignatureFormat::from($format);
        $event = new RemoteEvent('payment.authorized', 'event-id', ['amount' => 42]);

        $parsed = $this->parse($this->send($event, $format, self::SENT_AT), $format, self::SENT_AT);

        $this->assertSame('payment.authorized', $parsed->getName());
        $this->assertSame('event-id', $parsed->getId());
        $this->assertSame(42, $parsed->getPayload()['amount']);
    }

    #[TestWith(['standard'])]
    #[TestWith(['transitional'])]
    public function testAReplayIsRejected(string $format)
    {
        $format = SignatureFormat::from($format);
        $event = new RemoteEvent('payment.authorized', 'event-id', ['amount' => 42]);
        $headers = $this->send($event, $format, self::SENT_AT - 3600);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The "Webhook-Timestamp" HTTP request header is outside the accepted window.');

        $this->parse($headers, $format, self::SENT_AT);
    }

    #[TestWith(['legacy'])]
    #[TestWith(['transitional'])]
    public function testARewrittenEventHeaderIsRejected(string $format)
    {
        $format = SignatureFormat::from($format);
        $event = new RemoteEvent('payment.authorized', 'event-id', ['amount' => 42]);
        $headers = $this->send($event, $format, self::SENT_AT);
        $headers['Webhook-Event'] = 'payment.refunded';

        if (SignatureFormat::Transitional === $format) {
            // the Standard Webhooks entry still verifies, and it does not read that header at all
            $this->assertSame('payment.authorized', $this->parse($headers, $format, self::SENT_AT)->getName());

            return;
        }

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        $this->parse($headers, $format, self::SENT_AT);
    }

    public function testTheEventNameIsNotSentAsAnUnsignedHeaderInStandardFormat()
    {
        $headers = $this->send(new RemoteEvent('payment.authorized', 'event-id', []), SignatureFormat::Standard, self::SENT_AT);

        $this->assertArrayNotHasKey('Webhook-Event', $headers);
        $this->assertSame('payment.authorized', json_decode($headers['body'], true)['type']);
    }

    /**
     * @return array the request headers, plus the body under the "body" key
     */
    private function send(RemoteEvent $event, SignatureFormat $format, int $sentAt): array
    {
        $options = new HttpOptions();

        (new HeadersConfigurator(clock: new MockClock('@'.$sentAt), format: $format))->configure($event, self::SECRET, $options);
        (new JsonBodyConfigurator(new NativeJsonPayloadSerializer(), $format))->configure($event, self::SECRET, $options);
        (new HeaderSignatureConfigurator(format: $format))->configure($event, self::SECRET, $options);

        $opts = $options->toArray();

        return $opts['headers'] + ['body' => $opts['body']];
    }

    private function parse(array $headers, SignatureFormat $format, int $now): RemoteEvent
    {
        $body = $headers['body'];
        unset($headers['body']);

        $server = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $parser = new RequestParser(format: $format, clock: new MockClock('@'.$now));

        return $parser->parse(Request::create('/', 'POST', [], [], [], $server, $body), self::SECRET);
    }
}
