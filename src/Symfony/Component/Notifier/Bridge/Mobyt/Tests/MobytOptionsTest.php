<?php

namespace Symfony\Component\Notifier\Bridge\Mobyt\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytOptions;
use Symfony\Component\Notifier\Notification\Notification;

class MobytOptionsTest extends TestCase
{
    /**
     * @dataProvider fromNotificationDataProvider
     */
    public function testFromNotification($importance, $expectedMessageType)
    {
        $notification = (new Notification('Foo'))->importance($importance);

        $options = (MobytOptions::fromNotification($notification))->toArray();

        $this->assertEquals($expectedMessageType, $options['message_type']);
    }

    public function testFromNotificationDefaultLevel()
    {
        $notification = (new Notification('Foo'))->importance('Bar');

        $options = (MobytOptions::fromNotification($notification))->toArray();

        $this->assertEquals(MobytOptions::MESSAGE_TYPE_QUALITY_HIGH, $options['message_type']);
    }

    public function fromNotificationDataProvider(): \Generator
    {
        yield [Notification::IMPORTANCE_URGENT, MobytOptions::MESSAGE_TYPE_QUALITY_HIGH];
        yield [Notification::IMPORTANCE_HIGH, MobytOptions::MESSAGE_TYPE_QUALITY_HIGH];
        yield [Notification::IMPORTANCE_MEDIUM, MobytOptions::MESSAGE_TYPE_QUALITY_MEDIUM];
        yield [Notification::IMPORTANCE_LOW, MobytOptions::MESSAGE_TYPE_QUALITY_LOW];
    }

    public function testGetRecipientIdWhenSet()
    {
        $options = [
            'recipient' => 'foo',
        ];
        $mobytOptions = new MobytOptions($options);

        $this->assertEquals('foo', $mobytOptions->getRecipientId());
    }

    public function testGetRecipientIdWhenNotSet()
    {
        $this->assertNull((new MobytOptions())->getRecipientId());
    }

    public function testToArray()
    {
        $options = [
            'message' => 'foo',
            'recipient' => 'bar',
        ];
        $this->assertEmpty((new MobytOptions($options))->toArray());
    }

    public function testMessageType()
    {
        $mobytOptions = new MobytOptions();
        $mobytOptions->messageType('foo');

        $this->assertEquals(['message_type' => 'foo'], $mobytOptions->toArray());
    }
}
