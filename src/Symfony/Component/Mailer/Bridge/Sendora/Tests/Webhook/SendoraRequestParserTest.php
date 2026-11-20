<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendora\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Sendora\RemoteEvent\SendoraPayloadConverter;
use Symfony\Component\Mailer\Bridge\Sendora\Webhook\SendoraRequestParser;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class SendoraRequestParserTest extends AbstractRequestParserTestCase
{
    private const SECRET = 'whsec_6Rr0wPp2yQ8wGx5mJv9tLk3NcZb7Ad4FhUe1Sn0Yi8M';
    private const TIMESTAMP = 1789473605;

    public function testASignatureOfSendoraIsAccepted()
    {
        // signed by Sendora while the webhook had a second, newer secret, whose signature comes first
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        $request->headers->set('Sendora-Signature', 't=1789473605,v1=e2f978ff0cc50ed270baff0750ed2d3900fe76477f2755c2f6286657ae106a81,v1=46ba42b43be0731759edd71a4862e156e4271b92bbfa8cb323166f5c99e873d7');

        $event = $this->createRequestParser()->parse($request, self::SECRET);

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame(MailerDeliveryEvent::DELIVERED, $event->getName());
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

        $parser = new SendoraRequestParser(new SendoraPayloadConverter(), new MockClock('@'.self::TIMESTAMP));

        $this->assertNotNull($parser->parse($request, self::SECRET));
    }

    public function testRequestSignedWithAnotherSecretIsRejected()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $this->createRequestParser()->parse($request, 'whsec_Qm3Xz8Bv1Nc6Lk0Jh5Gf2Ds9Ap4Wo7Ei3Ru6Ty1Ux2Q');
    }

    public static function provideMalformedSignatures(): iterable
    {
        yield 'no timestamp' => ['v1=46ba42b43be0731759edd71a4862e156e4271b92bbfa8cb323166f5c99e873d7'];
        yield 'a timestamp that is no number' => ['t=yesterday,v1=46ba42b43be0731759edd71a4862e156e4271b92bbfa8cb323166f5c99e873d7'];
        yield 'no signature' => ['t=1789473605'];
        yield 'an empty header' => [''];
    }

    #[DataProvider('provideMalformedSignatures')]
    public function testMalformedSignatureIsRejected(string $signature)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        $request->headers->set('Sendora-Signature', $signature);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $this->createRequestParser()->parse($request, self::SECRET);
    }

    public function testRequestWithoutSignatureIsRejected()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        $request->headers->remove('Sendora-Signature');

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Request does not match.');

        $this->createRequestParser()->parse($request, self::SECRET);
    }

    public function testEmptySecretIsRefused()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-empty secret is required.');

        $this->createRequestParser()->parse($this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json')), '');
    }

    public function testSignedPayloadWithoutEventIsRejected()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Payload is malformed.');

        $this->createRequestParser()->parse($this->createRequest('{"id": "7c3e1f2a-4b5d-4e6f-8a7b-9c0d1e2f3a4b", "attempt": 1}'), self::SECRET);
    }

    public function testEventNewerThanTheBridgeIsARemoteEventNamedAfterIt()
    {
        $payload = '{"id": "49a5e0fb-7c8e-4f9a-9b0c-2d3e4f5a6b7c", "attempt": 1, "event": "future_event", "future": true}';

        $this->assertEquals(
            new RemoteEvent('future_event', '49a5e0fb-7c8e-4f9a-9b0c-2d3e4f5a6b7c', json_decode($payload, true)),
            $this->createRequestParser()->parse($this->createRequest($payload), self::SECRET),
        );
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new SendoraRequestParser(new SendoraPayloadConverter());
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
            'HTTP_SENDORA_SIGNATURE' => 't='.self::TIMESTAMP.',v1='.hash_hmac('sha256', self::TIMESTAMP.'.'.$payload, self::SECRET),
        ], $payload);
    }
}
