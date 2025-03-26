<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Tests\RemoteEvent;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Sendgrid\RemoteEvent\SendgridPayloadConverter;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;

class SendgridPayloadConverterTest extends TestCase
{
    /**
     * @dataProvider provideDeliveryEvents
     */
    public function testMailDeliveryEvent(string $event, string $expectedEventName)
    {
        $converter = new SendgridPayloadConverter();

        $event = $converter->convert([
            'event' => $event,
            'sg_message_id' => '123456',
            'reason' => 'reason',
            'timestamp' => '123456789',
            'email' => 'test@example.com',
        ]);

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame($expectedEventName, $event->getName());
        $this->assertSame('123456', $event->getId());
        $this->assertSame('reason', $event->getReason());
        $this->assertSame('test@example.com', $event->getRecipientEmail());
    }

    public static function provideDeliveryEvents(): iterable
    {
        yield ['processed', MailerDeliveryEvent::DELIVERED];
        yield ['delivered', MailerDeliveryEvent::DELIVERED];
        yield ['bounce', MailerDeliveryEvent::BOUNCE];
        yield ['dropped', MailerDeliveryEvent::DROPPED];
        yield ['deferred', MailerDeliveryEvent::DEFERRED];
    }

    /**
     * @dataProvider provideEngagementEvents
     */
    public function testMailEngagementEvent(string $event, string $expectedEventName)
    {
        $converter = new SendgridPayloadConverter();

        $event = $converter->convert([
            'event' => $event,
            'sg_message_id' => '123456',
            'timestamp' => '123456789',
            'email' => 'test@example.com',
        ]);

        $this->assertInstanceOf(MailerEngagementEvent::class, $event);
        $this->assertSame($expectedEventName, $event->getName());
        $this->assertSame('123456', $event->getId());
    }

    public static function provideEngagementEvents(): iterable
    {
        yield ['click', MailerEngagementEvent::CLICK];
        yield ['unsubscribe', MailerEngagementEvent::UNSUBSCRIBE];
        yield ['open', MailerEngagementEvent::OPEN];
        yield ['spamreport', MailerEngagementEvent::SPAM];
    }

    public function testUnsupportedEvent()
    {
        $converter = new SendgridPayloadConverter();

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unsupported event "unsupported".');

        $converter->convert([
            'event' => 'unsupported',
            'sg_message_id' => '123456',
            'timestamp' => '123456789',
            'email' => 'test@example.com',
        ]);
    }

    public function testInvalidDate()
    {
        $converter = new SendgridPayloadConverter();

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid date "invalid".');

        $converter->convert([
            'event' => 'processed',
            'sg_message_id' => '123456',
            'timestamp' => 'invalid',
            'email' => 'test@example.com',
        ]);
    }

    public function testAsynchronousBounce()
    {
        $converter = new SendgridPayloadConverter();

        $event = $converter->convert([
            'event' => 'bounce',
            'sg_event_id' => '123456',
            'timestamp' => '123456789',
            'email' => 'test@example.com',
        ]);

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame('123456', $event->getId());
    }

    public function testWithStringCategory()
    {
        $converter = new SendgridPayloadConverter();

        $event = $converter->convert([
            'event' => 'processed',
            'sg_message_id' => '123456',
            'timestamp' => '123456789',
            'email' => 'test@example.com',
            'category' => 'cat facts',
        ]);

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame(['cat facts'], $event->getTags());
    }
}
