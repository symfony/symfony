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
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Handler\MailerHandler;
use Symfony\Bridge\Monolog\Tests\RecordFactory;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerHandlerTest extends TestCase
{
    /** @var MockObject|MailerInterface */
    private $mailer = null;

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
            ->with($this->callback(function (Email $email) {
                return 'Alert: WARNING message' === $email->getSubject() && null === $email->getHtmlBody();
            }))
        ;
        $handler->handle($this->getRecord(Monolog::WARNING, 'message'));
    }

    public function testHandleBatch()
    {
        $handler = new MailerHandler($this->mailer, (new Email())->subject('Alert: %level_name% %message%'));
        $handler->setFormatter(new LineFormatter());
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return 'Alert: ERROR error' === $email->getSubject() && null === $email->getHtmlBody();
            }))
        ;
        $handler->handleBatch($this->getMultipleRecords());
    }

    public function testMessageCreationIsLazyWhenUsingCallback()
    {
        $this->mailer
            ->expects($this->never())
            ->method('send')
        ;

        $callback = function () {
            throw new \RuntimeException('Email creation callback should not have been called in this test');
        };
        $handler = new MailerHandler($this->mailer, $callback, Monolog::ALERT);

        $records = [
            $this->getRecord(Monolog::DEBUG),
            $this->getRecord(Monolog::INFO),
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
            ->with($this->callback(function (Email $email) {
                return 'Alert: WARNING message' === $email->getSubject() && null === $email->getTextBody();
            }))
        ;
        $handler->handle($this->getRecord(Monolog::WARNING, 'message'));
    }

    protected function getRecord($level = Monolog::WARNING, $message = 'test', $context = []): array|LogRecord
    {
        return RecordFactory::create($level, $message, context: $context);
    }

    protected function getMultipleRecords(): array
    {
        return [
            $this->getRecord(Monolog::DEBUG, 'debug message 1'),
            $this->getRecord(Monolog::DEBUG, 'debug message 2'),
            $this->getRecord(Monolog::INFO, 'information'),
            $this->getRecord(Monolog::WARNING, 'warning'),
            $this->getRecord(Monolog::ERROR, 'error'),
        ];
    }
}
