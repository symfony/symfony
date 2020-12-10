<?php

namespace Symfony\Component\Notifier\Bridge\Mobyt\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytOptions;
use Symfony\Component\Notifier\Notification\Notification;

final class MobytOptionsTest extends TestCase
{
    /**
     * @dataProvider fromNotificationDataProvider
     */
    public function testFromNotification(string $importance, string $expectedMessageType)
    {
        $notification = (new Notification('Foo'))->importance($importance);

        $options = (MobytOptions::fromNotification($notification))->toArray();

        $this->assertSame($expectedMessageType, $options['message_type']);
    }

    /**
     * @return \Generator<array{0: string, 1: string}>
     */
    public function fromNotificationDataProvider(): \Generator
    {
        yield [Notification::IMPORTANCE_URGENT, MobytOptions::MESSAGE_TYPE_QUALITY_HIGH];
        yield [Notification::IMPORTANCE_HIGH, MobytOptions::MESSAGE_TYPE_QUALITY_HIGH];
        yield [Notification::IMPORTANCE_MEDIUM, MobytOptions::MESSAGE_TYPE_QUALITY_MEDIUM];
        yield [Notification::IMPORTANCE_LOW, MobytOptions::MESSAGE_TYPE_QUALITY_LOW];
    }

    public function testFromNotificationDefaultLevel()
    {
        $notification = (new Notification('Foo'))->importance('Bar');

        $options = (MobytOptions::fromNotification($notification))->toArray();

        $this->assertSame(MobytOptions::MESSAGE_TYPE_QUALITY_HIGH, $options['message_type']);
    }

    public function testGetRecipientIdWhenSet()
    {
        $mobytOptions = new MobytOptions([
            'recipient' => 'foo',
        ]);

        $this->assertSame('foo', $mobytOptions->getRecipientId());
    }

    public function testGetRecipientIdWhenNotSet()
    {
        $this->assertNull((new MobytOptions())->getRecipientId());
    }

    public function testToArray()
    {
        $mobytOptions = new MobytOptions([
            'message' => 'foo',
            'recipient' => 'bar',
        ]);

        $this->assertEmpty($mobytOptions->toArray());
    }

    public function testMessageType()
    {
        $mobytOptions = new MobytOptions();
        $mobytOptions->messageType('foo');

        $this->assertSame(['message_type' => 'foo'], $mobytOptions->toArray());
    }
}
