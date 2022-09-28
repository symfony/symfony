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

use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\MessageEvents;
use Symfony\Component\Mailer\Test\Constraint as MailerConstraint;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mime\Test\Constraint as MimeConstraint;

trait MailerAssertionsTrait
{
    public static function assertEmailCount(int $count, string $transport = null, string $message = ''): void
    {
        self::assertThat(self::getMessageMailerEvents(), new MailerConstraint\EmailCount($count, $transport), $message);
    }

    public static function assertQueuedEmailCount(int $count, string $transport = null, string $message = ''): void
    {
        self::assertThat(self::getMessageMailerEvents(), new MailerConstraint\EmailCount($count, $transport, true), $message);
    }

    public static function assertEmailIsQueued(MessageEvent $event, string $message = ''): void
    {
        self::assertThat($event, new MailerConstraint\EmailIsQueued(), $message);
    }

    public static function assertEmailIsNotQueued(MessageEvent $event, string $message = ''): void
    {
        self::assertThat($event, new LogicalNot(new MailerConstraint\EmailIsQueued()), $message);
    }

    public static function assertEmailAttachmentCount(RawMessage $email, int $count, string $message = ''): void
    {
        self::assertThat($email, new MimeConstraint\EmailAttachmentCount($count), $message);
    }

    public static function assertEmailTextBodyContains(RawMessage $email, string $text, string $message = ''): void
    {
        self::assertThat($email, new MimeConstraint\EmailTextBodyContains($text), $message);
    }

    public static function assertEmailTextBodyNotContains(RawMessage $email, string $text, string $message = ''): void
    {
        self::assertThat($email, new LogicalNot(new MimeConstraint\EmailTextBodyContains($text)), $message);
    }

    public static function assertEmailHtmlBodyContains(RawMessage $email, string $text, string $message = ''): void
    {
        self::assertThat($email, new MimeConstraint\EmailHtmlBodyContains($text), $message);
    }

    public static function assertEmailHtmlBodyNotContains(RawMessage $email, string $text, string $message = ''): void
    {
        self::assertThat($email, new LogicalNot(new MimeConstraint\EmailHtmlBodyContains($text)), $message);
    }

    public static function assertEmailHasHeader(RawMessage $email, string $headerName, string $message = ''): void
    {
        self::assertThat($email, new MimeConstraint\EmailHasHeader($headerName), $message);
    }

    public static function assertEmailNotHasHeader(RawMessage $email, string $headerName, string $message = ''): void
    {
        self::assertThat($email, new LogicalNot(new MimeConstraint\EmailHasHeader($headerName)), $message);
    }

    public static function assertEmailHeaderSame(RawMessage $email, string $headerName, string $expectedValue, string $message = ''): void
    {
        self::assertThat($email, new MimeConstraint\EmailHeaderSame($headerName, $expectedValue), $message);
    }

    public static function assertEmailHeaderNotSame(RawMessage $email, string $headerName, string $expectedValue, string $message = ''): void
    {
        self::assertThat($email, new LogicalNot(new MimeConstraint\EmailHeaderSame($headerName, $expectedValue)), $message);
    }

    public static function assertEmailAddressContains(RawMessage $email, string $headerName, string $expectedValue, string $message = ''): void
    {
        self::assertThat($email, new MimeConstraint\EmailAddressContains($headerName, $expectedValue), $message);
    }

    /**
     * @return MessageEvent[]
     */
    public static function getMailerEvents(string $transport = null): array
    {
        return self::getMessageMailerEvents()->getEvents($transport);
    }

    public static function getMailerEvent(int $index = 0, string $transport = null): ?MessageEvent
    {
        return self::getMailerEvents($transport)[$index] ?? null;
    }

    /**
     * @return RawMessage[]
     */
    public static function getMailerMessages(string $transport = null): array
    {
        return self::getMessageMailerEvents()->getMessages($transport);
    }

    public static function getMailerMessage(int $index = 0, string $transport = null): ?RawMessage
    {
        return self::getMailerMessages($transport)[$index] ?? null;
    }

    private static function getMessageMailerEvents(): MessageEvents
    {
        $container = static::getContainer();
        if ($container->has('mailer.message_logger_listener')) {
            return $container->get('mailer.message_logger_listener')->getEvents();
        }

        if ($container->has('mailer.logger_message_listener')) {
            return $container->get('mailer.logger_message_listener')->getEvents();
        }

        static::fail('A client must have Mailer enabled to make email assertions. Did you forget to require symfony/mailer?');
    }
}
