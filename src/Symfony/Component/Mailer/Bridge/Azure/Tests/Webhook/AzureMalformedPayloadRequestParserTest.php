<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Azure\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Azure\RemoteEvent\AzurePayloadConverter;
use Symfony\Component\Mailer\Bridge\Azure\Webhook\AzureRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class AzureMalformedPayloadRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        $this->expectException(RejectWebhookException::class);

        return new AzureRequestParser(new AzurePayloadConverter());
    }

    public static function getPayloads(): iterable
    {
        yield 'not a list' => ['{"eventType": "Microsoft.Communication.EmailDeliveryReportReceived"}', []];
        yield 'no event type' => ['[{"data": {"messageId": "id"}}]', []];
        yield 'no message id' => ['[{"eventType": "Microsoft.Communication.EmailDeliveryReportReceived", "data": {}}]', []];
        yield 'unknown event type' => ['[{"eventType": "Microsoft.Communication.Unknown", "data": {"messageId": "id"}}]', []];
        yield 'unknown delivery status' => ['[{"eventType": "Microsoft.Communication.EmailDeliveryReportReceived", "data": {"messageId": "id", "status": "Teleported", "recipient": "a@b.com", "deliveryAttemptTimeStamp": "2026-03-18T00:22:20.285574+00:00"}}]', []];
        yield 'non-string recipient' => ['[{"eventType": "Microsoft.Communication.EmailDeliveryReportReceived", "data": {"messageId": "id", "status": "Delivered", "recipient": ["a@b.com"], "deliveryAttemptTimeStamp": "2026-03-18T00:22:20.285574+00:00"}}]', []];
        yield 'subscription validation' => ['[{"eventType": "Microsoft.EventGrid.SubscriptionValidationEvent", "data": {"validationCode": "code"}}]', []];
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/?secret='.self::SECRET, 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    protected function getSecret(): string
    {
        return self::SECRET;
    }

    private const SECRET = 'the-webhook-secret';
}
