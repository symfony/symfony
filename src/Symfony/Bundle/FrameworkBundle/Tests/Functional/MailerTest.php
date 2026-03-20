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

use PHPUnit\Framework\Attributes\RequiresMethod;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FullStack;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Test\Constraint\EmailHtmlBodyMatchesRegex;
use Symfony\Component\Mime\Test\Constraint\EmailTextBodyMatchesRegex;

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
            private \Closure $onDoSend;

            public function __construct(EventDispatcherInterface $eventDispatcher, LoggerInterface $logger, \Closure $onDoSend)
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
        $this->assertEmailAddressNotContains($email, 'To', 'thomas@symfony.com');

        $email = $this->getMailerMessage($second);
        $this->assertEmailSubjectContains($email, 'Foo');
        $this->assertEmailSubjectNotContains($email, 'Bar');
        $this->assertEmailAddressContains($email, 'To', 'fabien@symfony.com');
        $this->assertEmailAddressContains($email, 'To', 'thomas@symfony.com');
        $this->assertEmailAddressContains($email, 'Reply-To', 'me@symfony.com');
        $this->assertEmailAddressNotContains($email, 'To', 'helene@symfony.com');
        $this->assertEmailAddressNotContains($email, 'Reply-To', 'helene@symfony.com');
    }

    #[RequiresMethod(EmailHtmlBodyMatchesRegex::class, '__construct')]
    #[RequiresMethod(EmailTextBodyMatchesRegex::class, '__construct')]
    public function testMailerAssertionsWithRegex()
    {
        $client = $this->createClient(['test_case' => 'Mailer', 'root_config' => 'config.yml', 'debug' => true]);
        $client->request('GET', '/send_email_with_template');

        $this->assertEmailCount(1);
        $first = 0;
        if (!class_exists(FullStack::class)) {
            $this->assertQueuedEmailCount(1);
            $first = 1;
            $this->assertEmailIsQueued($this->getMailerEvent(0));
        }
        $this->assertEmailIsNotQueued($this->getMailerEvent($first));

        $email = $this->getMailerMessage($first);
        $this->assertEmailAddressContains($email, 'From', 'sanmartindev@gmail.com');
        $this->assertEmailAddressContains($email, 'To', 'other_account@example.com');
        $this->assertEmailSubjectContains($email, 'Welcome');
        $this->assertEmailHtmlBodyContains($email, '<h1>Welcome!</h1>');
        $this->assertEmailHtmlBodyContains($email, '<p>Your user is santysisi</p>');
        $this->assertEmailHtmlBodyMatchesRegex($email, '<p>Your password is [A-Za-z0-9]{7,10}[0-9][!@#$%^&*]</p>');
        $this->assertEmailHtmlBodyMatchesRegex($email, '<a href="https://mysuperwebapplication/activate/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/">Activate your account</a>');
        $this->assertEmailHtmlBodyNotMatchesRegex($email, '<p>Your password is [A-Za-z0-9]{7,10}[0-9][!@#$%^&*]{100}</p>');
        $this->assertEmailHtmlBodyNotMatchesRegex($email, '<a href="https://mysuperwebapplication/activate/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}/">Activate your account</a>');
        $this->assertEmailTextBodyContains($email, 'Welcome!');
        $this->assertEmailTextBodyContains($email, 'Your user is santysisi');
        $this->assertEmailTextBodyMatchesRegex($email, 'Your password is [A-Za-z0-9]{7,10}[0-9][!@#$%^&*]');
        $this->assertEmailTextBodyMatchesRegex($email, 'Link for Activate your account: https://mysuperwebapplication/activate/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/');
        $this->assertEmailTextBodyNotMatchesRegex($email, 'Your password is [A-Za-z0-9]{7,10}[0-9][!@#$%^&*]{100}');
        $this->assertEmailTextBodyNotMatchesRegex($email, 'Link for Activate your account: https://mysuperwebapplication/activate/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}/');
    }
}
