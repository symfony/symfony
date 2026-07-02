<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\MessageListener;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mailer\RemoteTemplateEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[Group('time-sensitive')]
class AbstractTransportTest extends TestCase
{
    public function testThrottling()
    {
        $transport = new NullTransport();
        $transport->setMaxPerSecond(2 / 10);
        $message = new RawMessage('');
        $envelope = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]);

        $start = time();
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(5, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(10, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(15, time() - $start, 1);

        $start = time();
        $transport->setMaxPerSecond(-3);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
    }

    public function testRateLimiting()
    {
        $transport = new class extends AbstractTransport {
            public int $sent = 0;

            protected function doSend(SentMessage $message): void
            {
                ++$this->sent;
            }

            public function __toString(): string
            {
                return 'fake://';
            }
        };
        $transport->setRateLimiterFactory(new RateLimiterFactory([
            'id' => 'mailer',
            'policy' => 'fixed_window',
            'limit' => 2,
            'interval' => '1 hour',
        ], new InMemoryStorage()));

        $message = new RawMessage('');
        $envelope = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]);

        $this->assertNotNull($transport->send($message, $envelope));
        $this->assertNotNull($transport->send($message, $envelope));

        try {
            $transport->send($message, $envelope);
            $this->fail('The third message should not have been sent.');
        } catch (RateLimitExceededException) {
            $this->assertSame(2, $transport->sent);
        }
    }

    public function testSendingRawMessages()
    {
        $this->expectException(LogicException::class);

        $transport = new NullTransport();
        $transport->send(new RawMessage('Some raw email message'));
    }

    public function testNotRenderedTemplatedEmail()
    {
        $this->expectException(LogicException::class);

        $transport = new NullTransport(new EventDispatcher());
        $transport->send((new TemplatedEmail())->htmlTemplate('Some template'));
    }

    public function testRenderedTemplatedEmail()
    {
        $transport = new NullTransport($dispatcher = new EventDispatcher());
        $dispatcher->addSubscriber(new MessageListener(null, new BodyRenderer(new Environment(new ArrayLoader(['tpl' => 'Some message'])))));

        $sentMessage = $transport->send((new TemplatedEmail())->to('me@example.com')->from('me@example.com')->htmlTemplate('tpl'));
        $this->assertMatchesRegularExpression('/Some message/', $sentMessage->getMessage()->toString());
    }

    public function testSendingRemoteTemplateEmailWithUnsupportedTransport()
    {
        $transport = new class(new EventDispatcher()) extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
            }

            public function __toString(): string
            {
                return 'fake://';
            }
        };

        $email = (new RemoteTemplateEmail())
            ->from('fabien@example.com')
            ->to('helene@example.com')
            ->template('welcome', ['firstName' => 'Fabien']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not support sending emails rendered from a remote template');

        $transport->send($email);
    }

    public function testSendingRemoteTemplateEmailWithSupportedTransport()
    {
        $transport = new NullTransport(new EventDispatcher());

        $email = (new RemoteTemplateEmail())
            ->from('fabien@example.com')
            ->to('helene@example.com')
            ->template('welcome', ['firstName' => 'Fabien']);

        $this->assertNotNull($transport->send($email));
    }

    public function testRejectMessage()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(MessageEvent::class, static fn (MessageEvent $event) => $event->reject(), 255);
        $dispatcher->addListener(MessageEvent::class, static fn () => throw new \RuntimeException('Should never be called.'));

        $transport = new class($dispatcher, $this) extends AbstractTransport {
            public function __construct(EventDispatcherInterface $dispatcher, private TestCase $test)
            {
                parent::__construct($dispatcher);
            }

            protected function doSend(SentMessage $message): void
            {
                $this->test->fail('This should never be called as message is rejected.');
            }

            public function __toString(): string
            {
                return 'fake://';
            }
        };

        $message = new RawMessage('');
        $envelope = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]);
        $this->assertNull($transport->send($message, $envelope));
    }
}
