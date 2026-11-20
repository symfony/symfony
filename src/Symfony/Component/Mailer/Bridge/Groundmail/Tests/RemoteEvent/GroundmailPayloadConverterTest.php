<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Groundmail\Tests\RemoteEvent;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Groundmail\RemoteEvent\GroundmailPayloadConverter;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

class GroundmailPayloadConverterTest extends TestCase
{
    public function testSeveralRecipients()
    {
        $event = (new GroundmailPayloadConverter())->convert([
            'type' => 'email.sent',
            'created_at' => '2026-10-17T15:02:11.000Z',
            'data' => ['email_id' => 'msg_1', 'to' => ['a@example.com', 'b@example.com'], 'tags' => []],
        ]);

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame(MailerDeliveryEvent::RECEIVED, $event->getName());
        $this->assertSame('a@example.com, b@example.com', $event->getRecipientEmail());
        $this->assertSame([], $event->getMetadata());
    }

    public function testUnsubscribeWithoutKnownEmail()
    {
        $event = (new GroundmailPayloadConverter())->convert([
            'type' => 'contact.unsubscribed',
            'created_at' => '2026-10-17T15:02:11.000Z',
            'data' => ['email' => 'user@example.com', 'scope' => 'all', 'suppression_id' => 'sup_1', 'category_opt_out_id' => null],
        ]);

        $this->assertInstanceOf(MailerEngagementEvent::class, $event);
        $this->assertSame(MailerEngagementEvent::UNSUBSCRIBE, $event->getName());
        $this->assertSame('sup_1', $event->getId());
        $this->assertSame('user@example.com', $event->getRecipientEmail());
    }

    public function testClick()
    {
        $event = (new GroundmailPayloadConverter())->convert([
            'type' => 'email.clicked',
            'created_at' => '2026-10-17T15:02:11.000Z',
            'data' => ['email_id' => 'msg_1', 'to' => ['user@example.com'], 'tags' => [['name' => 'order_id', 'value' => 'ord_123']]],
        ]);

        $this->assertInstanceOf(MailerEngagementEvent::class, $event);
        $this->assertSame(MailerEngagementEvent::CLICK, $event->getName());
        $this->assertSame(['order_id' => 'ord_123'], $event->getMetadata());
    }
}
