<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Component\Webhook\Messenger\SendWebhookMessage;
use Symfony\Component\Webhook\Server\TraceableTransport;

/**
 * Checks the webhooks that the application sent with the "webhook.transport" service.
 */
trait WebhookAssertionsTrait
{
    /**
     * Asserts the number of webhooks sent, to the given URL when it is not null.
     */
    public static function assertWebhookCount(int $count, ?string $url = null, string $message = ''): void
    {
        self::assertCount($count, self::getSentWebhooks($url), $message ?: \sprintf('Failed asserting that %d webhook(s) were sent%s.', $count, null === $url ? '' : ' to "'.$url.'"'));
    }

    /**
     * Asserts that a webhook was sent to the given URL, with the given event name and payload when they are not null.
     *
     * @param array<string, mixed>|null $payload
     */
    public static function assertWebhookSent(string $url, ?string $event = null, ?array $payload = null, string $message = ''): void
    {
        $webhooks = self::getSentWebhooks($url);
        $sent = array_map(static fn (SendWebhookMessage $webhook) => \sprintf('"%s" with payload %s', $webhook->getEvent()->getName(), self::encodeWebhookPayload($webhook->getEvent()->getPayload())), $webhooks);

        self::assertNotEmpty(self::filterSentWebhooks($webhooks, $event, $payload), $message ?: \sprintf('Failed asserting that a %s was sent to "%s". %s.', self::describeWebhook($event, $payload), $url, $sent ? 'Webhooks sent to this URL: '.implode(', ', $sent) : 'No webhook was sent to this URL'));
    }

    /**
     * Asserts that no webhook was sent to the given URL, with the given event name and payload when they are not null.
     *
     * @param array<string, mixed>|null $payload
     */
    public static function assertWebhookNotSent(string $url, ?string $event = null, ?array $payload = null, string $message = ''): void
    {
        self::assertEmpty(self::filterSentWebhooks(self::getSentWebhooks($url), $event, $payload), $message ?: \sprintf('Failed asserting that no %s was sent to "%s".', self::describeWebhook($event, $payload), $url));
    }

    /**
     * Returns the webhooks sent, to the given URL when it is not null, the oldest first.
     *
     * @return list<SendWebhookMessage>
     */
    public static function getSentWebhooks(?string $url = null): array
    {
        $container = static::getContainer();

        if (!$container->has('webhook.transport.traceable')) {
            static::fail('The Webhook component must be enabled with an HTTP client to make webhook assertions. Did you forget to require symfony/webhook or symfony/http-client?');
        }

        $transports = $container instanceof TestContainer ? $container->getKeptState('webhook.transport.traceable') : [];
        $transports[] = $container->get('webhook.transport.traceable');
        $webhooks = array_merge(...array_map(static fn (TraceableTransport $transport) => $transport->getSentWebhooks(), $transports));

        if (null !== $url) {
            $webhooks = array_values(array_filter($webhooks, static fn (SendWebhookMessage $webhook) => $url === $webhook->getSubscriber()->getUrl()));
        }

        return $webhooks;
    }

    /**
     * @param list<SendWebhookMessage> $webhooks
     *
     * @return list<SendWebhookMessage>
     */
    private static function filterSentWebhooks(array $webhooks, ?string $event, ?array $payload): array
    {
        return array_values(array_filter($webhooks, static fn (SendWebhookMessage $webhook) => (null === $event || $event === $webhook->getEvent()->getName()) && (null === $payload || $payload == $webhook->getEvent()->getPayload())));
    }

    private static function describeWebhook(?string $event, ?array $payload): string
    {
        $conditions = [];

        if (null !== $event) {
            $conditions[] = \sprintf('event "%s"', $event);
        }

        if (null !== $payload) {
            $conditions[] = 'payload '.self::encodeWebhookPayload($payload);
        }

        return $conditions ? 'webhook with '.implode(' and ', $conditions) : 'webhook';
    }

    private static function encodeWebhookPayload(array $payload): string
    {
        return json_encode($payload, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
