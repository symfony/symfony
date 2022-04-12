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

            $this->assertEquals(
                [new Address('redirected@example.org')],
                $envelope->getRecipients()
            );

            $this->assertEquals('sender@example.org', $envelope->getSender()->getAddress());
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
        $client = $this->createClient(['test_case' => 'Mailer', 'root_config' => 'config.yml', 'debug' => true]);
        $client->request('GET', '/send_email');

        $this->assertEmailCount(2);
        $first = 0;
        $second = 1;
        if (!class_exists(FullStack::class)) {
            $this->assertQueuedEmailCount(2);
            $first = 1;
            $second = 3;
            $this->assertEmailIsQueued($this->getMailerEvent(0));
            $this->assertEmailIsQueued($this->getMailerEvent(2));
        }
        $this->assertEmailIsNotQueued($this->getMailerEvent($first));
        $this->assertEmailIsNotQueued($this->getMailerEvent($second));

        $email = $this->getMailerMessage($first);
        $this->assertEmailHasHeader($email, 'To');
        $this->assertEmailHeaderSame($email, 'To', 'fabien@symfony.com');
        $this->assertEmailHeaderNotSame($email, 'To', 'helene@symfony.com');
        $this->assertEmailTextBodyContains($email, 'Bar');
        $this->assertEmailTextBodyNotContains($email, 'Foo');
        $this->assertEmailHtmlBodyContains($email, 'Foo');
        $this->assertEmailHtmlBodyNotContains($email, 'Bar');
        $this->assertEmailAttachmentCount($email, 1);

        $email = $this->getMailerMessage($second);
        $this->assertEmailAddressContains($email, 'To', 'fabien@symfony.com');
        $this->assertEmailAddressContains($email, 'To', 'thomas@symfony.com');
        $this->assertEmailAddressContains($email, 'Reply-To', 'me@symfony.com');
    }
}
