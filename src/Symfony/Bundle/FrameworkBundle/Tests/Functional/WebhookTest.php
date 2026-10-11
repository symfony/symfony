<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\RemoteEvent\Messenger\ConsumeRemoteEventMessage;
use Symfony\Component\Webhook\Messenger\SendWebhookMessage;

class WebhookTest extends AbstractWebTestCase
{
    use ClockSensitiveTrait;

    public function testIncomingWebhooksAreSignedAsTheirRoutingEntryExpects()
    {
        $client = $this->createClient(['test_case' => 'Webhook']);
        $client->sendWebhook('payments', 'payment.succeeded', ['amount' => 42]);

        $this->assertResponseStatusCodeSame(202);
        $this->assertSame('/webhook/payments', $client->getRequest()->getPathInfo());
        $this->assertQueuedMessageCount(1, 'async', ConsumeRemoteEventMessage::class);

        $message = $this->getQueuedMessages('async')[0]->getMessage();
        $this->assertSame('payments', $message->getType());
        $this->assertSame('payment.succeeded', $message->getEvent()->getName());
        $this->assertSame(['amount' => 42, 'type' => 'payment.succeeded'], $message->getEvent()->getPayload());
    }

    public function testIncomingWebhooksSignedWithAnotherSecretAreRejected()
    {
        $client = $this->createClient(['test_case' => 'Webhook']);
        $client->sendWebhook('payments', 'payment.succeeded', ['amount' => 42], 'whsec_'.base64_encode('not the secret'));

        $this->assertResponseStatusCodeSame(406);
        $this->assertQueuedMessageCount(0, 'async');
    }

    public function testIncomingWebhooksAreTimestampedByTheClock()
    {
        $client = $this->createClient(['test_case' => 'Webhook']);
        $clock = static::mockTime('2026-10-11 12:00:00');
        $client->sendWebhook('payments', 'payment.succeeded');

        $this->assertResponseStatusCodeSame(202);
        $this->assertSame((string) $clock->now()->getTimestamp(), $client->getRequest()->headers->get('Webhook-Timestamp'));

        static::mockTime('+1 hour');
        $client->reload();

        $this->assertResponseStatusCodeSame(406);
    }

    public function testSendWebhookRequiresTheWebhookComponent()
    {
        $client = $this->createClient(['test_case' => 'Webhook', 'root_config' => 'disabled.yml']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"Symfony\Bundle\FrameworkBundle\KernelBrowser::sendWebhook" requires the Webhook component to be enabled. Try running "composer require symfony/webhook".');

        $client->sendWebhook('payments', 'payment.succeeded');
    }

    public function testSendWebhookRequiresTheWebhookRoutes()
    {
        $client = $this->createClient(['test_case' => 'ConfigureContainer']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"Symfony\Bundle\FrameworkBundle\KernelBrowser::sendWebhook" requires the "_webhook_controller" route. Did you forget to import "@FrameworkBundle/Resources/config/routing/webhook.php" in your routes?');

        $client->sendWebhook('payments', 'payment.succeeded');
    }

    public function testSentWebhooksCanBeAsserted()
    {
        $client = $this->createClient(['test_case' => 'Webhook']);
        $client->request('GET', '/webhook-sender/send/order.paid');

        $this->assertWebhookCount(1);
        $this->assertWebhookCount(1, 'https://example.com/webhook');
        $this->assertWebhookCount(0, 'https://example.org/webhook');
        $this->assertWebhookSent('https://example.com/webhook');
        $this->assertWebhookSent('https://example.com/webhook', 'order.paid');
        $this->assertWebhookSent('https://example.com/webhook', 'order.paid', ['order' => 42]);
        $this->assertWebhookNotSent('https://example.org/webhook');
        $this->assertWebhookNotSent('https://example.com/webhook', 'order.refunded');
        $this->assertWebhookNotSent('https://example.com/webhook', 'order.paid', ['order' => 43]);

        $webhooks = $this->getSentWebhooks();
        $this->assertCount(1, $webhooks);
        $this->assertSame('order-42', $webhooks[0]->getEvent()->getId());
    }

    public function testAssertWebhookSentListsTheWebhooksSentToTheUrl()
    {
        $client = $this->createClient(['test_case' => 'Webhook']);
        $client->request('GET', '/webhook-sender/send/order.paid');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that a webhook with event "order.refunded" was sent to "https://example.com/webhook". Webhooks sent to this URL: "order.paid" with payload {"order":42}.');

        $this->assertWebhookSent('https://example.com/webhook', 'order.refunded');
    }

    public function testWebhookAssertionsRequireTheWebhookComponent()
    {
        self::bootKernel(['test_case' => 'Webhook', 'root_config' => 'disabled.yml']);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The Webhook component must be enabled with an HTTP client to make webhook assertions. Did you forget to require symfony/webhook or symfony/http-client?');

        $this->assertWebhookCount(0);
    }

    public function testWebhooksSentBeforeARebootAreKept()
    {
        $client = $this->createClient(['test_case' => 'Webhook']);
        $client->request('GET', '/webhook-sender/send/order.paid');
        $this->assertResponseRedirects('/webhook-sender/sent');
        $client->followRedirect();
        $client->request('GET', '/webhook-sender/send/order.refunded');

        $this->assertSame(['order.paid', 'order.refunded'], array_map(static fn (SendWebhookMessage $webhook) => $webhook->getEvent()->getName(), $this->getSentWebhooks()));
    }

    public function testWebhooksSentByAWorkerAreSeen()
    {
        $client = $this->createClient(['test_case' => 'Webhook']);
        $client->request('GET', '/webhook-sender/dispatch/order.paid');

        $this->assertWebhookCount(0);

        $this->consumeQueuedMessages('async');

        $this->assertWebhookSent('https://example.com/webhook', 'order.paid', ['order' => 42]);
    }
}
