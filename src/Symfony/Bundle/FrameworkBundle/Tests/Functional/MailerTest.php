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

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FullStack;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailerTest extends AbstractWebTestCase
{
    public function testEnvelopeListener()
    {
        self::bootKernel(['test_case' => 'Mailer']);

        $onDoSend = function (SentMessage $message) {
            $envelope = $message->getEnvelope();

            self::assertEquals([new Address('redirected@example.org')], $envelope->getRecipients());

            self::assertEquals('sender@example.org', $envelope->getSender()->getAddress());
        };

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $logger = self::getContainer()->get('logger');

        $testTransport = new class($eventDispatcher, $logger, $onDoSend) extends AbstractTransport {
            /**
             * @var callable
             */
            private $onDoSend;

            public function __construct(EventDispatcherInterface $eventDispatcher, LoggerInterface $logger, callable $onDoSend)
            {
                parent::__construct($eventDispatcher, $logger);
                $this->onDoSend = $onDoSend;
            }

            public function __toString(): string
            {
                return 'dummy://local';
            }

            protected function doSend(SentMessage $message): void
            {
                $onDoSend = $this->onDoSend;
                $onDoSend($message);
            }
        };

        $mailer = new Mailer($testTransport);

        $message = (new Email())
            ->subject('Test subject')
            ->text('Hello world')
            ->from('from@example.org')
            ->to('to@example.org');

        $mailer->send($message);
    }

    public function testMailerAssertions()
    {
        $client = self::createClient(['test_case' => 'Mailer', 'root_config' => 'config.yml', 'debug' => true]);
        $client->request('GET', '/send_email');

        self::assertEmailCount(2);
        $first = 0;
        $second = 1;
        if (!class_exists(FullStack::class)) {
            self::assertQueuedEmailCount(2);
            $first = 1;
            $second = 3;
            self::assertEmailIsQueued(self::getMailerEvent(0));
            self::assertEmailIsQueued(self::getMailerEvent(2));
        }
        self::assertEmailIsNotQueued(self::getMailerEvent($first));
        self::assertEmailIsNotQueued(self::getMailerEvent($second));

        $email = self::getMailerMessage($first);
        self::assertEmailHasHeader($email, 'To');
        self::assertEmailHeaderSame($email, 'To', 'fabien@symfony.com');
        self::assertEmailHeaderNotSame($email, 'To', 'helene@symfony.com');
        self::assertEmailTextBodyContains($email, 'Bar');
        self::assertEmailTextBodyNotContains($email, 'Foo');
        self::assertEmailHtmlBodyContains($email, 'Foo');
        self::assertEmailHtmlBodyNotContains($email, 'Bar');
        self::assertEmailAttachmentCount($email, 1);

        $email = self::getMailerMessage($second);
        self::assertEmailAddressContains($email, 'To', 'fabien@symfony.com');
        self::assertEmailAddressContains($email, 'To', 'thomas@symfony.com');
        self::assertEmailAddressContains($email, 'Reply-To', 'me@symfony.com');
    }
}
