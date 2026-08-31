<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Azure\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Azure\RemoteEvent\AzurePayloadConverter;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * Parses Azure Communication Services Email webhook requests.
 *
 * Azure Event Grid sends events in CloudEvents schema or Event Grid schema.
 * This parser handles the Event Grid schema format.
 *
 * Event Grid does not sign its payloads. It authenticates itself either with a
 * Microsoft Entra bearer token or by repeating the query parameters of the
 * subscription URL on every delivery. This parser uses the latter: configure the
 * subscription URL with a "secret" query parameter holding the webhook secret.
 *
 * Event Grid's automatic subscription handshake (which requires echoing the
 * validation code in the response body) is not supported: register the
 * endpoint using Event Grid's manual validation flow instead.
 *
 * @see https://learn.microsoft.com/en-us/azure/event-grid/communication-services-email-events
 */
final class AzureRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly AzurePayloadConverter $converter,
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher('POST'),
            new IsJsonRequestMatcher(),
        ]);
    }

    /**
     * @return AbstractMailerEvent[]
     */
    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): array
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        if (!hash_equals($secret, (string) $request->query->get('secret'))) {
            throw new RejectWebhookException(401, 'Invalid secret.');
        }

        $payload = $request->toArray();

        // Azure Event Grid sends events as an array
        if (!isset($payload[0])) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        return array_map($this->parseEvent(...), $payload);
    }

    private function parseEvent(mixed $event): AbstractMailerEvent
    {
        // https://learn.microsoft.com/en-us/azure/event-grid/webhook-event-delivery
        if ('Microsoft.EventGrid.SubscriptionValidationEvent' === ($event['eventType'] ?? null)) {
            throw new RejectWebhookException(400, 'Event Grid subscription validation is not supported, use the manual validation flow to register the endpoint.');
        }

        if (!\is_array($event) || !isset($event['eventType']) || !isset($event['data']['messageId'])) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        try {
            return $this->converter->convert($event);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }
}
