<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests\Test;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Webhook\Client\RequestParser;
use Symfony\Component\Webhook\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Server\SignatureFormat;
use Symfony\Component\Webhook\Test\RequestFactory;

class RequestFactoryTest extends TestCase
{
    private const SECRET = 'whsec_c2hvd2Nhc2UtcGF5bWVudHMtd2ViaG9vaw==';
    private const NOW = 1700000000;

    #[TestWith(['legacy'])]
    #[TestWith(['standard'])]
    #[TestWith(['transitional'])]
    public function testTheRequestParserAcceptsTheRequest(string $format)
    {
        $format = SignatureFormat::from($format);
        $clock = new MockClock('@'.self::NOW);
        $factory = new RequestFactory(['payments' => self::SECRET], 'sha256', 'Webhook-Signature', 'Webhook-Event', 'Webhook-Id', 'Webhook-Timestamp', $format, $clock);
        $parser = new RequestParser('sha256', 'Webhook-Signature', 'Webhook-Event', 'Webhook-Id', 'Webhook-Timestamp', $format, 300, $clock);

        $request = $factory->createRequest('/webhook/payments', 'payments', 'payment.succeeded', ['amount' => 42]);
        $event = $parser->parse($request, self::SECRET);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/webhook/payments', $request->getPathInfo());
        $this->assertSame('payment.succeeded', $event->getName());
        $this->assertSame($request->headers->get('Webhook-Id'), $event->getId());
        $this->assertSame(42, $event->getPayload()['amount']);
    }

    public function testTheEventNameIsSentInThePayloadUnlessTheFormatIsLegacy()
    {
        $legacy = new RequestFactory(['payments' => self::SECRET]);
        $standard = new RequestFactory(['payments' => self::SECRET], 'sha256', 'Webhook-Signature', 'Webhook-Event', 'Webhook-Id', 'Webhook-Timestamp', SignatureFormat::Standard);

        $this->assertSame('{"amount":42}', $legacy->createRequest('/', 'payments', 'payment.succeeded', ['amount' => 42])->getContent());
        $this->assertSame('{"amount":42,"type":"payment.succeeded"}', $standard->createRequest('/', 'payments', 'payment.succeeded', ['amount' => 42])->getContent());
        $this->assertSame('{"type":"payment.failed"}', $standard->createRequest('/', 'payments', 'payment.succeeded', ['type' => 'payment.failed'])->getContent());
    }

    #[TestWith(['legacy', ['content-type', 'webhook-id', 'webhook-timestamp', 'webhook-event', 'webhook-signature']])]
    #[TestWith(['standard', ['content-type', 'webhook-id', 'webhook-timestamp', 'webhook-signature']])]
    public function testTheRequestCarriesOnlyTheHeadersOfAWebhook(string $format, array $headers)
    {
        $factory = new RequestFactory(['payments' => self::SECRET], 'sha256', 'Webhook-Signature', 'Webhook-Event', 'Webhook-Id', 'Webhook-Timestamp', SignatureFormat::from($format));

        $this->assertSame($headers, array_keys($factory->createRequest('/', 'payments', 'payment.succeeded')->headers->all()));
    }

    public function testTheTimestampComesFromTheClock()
    {
        $factory = new RequestFactory(['payments' => self::SECRET], 'sha256', 'Webhook-Signature', 'Webhook-Event', 'Webhook-Id', 'Webhook-Timestamp', SignatureFormat::Standard, new MockClock('@'.self::NOW));

        $this->assertSame((string) self::NOW, $factory->createRequest('/', 'payments', 'payment.succeeded')->headers->get('Webhook-Timestamp'));
    }

    public function testEachRequestHasItsOwnId()
    {
        $factory = new RequestFactory(['payments' => self::SECRET]);

        $this->assertNotSame($factory->createRequest('/', 'payments', 'payment.succeeded')->headers->get('Webhook-Id'), $factory->createRequest('/', 'payments', 'payment.succeeded')->headers->get('Webhook-Id'));
    }

    public function testARequestSignedWithAnotherSecretIsRejected()
    {
        $factory = new RequestFactory(['payments' => self::SECRET], 'sha256', 'Webhook-Signature', 'Webhook-Event', 'Webhook-Id', 'Webhook-Timestamp', SignatureFormat::Standard);
        $request = $factory->createRequest('/', 'payments', 'payment.succeeded', [], 'whsec_'.base64_encode('not the secret'));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        (new RequestParser('sha256', 'Webhook-Signature', 'Webhook-Event', 'Webhook-Id', 'Webhook-Timestamp', SignatureFormat::Standard))->parse($request, self::SECRET);
    }

    public function testATypeWithoutSecretIsRejected()
    {
        $factory = new RequestFactory(['payments' => self::SECRET, 'refunds' => self::SECRET]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot sign webhooks of type "mailgun": only the routing entries that use the generic request parser of the Webhook component can be signed ("payments", "refunds"). Create the requests of the other entries by hand.');

        $factory->createRequest('/', 'mailgun', 'delivered');
    }

    public function testAnEmptySecretIsRejected()
    {
        $factory = new RequestFactory(['payments' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-empty secret is required to sign webhooks of type "payments".');

        $factory->createRequest('/', 'payments', 'payment.succeeded');
    }
}
