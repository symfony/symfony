<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mobyt\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytOptions;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Notification\Notification;

final class MobytOptionsTest extends TestCase
{
    /**
     * @dataProvider fromNotificationDataProvider
     */
    public function testFromNotification(string $importance, string $expectedMessageType)
    {
        $notification = (new Notification('Foo'))->importance($importance);

        $options = MobytOptions::fromNotification($notification)->toArray();

        self::assertSame($expectedMessageType, $options['message_type']);
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

        $options = MobytOptions::fromNotification($notification)->toArray();

        self::assertSame(MobytOptions::MESSAGE_TYPE_QUALITY_HIGH, $options['message_type']);
    }

    public function testGetRecipientIdWhenSet()
    {
        $mobytOptions = new MobytOptions([
            'recipient' => 'foo',
        ]);

        self::assertSame('foo', $mobytOptions->getRecipientId());
    }

    public function testGetRecipientIdWhenNotSet()
    {
        self::assertNull((new MobytOptions())->getRecipientId());
    }

    public function testToArray()
    {
        $mobytOptions = new MobytOptions([
            'message' => 'foo',
            'recipient' => 'bar',
        ]);

        self::assertEmpty($mobytOptions->toArray());
    }

    /**
     * @dataProvider validMessageTypes
     */
    public function testMessageType(string $type)
    {
        $mobytOptions = new MobytOptions();
        $mobytOptions->messageType($type);

        self::assertSame(['message_type' => $type], $mobytOptions->toArray());
    }

    public function validMessageTypes(): iterable
    {
        yield [MobytOptions::MESSAGE_TYPE_QUALITY_HIGH];
        yield [MobytOptions::MESSAGE_TYPE_QUALITY_MEDIUM];
        yield [MobytOptions::MESSAGE_TYPE_QUALITY_LOW];
    }

    public function testCallingMessageTypeMethodWithUnknownTypeThrowsInvalidArgumentException()
    {
        $mobytOptions = new MobytOptions();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The message type "foo-bar" is not supported; supported message types are: "N", "L", "LL"');

        $mobytOptions->messageType('foo-bar');
    }

    public function testSettingMessageTypeViaConstructorWithUnknownTypeThrowsInvalidArgumentException()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The message type "foo-bar" is not supported; supported message types are: "N", "L", "LL"');

        new MobytOptions([
            'message_type' => 'foo-bar',
        ]);
    }
}
