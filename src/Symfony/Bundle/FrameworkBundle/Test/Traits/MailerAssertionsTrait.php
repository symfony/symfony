<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test\Traits;

use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Test\Constraint as MailerConstraint;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mime\Test\Constraint as MimeConstraint;

trait MailerAssertionsTrait
{
    public static function assertEmailCount(int $count, ?string $transport = null, string $message = ''): void
    {
        self::assertThat(self::getMailerMessageEvents(), new MailerConstraint\EmailCount($count, $transport), $message);
    }

    public static function assertQueuedEmailCount(int $count, ?string $transport = null, string $message = ''): void
    {
        self::assertThat(self::getMailerMessageEvents(), new MailerConstraint\EmailCount($count, $transport, true), $message);
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

    public static function assertEmailSubjectContains(RawMessage $email, string $expectedValue, string $message = ''): void
    {
        self::assertThat($email, new MimeConstraint\EmailSubjectContains($expectedValue), $message);
    }

    public static function assertEmailSubjectNotContains(RawMessage $email, string $expectedValue, string $message = ''): void
    {
        self::assertThat($email, new LogicalNot(new MimeConstraint\EmailSubjectContains($expectedValue)), $message);
    }
}
