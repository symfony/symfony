<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Handler;

use Monolog\Formatter\HtmlFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Handler\MailerHandler;
use Symfony\Bridge\Monolog\Tests\RecordFactory;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerHandlerTest extends TestCase
{
    private MockObject&MailerInterface $mailer;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
    }

    public function testHandle()
    {
        $handler = new MailerHandler($this->mailer, (new Email())->subject('Alert: %level_name% %message%'));
        $handler->setFormatter(new LineFormatter());
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(static fn (Email $email) => 'Alert: WARNING message' === $email->getSubject() && null === $email->getHtmlBody()))
        ;
        $handler->handle($this->getRecord(Level::Warning, 'message'));
    }

    public function testHandleBatch()
    {
        $handler = new MailerHandler($this->mailer, (new Email())->subject('Alert: %level_name% %message%'));
        $handler->setFormatter(new LineFormatter());
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(static fn (Email $email) => 'Alert: ERROR error' === $email->getSubject() && null === $email->getHtmlBody()))
        ;
        $handler->handleBatch($this->getMultipleRecords());
    }

    public function testMessageCreationIsLazyWhenUsingCallback()
    {
        $this->mailer
            ->expects($this->never())
            ->method('send')
        ;

        $callback = static function () {
            throw new \RuntimeException('Email creation callback should not have been called in this test');
        };
        $handler = new MailerHandler($this->mailer, $callback, Level::Alert);

        $records = [
            $this->getRecord(Level::Debug),
            $this->getRecord(Level::Info),
        ];
        $handler->handleBatch($records);
    }

    public function testHtmlContent()
    {
        $handler = new MailerHandler($this->mailer, (new Email())->subject('Alert: %level_name% %message%'));
        $handler->setFormatter(new HtmlFormatter());
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(static fn (Email $email) => 'Alert: WARNING message' === $email->getSubject() && null === $email->getTextBody()))
        ;
        $handler->handle($this->getRecord(Level::Warning, 'message'));
    }

    public function testSubjectIsTruncatedWithEllipsis()
    {
        $handler = new MailerHandler($this->mailer, (new Email())->subject('Alert: %message%'), Level::Debug, true, 50);
        $handler->setFormatter(new LineFormatter());

        $longMessage = str_repeat('a', 200);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $this->assertSame('Alert: '.str_repeat('a', 38).'[...]', $email->getSubject());
                $this->assertSame(50, mb_strlen($email->getSubject()));

                return true;
            }))
        ;
        $handler->handle($this->getRecord(Level::Warning, $longMessage));
    }

    public function testSubjectIsNotTruncatedWhenShorterThanMax()
    {
        $handler = new MailerHandler($this->mailer, (new Email())->subject('Alert: %message%'), Level::Debug, true, 50);
        $handler->setFormatter(new LineFormatter());

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $this->assertSame('Alert: short', $email->getSubject());

                return true;
            }))
        ;
        $handler->handle($this->getRecord(Level::Warning, 'short'));
    }

    public function testSubjectDefaultMaxLengthTruncatesLongMessages()
    {
        $handler = new MailerHandler($this->mailer, (new Email())->subject('Alert: %message%'));
        $handler->setFormatter(new LineFormatter());

        $longMessage = str_repeat('a', 500);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $this->assertSame(200, mb_strlen($email->getSubject()));
                $this->assertStringEndsWith('[...]', $email->getSubject());

                return true;
            }))
        ;
        $handler->handle($this->getRecord(Level::Warning, $longMessage));
    }

    public function testSubjectIsTruncatedSafelyForMultibyteCharacters()
    {
        $handler = new MailerHandler($this->mailer, (new Email())->subject('Alert: %message%'), Level::Debug, true, 50);
        $handler->setFormatter(new LineFormatter());

        $longMessage = str_repeat('é', 200);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $subject = $email->getSubject();
                $this->assertSame(50, mb_strlen($subject));
                $this->assertStringEndsWith('[...]', $subject);
                $this->assertTrue(mb_check_encoding($subject, 'UTF-8'));
                $this->assertSame('Alert: '.str_repeat('é', 38).'[...]', $subject);

                return true;
            }))
        ;
        $handler->handle($this->getRecord(Level::Warning, $longMessage));
    }

    public function testSubjectMaxLengthZeroDisablesTruncation()
    {
        $handler = new MailerHandler($this->mailer, (new Email())->subject('Alert: %message%'), Level::Debug, true, 0);
        $handler->setFormatter(new LineFormatter());

        $longMessage = str_repeat('a', 500);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($longMessage) {
                $this->assertSame('Alert: '.$longMessage, $email->getSubject());

                return true;
            }))
        ;
        $handler->handle($this->getRecord(Level::Warning, $longMessage));
    }

    public function testNegativeSubjectMaxLengthThrows()
    {
        $this->mailer->expects($this->never())->method('send');

        $this->expectException(\InvalidArgumentException::class);

        new MailerHandler($this->mailer, (new Email())->subject('Alert: %message%'), Level::Debug, true, -1);
    }

    public function testSubjectMaxLengthSmallerThanMarkerThrows()
    {
        $this->mailer->expects($this->never())->method('send');

        $this->expectException(\InvalidArgumentException::class);

        new MailerHandler($this->mailer, (new Email())->subject('Alert: %message%'), Level::Debug, true, \strlen(MailerHandler::TRUNCATION_MARKER) - 1);
    }

    public function testSubjectNotTruncatedAtExactBoundary()
    {
        $handler = new MailerHandler($this->mailer, (new Email())->subject('%message%'), Level::Debug, true, 50);
        $handler->setFormatter(new LineFormatter());

        $message = str_repeat('a', 50);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($message) {
                $this->assertSame($message, $email->getSubject());

                return true;
            }))
        ;
        $handler->handle($this->getRecord(Level::Warning, $message));
    }

    public function testSubjectTruncatedJustPastBoundary()
    {
        $handler = new MailerHandler($this->mailer, (new Email())->subject('%message%'), Level::Debug, true, 50);
        $handler->setFormatter(new LineFormatter());

        $message = str_repeat('a', 51);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $this->assertSame(str_repeat('a', 45).'[...]', $email->getSubject());
                $this->assertSame(50, mb_strlen($email->getSubject()));

                return true;
            }))
        ;
        $handler->handle($this->getRecord(Level::Warning, $message));
    }

    protected function getRecord($level = Level::Warning, $message = 'test', $context = []): array|LogRecord
    {
        return RecordFactory::create($level, $message, context: $context);
    }

    protected function getMultipleRecords(): array
    {
        return [
            $this->getRecord(Level::Debug, 'debug message 1'),
            $this->getRecord(Level::Debug, 'debug message 2'),
            $this->getRecord(Level::Info, 'information'),
            $this->getRecord(Level::Warning, 'warning'),
            $this->getRecord(Level::Error, 'error'),
        ];
    }
}
